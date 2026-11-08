<?php

namespace App\Models;

use App\Enums\DatasetStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A data file the researcher collected and uploaded in Stage 2 (survey data, transcripts, or coded data). */
class Dataset extends Model
{
    public const KIND_QUANTITATIVE = 'quantitative';

    public const KIND_CODES = 'qualitative_codes';

    public const KIND_TRANSCRIPT = 'transcript';

    /** Formats researchers can upload, by kind. */
    public const TABULAR_FORMATS = ['csv', 'xlsx', 'sav', 'dta'];

    public const TRANSCRIPT_FORMATS = ['docx', 'pdf', 'txt'];

    protected $fillable = [
        'project_id', 'kind', 'original_name', 'file_path', 'file_size', 'row_count', 'validation_report', 'status',
        'format', 'submitted_at', 'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'validation_report' => 'array',
            'status' => DatasetStatus::class,
            'submitted_at' => 'datetime',
            'superseded_at' => 'datetime',
            'row_count' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    public function isReady(): bool
    {
        return in_array($this->status, [DatasetStatus::Valid, DatasetStatus::ValidWithWarnings, DatasetStatus::Accepted], true);
    }

    public function sizeForHumans(): string
    {
        return $this->file_size >= 1048576 ? number_format($this->file_size / 1048576, 1).' MB' : max(1, round($this->file_size / 1024)).' KB';
    }
}
