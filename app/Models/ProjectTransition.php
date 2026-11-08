<?php

namespace App\Models;

use App\Enums\WorkflowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProjectTransition extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['project_id', 'from_status', 'to_status', 'user_id', 'note'];

    protected function casts(): array
    {
        // Plain strings: rows from the retired AI workflow hold statuses that no longer exist.
        return ['created_at' => 'datetime'];
    }

    public function toLabel(): string
    {
        return self::labelFor($this->to_status);
    }

    public static function labelFor(?string $status): string
    {
        return WorkflowStatus::tryFrom((string) $status)?->label() ?? ($status ? Str::headline($status).' (previous workflow)' : '—');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
