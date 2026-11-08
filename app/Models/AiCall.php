<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCall extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'project_id', 'user_id', 'stage', 'label', 'prompt', 'model', 'input_tokens', 'output_tokens', 'cached_tokens',
        'cache_write_tokens', 'cost', 'duration_ms', 'status', 'error',
    ];

    protected function casts(): array
    {
        return ['cost' => 'decimal:6', 'created_at' => 'datetime'];
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
