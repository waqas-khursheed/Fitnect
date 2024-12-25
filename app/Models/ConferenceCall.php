<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceCall extends Model
{
    use HasFactory;

    protected $table = "conference_calls";

    protected $fillable = [
       'appointment_id','user_id', 'title', 'ended_at', 'room_id', 'status'
    ];
}
