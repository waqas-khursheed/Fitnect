<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceCallUser extends Model
{
    use HasFactory;


    protected $table = "conference_call_users";

    protected $fillable = [
        'conference_call_id', 'user_id', 'token'
    ];
}
