<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiQuestionDraft extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'user_id',
        'title',
        'source_filename',
        'source_text',
        'topic',
        'type_counts',
        'difficulty',
        'status',
        'questions',
        'last_error',
        'generated_at',
        'admin_id',
    ];

    protected $casts = [
        'type_counts' => 'array',
        'questions' => 'array',
        'generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
