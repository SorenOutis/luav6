<?php

namespace App\Models;

use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChatSession extends Model
{
    public const MODE_SOLO = 'solo';

    public const MODE_GROUP = 'group';

    public const MAX_GROUP_SPECIALISTS = 6;

    protected static ?bool $hasUuidColumn = null;

    protected $fillable = [
        'uuid',
        'user_id',
        'bot_id',
        'mode',
        'participant_bot_ids',
        'title',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'participant_bot_ids' => 'array',
        ];
    }

    public static function hasUuidColumn(): bool
    {
        if (static::$hasUuidColumn === null) {
            try {
                static::$hasUuidColumn = Schema::hasTable('chat_sessions') && Schema::hasColumn('chat_sessions', 'uuid');
            } catch (\Throwable) {
                static::$hasUuidColumn = true;
            }
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

            if (! $session->mode) {
                $session->mode = self::MODE_SOLO;
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

        if (is_numeric($value)) {
            return $query->where('id', (int) $value);
        }

        if (static::hasUuidColumn() && is_string($value) && Str::isUuid($value)) {
            return $query->where('uuid', $value);
        }

        return $query->whereRaw('1 = 0');
    }

    public function isGroup(): bool
    {
        return $this->mode === self::MODE_GROUP;
    }

    public function isBotSession(): bool
    {
        return $this->bot_id !== null || $this->isGroup();
    }

    /** @return Builder<ChatSession> */
    public function scopeBotSessions(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNotNull('bot_id')->orWhere('mode', self::MODE_GROUP);
        });
    }

    /**
     * Restrict to sessions whose solo bot or team participants are in the list.
     *
     * @param  array<int, int>  $botIds
     * @return Builder<ChatSession>
     */
    public function scopeForBotIds(Builder $query, array $botIds): Builder
    {
        $botIds = array_values(array_unique(array_filter(
            array_map('intval', $botIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($botIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($botIds): void {
            $query
                ->whereIn('bot_id', $botIds)
                ->orWhere(function (Builder $query) use ($botIds): void {
                    $query
                        ->where('mode', self::MODE_GROUP)
                        ->where(function (Builder $query) use ($botIds): void {
                            foreach ($botIds as $botId) {
                                $query->orWhereJsonContains('participant_bot_ids', $botId);
                            }
                        });
                });
        });
    }

    /** @return Builder<ChatSession> */
    public function scopeForBot(Builder $query, int $botId): Builder
    {
        return $query->where(function (Builder $query) use ($botId): void {
            $query
                ->where('bot_id', $botId)
                ->orWhere(function (Builder $query) use ($botId): void {
                    $query
                        ->where('mode', self::MODE_GROUP)
                        ->whereJsonContains('participant_bot_ids', $botId);
                });
        });
    }

    public function workspaceId(): ?int
    {
        if ($this->bot_id) {
            return $this->bot?->workspace_id;
        }

        if ($this->isGroup()) {
            $workspaceId = $this->rosterBots()->value('workspace_id');
            if ($workspaceId) {
                return (int) $workspaceId;
            }
        }

        return $this->user?->current_workspace_id
            ?: app(WorkspaceContext::class)->id();
    }

    public function activeCoordinator(): ?AiBot
    {
        return AiBot::activeCoordinatorFor($this->workspaceId());
    }

    /** @return Builder<AiBot> */
    public function rosterBots(): Builder
    {
        $ids = array_values(array_filter(
            array_map('intval', (array) $this->participant_bot_ids),
            fn (int $id): bool => $id > 0,
        ));

        return AiBot::query()
            ->whereIn('id', $ids)
            ->orderBy('name');
    }

    public function respondingBotId(): ?int
    {
        return $this->bot_id ?: $this->activeCoordinator()?->id;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class, 'bot_id');
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
