<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'influencer_id', 'type', 'date', 'start_time', 'end_time', 'fee', 'platform_fee', 'merchant_fee', 'profit', 'strip_charge_id', 'strip_refund_id', 'status', 'is_reminder'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->select('id', 'first_name', 'last_name', 'profile_image', 'user_type');
    }

    public function influencer()
    {
        return $this->belongsTo(User::class, 'influencer_id')->select('id', 'first_name', 'last_name', 'profile_image', 'user_type');
    }
}
