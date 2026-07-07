<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['name', 'description', 'points_cost', 'admin_id'];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
