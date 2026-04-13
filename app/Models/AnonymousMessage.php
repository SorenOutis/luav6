<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnonymousMessage extends Model
{
    protected $fillable = ['user_id', 'content', 'likes_count', 'is_approved'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function likes()
    {
        return $this->hasMany(\Illuminate\Support\Facades\DB::table('anonymous_message_likes')->getNamespace() ? 'App\Models\AnonymousMessageLike' : 'AnonymousMessageLike', 'anonymous_message_id');
    }

    // Simplified for now, will use DB table directly in controller for speed
    public function likedByUsers()
    {
        return $this->belongsToMany(User::class, 'anonymous_message_likes');
    }
}
