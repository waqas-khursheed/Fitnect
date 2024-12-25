<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name', 
        'last_name', 
        'email',
        'user_type',
        'password',
        'gender', 
        'profile_image', 
        'cover_image', 
        'phone_number', 
        'date_of_birth',
        'website_link', 
        'interest', 
        'expertise', 
        'country', 
        'state', 
        'city', 
        'about',    
        'session',        
        'package_type',        
        'package_name',        
        'is_profile_complete',
        'device_type',
        'device_token',
        'social_type',
        'social_token',
        'is_forgot',
        'push_notification',
        'is_verified',
        'is_social',
        'verified_code',
        'is_active',
        'is_blocked',
        'customer_id',
        'account_id',
        'is_merchant_setup',
        'online_status'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function scopeOtpVerified($query)
    {
        return $query->where('is_verified', '1');
    }

    public function scopeProfileCompleted($query)
    {
        return $query->where('is_profile_complete', '1');
    }

    function user_interest()
    {
        return $this->hasMany(UserInterest::class, 'user_id')->with('interest');    
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'receiver_id');
    }

    protected function interest(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => json_decode($value),
        );
    }

    protected function expertise(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => json_decode($value),
        );
    }
}
