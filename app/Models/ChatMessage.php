<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'session_id',
        'bot_id',
        'role',
        'content',
        'thinking',
        'attachments',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class, 'bot_id');
    }
}
