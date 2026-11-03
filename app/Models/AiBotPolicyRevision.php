<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\AiBotPolicyRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBotPolicyRevision extends Model
{
    /** @use HasFactory<AiBotPolicyRevisionFactory> */
    use BelongsToWorkspace;

    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'ai_bot_id',
        'version',
        'policy',
        'allowed_tools',
        'changed_by',
        'change_summary',
    ];

    protected function casts(): array
    {
        return [
            'policy' => 'array',
            'allowed_tools' => 'array',
            'version' => 'integer',
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class, 'ai_bot_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
