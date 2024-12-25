<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id', 'receiver_id', 'review', 'rating'
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id')->select('id', 'first_name', 'last_name', 'profile_image', 'user_type');
    }
}
