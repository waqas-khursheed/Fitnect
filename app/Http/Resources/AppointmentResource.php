<?php

namespace App\Http\Resources;

use App\Exceptions\CustomValidationException;
use App\Models\Availability;
use App\Models\Like;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                =>  $this->id,
            'type'              =>  $this->type,
            'date'              =>  $this->date,
            'start_time'        =>  $this->start_time,
            'end_time'          =>  $this->end_time,
            'fee'               =>  $this->fee,
            'platform_fee'      =>  $this->platform_fee,
            'merchant_fee'      =>  $this->merchant_fee,
            'profit'            =>  $this->profit,
            // 'status'            =>  $this->status,
            'payment_method'    =>  'Stripe', 
            'created_at'        =>  $this->created_at,
            'user'            => [
                'id'                    =>  $this->user->id,
                'first_name'            =>  $this->user->first_name,
                'last_name'             =>  $this->user->last_name,
                'profile_image'         =>  $this->user->profile_image,
                'user_type'             =>  $this->user->user_type
            ],
            'influencer'            => [
                'id'                    =>  $this->influencer->id,
                'first_name'            =>  $this->influencer->first_name,
                'last_name'             =>  $this->influencer->last_name,
                'profile_image'         =>  $this->influencer->profile_image,
                'user_type'             =>  $this->influencer->user_type
            ]
        ];
    }
}
