<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChatSession extends Model
{
    protected static ?bool $hasUuidColumn = null;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'source',
    ];

    public static function hasUuidColumn(): bool
    {
        if (static::$hasUuidColumn === null) {
            static::$hasUuidColumn = Schema::hasTable('chat_sessions') && Schema::hasColumn('chat_sessions', 'uuid');
        }

        return static::$hasUuidColumn;
    }

    public static function flushUuidColumnCache(): void
    {
        static::$hasUuidColumn = null;
    }

    protected static function booted(): void
    {
        static::creating(function (ChatSession $session): void {
            if (static::hasUuidColumn() && ! $session->uuid) {
                $session->uuid = (string) Str::uuid7();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return static::hasUuidColumn() ? 'uuid' : 'id';
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

        if (is_numeric($value)) {
            return $query->where('id', (int) $value);
        }

        if (static::hasUuidColumn() && is_string($value) && Str::isUuid($value)) {
            return $query->where('uuid', $value);
        }

        return $query->whereRaw('1 = 0');
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
