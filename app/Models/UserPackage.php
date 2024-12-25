<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPackage extends Model
{
    use HasFactory;

    protected $table = "user_packages";

    protected $fillable = ['user_id', 'package_id', 'package_name', 'package_type', 'session', 'amount', 'subscribed_at', 'expires_at', 'is_active', 'json'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
