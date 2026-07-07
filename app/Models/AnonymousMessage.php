<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AnonymousMessage extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['user_id', 'content', 'likes_count', 'is_approved', 'admin_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function likes()
    {
        return $this->hasMany(DB::table('anonymous_message_likes')->getNamespace() ? 'App\Models\AnonymousMessageLike' : 'AnonymousMessageLike', 'anonymous_message_id');
    }

    // Simplified for now, will use DB table directly in controller for speed
    public function likedByUsers()
    {
        return $this->belongsToMany(User::class, 'anonymous_message_likes');
    }
}
