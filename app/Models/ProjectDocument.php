<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One version of one deliverable written by the team. It reaches the researcher only once the admin delivers it
 * (delivered_at). Rows and files are never overwritten: a correction is a new version.
 *
 * Rows of kind "ai_draft" are archived output from the retired AI-writing workflow: kept, never shown.
 */
class ProjectDocument extends Model
{
    public const DELIVERABLE = 'deliverable';

    /** Archived output of the retired AI-writing workflow. */
    public const AI_DRAFT = 'ai_draft';

    protected $fillable = [
        'project_id', 'stage', 'type', 'slot', 'label', 'kind', 'version', 'format', 'file_path', 'original_name',
        'file_size', 'sha256', 'extracted_text', 'extraction_error', 'extracted_at', 'created_by', 'delivered_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'stage' => 'integer',
            'version' => 'integer',
            'extracted_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeDeliverables(Builder $query): Builder
    {
        return $query->where('kind', self::DELIVERABLE);
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('kind', self::DELIVERABLE)->whereNotNull('delivered_at');
    }

    public function isDelivered(): bool
    {
        return $this->kind === self::DELIVERABLE && $this->delivered_at !== null;
    }

    /** "instrument:questionnaire" → the slot of an instrument kind; other types use the type value. */
    public static function slotFor(DocumentType $type, ?string $variant = null): string
    {
        return $type === DocumentType::Instrument ? 'instrument:'.Str::slug($variant ?: 'instrument', '_') : $type->value;
    }

    public function downloadName(): string
    {
        $project = $this->project;
        $base = Str::slug(Str::limit($project->title, 60, ''), '_') ?: 'project';

        return Str::slug($this->label, '_').'_'.$base.($this->version > 1 ? '_v'.$this->version : '').'.'.$this->format;
    }

    public function adminDownloadName(): string
    {
        return $this->project->reference().'_'.Str::slug($this->label, '_').'_v'.$this->version.'.'.$this->format;
    }

    public function contents(): string
    {
        return (string) Storage::disk(config('researchhub.disk'))->get($this->file_path);
    }

    public function sizeLabel(): string
    {
        return $this->file_size >= 1048576 ? round($this->file_size / 1048576, 1).' MB' : max(1, round($this->file_size / 1024)).' KB';
    }
}
