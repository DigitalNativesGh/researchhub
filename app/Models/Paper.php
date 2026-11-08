<?php

namespace App\Models;

use App\Enums\PaperStatus;
use Database\Factories\PaperFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

/**
 * A file the researcher uploaded: a source paper, or their institution's guidelines or template. Papers are stored
 * and handed to the team as they are; nothing reads or summarises them. (title, authors, citation_key and the
 * other metadata columns were filled by the retired AI workflow and are kept for older projects.)
 */
class Paper extends Model
{
    /** @use HasFactory<PaperFactory> */
    use HasFactory;

    public const KIND_SOURCE = 'source';

    public const KIND_GUIDELINES = 'guidelines';

    /** Accepted upload formats (contents are checked too: see App\Support\FileSignature). */
    public const FORMATS = ['pdf', 'docx', 'doc', 'txt', 'rtf', 'odt'];

    protected $fillable = [
        'project_id', 'kind', 'original_name', 'file_path', 'file_size', 'sha256', 'parse_status', 'title',
    ];

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'year' => 'integer',
            'parse_status' => PaperStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function displayTitle(): string
    {
        return $this->title ?: $this->original_name;
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    public function isGuidelines(): bool
    {
        return $this->kind === self::KIND_GUIDELINES;
    }

    public function sizeForHumans(): string
    {
        return $this->file_size ? Number::fileSize($this->file_size, 1) : '';
    }
}
