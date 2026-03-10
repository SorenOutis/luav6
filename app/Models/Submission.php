<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $table = 'assignment_user';

    protected $fillable = [
        'user_id',
        'assignment_id',
        'submitted',
        'status',
        'grade',
        'file_path',
        'submitted_at',
    ];

    protected $casts = [
        'submitted' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }
}
