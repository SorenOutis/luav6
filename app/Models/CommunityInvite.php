<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CommunityInvite extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'token',
        'invited_by',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'immutable_datetime',
        ];
    }

    public static function generateToken(): string
    {
        return Str::random(40);
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function regenerateToken(bool $resetAccepted = false): string
    {
        $newToken = static::generateToken();

        $attributes = ['token' => $newToken];
        if ($resetAccepted) {
            $attributes['accepted_at'] = null;
        }

        $this->update($attributes);

        return $newToken;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
