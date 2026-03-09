<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    protected $fillable = ['name', 'description', 'points_cost'];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
