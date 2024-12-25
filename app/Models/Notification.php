<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id', 'receiver_id', 'title', 'description', 'record_id', 'type', 'token', 'room_id', 'seen', 'read_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachment()
    {
        return $this->hasOne(Attachment::class, 'record_id', 'record_id')->where('type', 'receipt_pay');
    }
}
