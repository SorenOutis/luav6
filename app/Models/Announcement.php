<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['title', 'description', 'link', 'is_active', 'admin_id'];
}
