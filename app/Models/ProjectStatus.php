<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectStatus extends Model
{
    protected $table = 'project_status';

    protected $fillable = ['status_name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ProjectStatus now backs proposals.status_id, not Project — Project's
    // own status lives on ProjectLifecycleStatus. There is no "projects with
    // this proposal-status" relationship to expose any more; see Proposal::status().
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'status_id');
    }
}
