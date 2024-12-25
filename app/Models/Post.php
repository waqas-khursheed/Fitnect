<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'description', 'media', 'media_type', 'media_thumbnail'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->select('id', 'first_name', 'last_name', 'profile_image', 'user_type');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id')->with('user')->latest()->select(['id', 'user_id', 'post_id', 'comment', 'created_at'])->where('parent_id', null);
    }

    public function likes()
    {
        return $this->hasMany(Like::class, 'record_id')->where('like_type', 'post');
    }

    public function post_views()
    {
        return $this->hasMany(PostView::class, 'post_id');
    }
}
