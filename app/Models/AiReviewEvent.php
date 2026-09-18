<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiReviewEvent extends Model
{
    use BelongsToWorkspace;

    public const UPDATED_AT = null;

    protected $fillable = [
        'workspace_id',
        'reviewable_type',
        'reviewable_id',
        'actor_id',
        'event',
        'version',
        'before_payload',
        'after_payload',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'before_payload' => 'array',
            'after_payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
