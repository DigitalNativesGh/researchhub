<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Who submitted, downloaded, uploaded or delivered what, and when. */
class ProjectAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['project_id', 'user_id', 'action', 'document_id', 'summary', 'details', 'ip_address'];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ProjectDocument::class, 'document_id');
    }

    public static function record(Project $project, string $action, string $summary, ?ProjectDocument $document = null, array $details = [], ?User $user = null): self
    {
        $user ??= auth()->user();

        return self::create([
            'project_id' => $project->id,
            'user_id' => $user?->id,
            'action' => $action,
            'document_id' => $document?->id,
            'summary' => $summary,
            'details' => $details ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()?->ip(),
        ]);
    }
}
