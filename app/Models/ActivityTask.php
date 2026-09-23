<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityTask extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'admin_id',
        'section_id',
        'title',
        'term',
        'task_type',
        'max_points',
        'description',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'max_points' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ActivityTaskScore::class);
    }

    public function scoreForStudent(int $userId): ?ActivityTaskScore
    {
        return $this->scores->firstWhere('user_id', $userId);
    }
}
