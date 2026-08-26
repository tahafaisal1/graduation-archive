<?php

use App\Models\Department;
use App\Models\Proposal;
use App\Models\Specialization;
use App\Models\User;
use App\Services\SearchService;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
    $this->seed(ProjectStatusSeeder::class);
});

// ── Helper ────────────────────────────────────────────────────────────────────

/**
 * Creates a proposal with all required FK deps. This search/filter surface
 * (proposals.index + search.index/search.suggestions) is powered by
 * SearchService::searchProposals(), which queries the `proposals` table
 * directly — no instantiated Project is needed for any of these tests.
 * Omit $dept / $spec / $supervisor to have fresh ones auto-created.
 */
function makeSearchProject(
    ?Department $dept = null,
    ?Specialization $spec = null,
    ?User $supervisor = null,
    array $overrides = [],
): Proposal {
    $dept       ??= Department::factory()->create();
    $spec       ??= Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor ??= userWithRole('supervisor');

    return Proposal::factory()->create(array_merge([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supervisor->id,
    ], $overrides));
}

// ── 1. Search by title ────────────────────────────────────────────────────────

test('search by title returns correct projects', function () {
    makeSearchProject(overrides: ['title' => 'Alpha Robot Controller']);
    makeSearchProject(overrides: ['title' => 'Beta Database System']);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('proposals.index', ['search' => 'Alpha Robot']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Proposals/Index')
            ->has('proposals.data', 1)
            ->where('proposals.data.0.title', 'Alpha Robot Controller')
        );
});

// ── 2. Search by description ──────────────────────────────────────────────────

test('search by description returns correct projects', function () {
    makeSearchProject(overrides: [
        'title'       => 'Generic Title One',
        'description' => 'Uses xylophone resonance for signal analysis',
    ]);
    makeSearchProject(overrides: [
        'title'       => 'Generic Title Two',
        'description' => 'Completely unrelated subject matter',
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('proposals.index', ['search' => 'xylophone']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Proposals/Index')
            ->has('proposals.data', 1)
        );
});

// ── 3. Case-insensitive search ────────────────────────────────────────────────

test('search is case insensitive', function () {
    makeSearchProject(overrides: ['title' => 'Machine Learning Application']);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('proposals.index', ['search' => 'machine learning']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Proposals/Index')
            ->has('proposals.data', 1)
        );
});

// ── 4. Filter by department ───────────────────────────────────────────────────

test('filter by department returns only that department projects', function () {
    $deptA = Department::factory()->create();
    $specA = Specialization::factory()->create(['department_id' => $deptA->id]);
    $deptB = Department::factory()->create();
    $specB = Specialization::factory()->create(['department_id' => $deptB->id]);
    $sup   = userWithRole('supervisor');

    Proposal::factory()->count(2)->create([
        'department_id'     => $deptA->id,
        'specialization_id' => $specA->id,
        'supervisor_id'     => $sup->id,
    ]);
    Proposal::factory()->create([
        'department_id'     => $deptB->id,
        'specialization_id' => $specB->id,
        'supervisor_id'     => $sup->id,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('proposals.index', ['department_id' => $deptA->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('proposals.data', 2)
        );
});

// ── 5. Filter by specialization ───────────────────────────────────────────────

test('filter by specialization returns correct results', function () {
    $dept  = Department::factory()->create();
    $specA = Specialization::factory()->create(['department_id' => $dept->id]);
    $specB = Specialization::factory()->create(['department_id' => $dept->id]);
    $sup   = userWithRole('supervisor');

    Proposal::factory()->count(2)->create([
        'department_id'     => $dept->id,
        'specialization_id' => $specA->id,
        'supervisor_id'     => $sup->id,
    ]);
    Proposal::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $specB->id,
        'supervisor_id'     => $sup->id,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('proposals.index', ['specialization_id' => $specA->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('proposals.data', 2)
        );
});

// ── 6. Filter by academic year ────────────────────────────────────────────────

test('filter by academic year returns correct results', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $sup  = userWithRole('supervisor');

    Proposal::factory()->count(2)->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $sup->id,
        'academic_year'     => '2024/2025',
    ]);
    Proposal::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $sup->id,
        'academic_year'     => '2023/2024',
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('proposals.index', ['academic_year' => '2024/2025']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('proposals.data', 2)
        );
});

// ── 7. Filter by supervisor ───────────────────────────────────────────────────

test('filter by supervisor returns correct results', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supA = userWithRole('supervisor');
    $supB = userWithRole('supervisor');

    Proposal::factory()->count(2)->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supA->id,
    ]);
    Proposal::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supB->id,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('proposals.index', ['supervisor_id' => $supA->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('proposals.data', 2)
        );
});

// ── 8. Combine multiple filters ───────────────────────────────────────────────

test('combine multiple filters works correctly', function () {
    $deptA = Department::factory()->create();
    $specA = Specialization::factory()->create(['department_id' => $deptA->id]);
    $deptB = Department::factory()->create();
    $specB = Specialization::factory()->create(['department_id' => $deptB->id]);
    $sup   = userWithRole('supervisor');

    // 2 proposals that should match (dept A + year 2024/2025)
    Proposal::factory()->count(2)->create([
        'department_id'     => $deptA->id,
        'specialization_id' => $specA->id,
        'supervisor_id'     => $sup->id,
        'academic_year'     => '2024/2025',
    ]);
    // Non-matching: dept A but wrong year
    Proposal::factory()->create([
        'department_id'     => $deptA->id,
        'specialization_id' => $specA->id,
        'supervisor_id'     => $sup->id,
        'academic_year'     => '2023/2024',
    ]);
    // Non-matching: correct year but wrong dept
    Proposal::factory()->create([
        'department_id'     => $deptB->id,
        'specialization_id' => $specB->id,
        'supervisor_id'     => $sup->id,
        'academic_year'     => '2024/2025',
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('proposals.index', [
            'department_id' => $deptA->id,
            'academic_year' => '2024/2025',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('proposals.data', 2)
        );
});

// ── 9. Empty search returns all non-deleted proposals ──────────────────────────

test('empty search returns all projects', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $sup  = userWithRole('supervisor');

    Proposal::factory()->count(3)->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $sup->id,
        'is_deleted'        => false,
    ]);
    // Soft-deleted: must be excluded
    Proposal::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $sup->id,
        'is_deleted'        => true,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('proposals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('proposals.data', 3)
        );
});

// ── 10. Similarity detection: finds matches ───────────────────────────────────

test('similarity detection finds matching titles', function () {
    makeSearchProject(overrides: ['title' => 'Smart Home Automation System']);

    $results = app(SearchService::class)->detectSimilarity('Home Automation');

    expect($results)->toHaveCount(1);
    expect($results->first()->title)->toBe('Smart Home Automation System');
});

// ── 11. Similarity detection: excludes current proposal ────────────────────────

test('similarity detection ignores current project when editing', function () {
    $proposal = makeSearchProject(overrides: ['title' => 'Smart Home Automation System']);

    // A second proposal with the same title DOES exist and would normally match
    makeSearchProject(overrides: ['title' => 'Smart Home Automation System']);

    // When excluding by id, only the other proposal is returned
    $results = app(SearchService::class)->detectSimilarity('Smart Home Automation', $proposal->id);

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->not->toBe($proposal->id);
});

// ── 12. Suggestions: max 5 results ───────────────────────────────────────────

test('search suggestions returns max 5 results', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $sup  = userWithRole('supervisor');

    $titles = ['Alpha Smart', 'Beta Smart', 'Gamma Smart', 'Delta Smart', 'Epsilon Smart', 'Zeta Smart'];
    foreach ($titles as $title) {
        Proposal::factory()->create([
            'title'             => $title,
            'department_id'     => $dept->id,
            'specialization_id' => $spec->id,
            'supervisor_id'     => $sup->id,
        ]);
    }

    $this->actingAs(userWithRole('super_admin'))
        ->getJson(route('search.suggestions', ['q' => 'Smart']))
        ->assertOk()
        ->assertJsonCount(5);
});

// ── 13. Suggestions: matching titles only ─────────────────────────────────────

test('search suggestions returns matching titles only', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $sup  = userWithRole('supervisor');

    Proposal::factory()->create([
        'title'             => 'Neural Network Classifier',
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $sup->id,
    ]);
    Proposal::factory()->create([
        'title'             => 'Database Optimization Tool',
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $sup->id,
    ]);

    $response = $this->actingAs(userWithRole('super_admin'))
        ->getJson(route('search.suggestions', ['q' => 'Neural']))
        ->assertOk()
        ->assertJsonCount(1);

    expect($response->json(0))->toBe('Neural Network Classifier');
});

// ── 14. Unauthenticated access is blocked ─────────────────────────────────────

test('unauthenticated user cannot search', function () {
    $this->get(route('search.index'))
        ->assertRedirect('/login');
});

// ── 15. Results are paginated ─────────────────────────────────────────────────

test('search results are paginated', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $sup  = userWithRole('supervisor');

    Proposal::factory()->count(16)->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $sup->id,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('proposals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('proposals.last_page', 2)
            ->where('proposals.total', 16)
        );
});
