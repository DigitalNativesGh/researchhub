<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Which editor has a stage's documents and when they are due back. Internal: never shown to researchers. */
class EditingAssignment extends Model
{
    protected $fillable = ['project_id', 'stage', 'editor_name', 'expected_return_at', 'notes', 'assigned_by', 'completed_at'];

    protected function casts(): array
    {
        return ['stage' => 'integer', 'expected_return_at' => 'date', 'completed_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isOverdue(): bool
    {
        return ! $this->completed_at && $this->expected_return_at?->endOfDay()->isPast();
    }
}
