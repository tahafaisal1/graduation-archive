<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Proposal;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateProposalProjectData extends Command
{
    protected $signature = 'proposals:migrate-legacy-data';

    protected $description = 'One-time migration: split projects_legacy rows into proposals + instantiated projects';

    public function handle(): int
    {
        DB::transaction(function () {
            $this->cleanErroneousGrading();
            $idMap = $this->migrateProposals();
            $this->migrateProposalStudents();
            $newProjectIds = $this->instantiateProjectsForArchivedRows($idMap);
            $this->repointExaminersAndEvaluations($newProjectIds);
            $this->restoreExaminerEvaluationForeignKeys();
            $this->repointBasedOnChains($newProjectIds);
        });

        $this->verifyCounts();

        // DDL below is deliberately outside the transaction (MySQL DDL
        // auto-commits and cannot be rolled back with it anyway) and only
        // runs once verifyCounts() has confirmed the data copy succeeded.
        $this->dropLegacyTables();

        return self::SUCCESS;
    }

    /**
     * Decision 1: id=1's grading data was found erroneous during audit
     * (examiners/evaluations/a score attached to a still-مقترح row). Clean
     * it — and any row in the same state — before it gets copied forward.
     */
    private function cleanErroneousGrading(): void
    {
        $badIds = DB::table('projects_legacy')
            ->where('current_status_id', Proposal::STATUS_PENDING)
            ->whereIn('id', function ($q) {
                $q->select('project_id')->from('project_examiners');
            })
            ->pluck('id');

        if ($badIds->isEmpty()) {
            return;
        }

        $this->warn("Cleaning erroneous grading data on مقترح rows: {$badIds->implode(', ')}");

        DB::table('project_examiners')->whereIn('project_id', $badIds)->delete();
        DB::table('evaluations')->whereIn('project_id', $badIds)->delete();
        DB::table('projects_legacy')->whereIn('id', $badIds)->update(['final_score' => null]);
    }

    /** @return array<int,int> old projects_legacy.id => proposals.id (identical, ids preserved) */
    private function migrateProposals(): array
    {
        $rows = DB::table('projects_legacy')->orderBy('id')->get();
        $idMap = [];

        foreach ($rows as $row) {
            DB::table('proposals')->insert([
                'id'                   => $row->id, // preserve original id — simplifies every downstream re-pointing step
                'title'                => $row->project_title,
                'description'          => $row->description,
                'academic_year'        => $row->academic_year,
                'department_id'        => $row->department_id,
                'specialization_id'    => $row->specialization_id,
                'supervisor_id'        => $row->supervisor_id,
                'created_by'           => $row->created_by,
                'draft_file_path'      => $row->draft_file_path,
                'status_id'            => $row->current_status_id,
                'based_on_project_id'  => null, // re-pointed in repointBasedOnChains() once new project ids exist
                'is_deleted'           => $row->is_deleted,
                'created_at'           => $row->created_at,
                'updated_at'           => $row->updated_at,
            ]);
            $idMap[$row->id] = $row->id;
        }

        $this->fixProposalsAutoIncrement((int) ($rows->max('id') ?? 0));

        return $idMap;
    }

    /**
     * Keep `proposals`' auto-increment counter ahead of the highest
     * preserved legacy id, so the next real (non-migrated) proposal doesn't
     * collide with one of these ids.
     *
     * SQLite has no ALTER TABLE ... AUTO_INCREMENT statement (nor does it
     * accept MySQL's syntax at all — it's a hard parse error, not just a
     * no-op) — its equivalent counter lives in the sqlite_sequence table,
     * one row per AUTOINCREMENT table. Branch on driver so this command
     * still runs against Pest's in-memory SQLite test database as well as
     * the real MySQL dev database.
     */
    private function fixProposalsAutoIncrement(int $maxId): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $updated = DB::table('sqlite_sequence')->where('name', 'proposals')->update(['seq' => $maxId]);

            if (! $updated) {
                DB::table('sqlite_sequence')->insert(['name' => 'proposals', 'seq' => $maxId]);
            }

            return;
        }

        DB::statement('ALTER TABLE proposals AUTO_INCREMENT = ?', [$maxId + 1]);
    }

    private function migrateProposalStudents(): void
    {
        $students = DB::table('project_students')->get();

        foreach ($students as $student) {
            DB::table('proposal_students')->insert([
                'proposal_id'          => $student->project_id,
                'full_name'            => $student->full_name,
                'registration_number'  => $student->registration_number,
                'status'               => $student->status,
                'withdrawal_date'      => $student->withdrawal_date,
                'created_at'           => $student->created_at,
                'updated_at'           => $student->updated_at,
            ]);
        }
    }

    /** @return array<int,int> old projects_legacy.id => new projects.id */
    private function instantiateProjectsForArchivedRows(array $idMap): array
    {
        $archived = DB::table('projects_legacy')->where('current_status_id', Proposal::STATUS_ARCHIVED)->get();
        $newProjectIds = [];

        foreach ($archived as $row) {
            $hasGrading = DB::table('project_examiners')->where('project_id', $row->id)->exists();

            $newId = DB::table('projects')->insertGetId([
                'proposal_id'      => $idMap[$row->id],
                'status_id'        => $hasGrading ? Project::STATUS_ARCHIVED : Project::STATUS_IN_PROGRESS,
                'final_score'      => $row->final_score,
                'instantiated_by'  => null, // no real actor for backfilled historical data
                'instantiated_at'  => $row->updated_at, // best available proxy for "when it became مؤرشف"
                'visit_count'      => $row->visit_count,
                'is_deleted'       => $row->is_deleted,
                'created_at'       => $row->created_at,
                'updated_at'       => $row->updated_at,
            ]);

            $newProjectIds[$row->id] = $newId;
        }

        return $newProjectIds;
    }

    private function repointExaminersAndEvaluations(array $newProjectIds): void
    {
        foreach ($newProjectIds as $oldId => $newId) {
            DB::table('project_examiners')->where('project_id', $oldId)->update(['project_id' => $newId]);
            DB::table('evaluations')->where('project_id', $oldId)->update(['project_id' => $newId]);
        }
    }

    /**
     * Uses the Schema Builder (matching the exact pattern already used in
     * migration 2026_08_24_160000's down()) rather than raw
     * `ALTER TABLE ... ADD CONSTRAINT ... FOREIGN KEY` DB::statement calls:
     * SQLite's ALTER TABLE only supports RENAME/ADD COLUMN/DROP COLUMN — it
     * has no ADD CONSTRAINT form at all, so MySQL-flavoured raw SQL here
     * would be a hard syntax error against Pest's SQLite test database.
     * Schema::table()->foreign() compiles to the correct DDL for either
     * driver and produces the same named constraint.
     */
    private function restoreExaminerEvaluationForeignKeys(): void
    {
        Schema::table('project_examiners', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    private function repointBasedOnChains(array $newProjectIds): void
    {
        $chains = DB::table('projects_legacy')->whereNotNull('based_on_project_id')->get(['id', 'based_on_project_id']);

        foreach ($chains as $chain) {
            $target = $newProjectIds[$chain->based_on_project_id] ?? null;

            if ($target === null) {
                $this->warn("Skipping based_on chain for proposal {$chain->id}: target {$chain->based_on_project_id} was never instantiated (unexpected — a مقترح target has no project row).");
                continue;
            }

            DB::table('proposals')->where('id', $chain->id)->update(['based_on_project_id' => $target]);
        }
    }

    private function verifyCounts(): void
    {
        $proposals = DB::table('proposals')->count();
        $projects  = DB::table('projects')->count();

        $this->info("proposals: {$proposals}, projects: {$projects}");

        if ($proposals === 0) {
            throw new \RuntimeException('Migration produced zero proposals — aborting, transaction will roll back.');
        }
    }

    private function dropLegacyTables(): void
    {
        Schema::dropIfExists('project_students');
        Schema::dropIfExists('projects_legacy');
        $this->info('Dropped legacy tables: project_students, projects_legacy.');
    }
}
