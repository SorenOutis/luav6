<?php

namespace App\Models;

use App\Ai\Support\BotPolicy;
use App\Ai\Support\ToolRegistry;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\AiBotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiBot extends Model
{
    /** @use HasFactory<AiBotFactory> */
    use BelongsToWorkspace;

    use HasFactory;

    public const KIND_COORDINATOR = 'coordinator';

    public const KIND_SPECIALIST = 'specialist';

    public const MEMORY_SESSION = 'session';

    public const MEMORY_BOT = 'bot';

    protected $fillable = [
        'workspace_id',
        'kind',
        'slug',
        'name',
        'title',
        'avatar',
        'instructions',
        'policy',
        'allowed_tools',
        'model',
        'max_steps',
        'memory_scope',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'policy' => 'array',
            'allowed_tools' => 'array',
            'is_active' => 'boolean',
            'max_steps' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AiBot $bot): void {
            $bot->kind = $bot->kind === self::KIND_COORDINATOR
                ? self::KIND_COORDINATOR
                : self::KIND_SPECIALIST;

            if (! $bot->slug) {
                $bot->slug = Str::slug((string) $bot->name) ?: 'bot';
            }

            $bot->slug = Str::slug($bot->slug) ?: 'bot';
            $bot->max_steps = max(1, min(BotPolicy::MAX_STEPS_HARD_CAP, (int) ($bot->max_steps ?: 8)));
            $bot->memory_scope = $bot->memory_scope === self::MEMORY_BOT
                ? self::MEMORY_BOT
                : self::MEMORY_SESSION;

            if ($bot->is_active === null) {
                $bot->is_active = true;
            }

            $rawPolicy = is_string($bot->policy)
                ? (json_decode($bot->policy, true) ?: [])
                : ($bot->policy ?? []);

            $bot->policy = BotPolicy::from(is_array($rawPolicy) ? $rawPolicy : [], $bot->kind)->toArray();
            $bot->allowed_tools = array_values(array_unique(array_filter(
                is_array($bot->allowed_tools) ? $bot->allowed_tools : [],
                fn (mixed $key): bool => is_string($key) && $key !== '',
            )));

            if ($bot->isCoordinator()) {
                $bot->allowed_tools = array_values(array_diff($bot->allowed_tools, ToolRegistry::writeKeys()));
            }

            $bot->assertSingleActiveCoordinator();
        });

        static::saved(function (AiBot $bot): void {
            if ($bot->wasRecentlyCreated || $bot->wasChanged(['policy', 'allowed_tools'])) {
                $bot->createPolicyRevision();
            }
        });
    }

    public function isCoordinator(): bool
    {
        return $this->kind === self::KIND_COORDINATOR;
    }

    public function isSpecialist(): bool
    {
        return ! $this->isCoordinator();
    }

    /** @return Builder<AiBot> */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return Builder<AiBot> */
    public function scopeCoordinators(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_COORDINATOR);
    }

    /** @return Builder<AiBot> */
    public function scopeSpecialists(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_SPECIALIST);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function policyRevisions(): HasMany
    {
        return $this->hasMany(AiBotPolicyRevision::class, 'ai_bot_id')->latest('version');
    }

    public function handoffsFrom(): HasMany
    {
        return $this->hasMany(AiBotHandoff::class, 'from_bot_id');
    }

    public function handoffsTo(): HasMany
    {
        return $this->hasMany(AiBotHandoff::class, 'to_bot_id');
    }

    public function routines(): HasMany
    {
        return $this->hasMany(AiBotRoutine::class, 'bot_id');
    }

    public function memories(): HasMany
    {
        return $this->hasMany(AiBotMemory::class, 'bot_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ChatSession::class, 'bot_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'bot_id');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class, 'bot_id');
    }

    public function policyObject(): BotPolicy
    {
        return BotPolicy::from($this->policy, $this->kind);
    }

    public function compiledInstructions(): string
    {
        $policy = $this->policyObject()->compile($this);

        if (trim((string) $this->instructions) !== '') {
            $policy .= "\n\nROLE NOTES\n".trim((string) $this->instructions);
        }

        return $policy;
    }

    /** @return list<string> */
    public function allowedActions(): array
    {
        return $this->policyObject()->allowedActions($this->allowed_tools);
    }

    /** @return list<string> */
    public function driftWarnings(): array
    {
        return $this->policyObject()->driftWarnings($this);
    }

    public function createPolicyRevision(?User $changedBy = null, ?string $summary = null): AiBotPolicyRevision
    {
        $changedBy ??= auth()->user();

        $version = (int) AiBotPolicyRevision::query()
            ->withoutGlobalScope('workspace')
            ->where('ai_bot_id', $this->id)
            ->max('version') + 1;

        return AiBotPolicyRevision::query()->create([
            'workspace_id' => $this->workspace_id,
            'ai_bot_id' => $this->id,
            'version' => $version,
            'policy' => $this->policy,
            'allowed_tools' => $this->allowed_tools,
            'changed_by' => $changedBy?->id,
            'change_summary' => $summary,
        ]);
    }

    /**
     * Only one active coordinator may exist per workspace. The coordinator is
     * the routing brain for group sessions, so two would make routing
     * ambiguous.
     */
    public function assertSingleActiveCoordinator(): void
    {
        if (! $this->isCoordinator() || ! $this->is_active) {
            return;
        }

        $exists = self::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $this->workspace_id)
            ->where('kind', self::KIND_COORDINATOR)
            ->where('is_active', true)
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'kind' => 'This workspace already has an active Chief of Staff. Deactivate it before activating another coordinator.',
            ]);
        }
    }

    public static function activeCoordinatorFor(?int $workspaceId): ?self
    {
        if (! $workspaceId) {
            return null;
        }

        return self::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->where('kind', self::KIND_COORDINATOR)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Durable facts safe to inject into this bot's prompt for the given actor.
     * Only importance 1-2 rows are retrieved in v1, capped at 20 rows / ~2,000
     * characters. Shared facts (`user_id` null) are visible to everyone; private
     * facts only to their owner.
     *
     * @return list<string>
     */
    public function memoryFactsFor(?User $actor): array
    {
        $facts = AiBotMemory::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $this->workspace_id)
            ->where('bot_id', $this->id)
            ->where('importance', '<=', 2)
            ->when($actor, fn (Builder $query) => $query->where(function (Builder $query) use ($actor): void {
                $query->whereNull('user_id')->orWhere('user_id', $actor->id);
            }), fn (Builder $query) => $query->whereNull('user_id'))
            ->orderByDesc('importance')
            ->orderByDesc('id')
            ->limit(20)
            ->pluck('content');

        $facts = $facts
            ->map(fn (mixed $fact): string => trim((string) $fact))
            ->filter()
            ->values();

        $injected = [];
        $characters = 0;
        foreach ($facts as $fact) {
            $length = strlen($fact);
            if ($characters + $length > 2000) {
                break;
            }
            $injected[] = $fact;
            $characters += $length;
        }

        return $injected;
    }
}
