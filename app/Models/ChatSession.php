<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatSession extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'source',
    ];

    protected static function booted(): void
    {
        static::creating(function (ChatSession $session): void {
            if (! $session->uuid) {
                $session->uuid = (string) Str::uuid7();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Retrieve the model for a bound value.
     * Supports resolution by both UUID string and legacy integer ID.
     *
     * @param  Builder  $query
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        if ($field) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        if (is_string($value) && Str::isUuid($value)) {
            return $query->where('uuid', $value);
        }

        if (is_numeric($value)) {
            return $query->where(function (Builder $subQuery) use ($value): void {
                $subQuery->where('id', $value)->orWhere('uuid', $value);
            });
        }

        return $query->where('uuid', $value);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'session_id')->orderBy('id');
    }

    public function pendingAiActions(): HasMany
    {
        return $this->hasMany(PendingAiAction::class);
    }
}
