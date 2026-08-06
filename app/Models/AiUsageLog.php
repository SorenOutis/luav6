<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    protected $fillable = [
        'date',
        'provider',
        'model',
        'source',
        'input_tokens',
        'output_tokens',
        'neurons',
    ];

    protected $casts = [
        'date' => 'date',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'neurons' => 'decimal:2',
    ];
}
