<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\AiBotMemoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBotMemory extends Model
{
    /** @use HasFactory<AiBotMemoryFactory> */
    use BelongsToWorkspace;

    use HasFactory;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_APPROVED = 'approved';

    protected $fillable = [
        'workspace_id',
        'bot_id',
        'user_id',
        'content',
        'source',
        'importance',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'importance' => 'integer',
        ];
    }

    /** @return Builder<AiBotMemory> */
    public function scopeVisibleTo(Builder $query, ?User $actor): Builder
    {
        return $query->when($actor, fn (Builder $query) => $query->where(function (Builder $query) use ($actor): void {
            $query->whereNull('user_id')->orWhere('user_id', $actor->id);
        }), fn (Builder $query) => $query->whereNull('user_id'));
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class, 'bot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
