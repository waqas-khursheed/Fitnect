<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'record_id', 'like_type'
    ];
    

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->select('id', 'first_name', 'last_name', 'profile_image', 'user_type');
    }
}
