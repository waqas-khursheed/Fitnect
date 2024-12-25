<?php

namespace App\Http\Resources;

use App\Exceptions\CustomValidationException;
use App\Models\Availability;
use App\Models\Favourite;
use App\Models\Follow;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if($this->user_type == 'user'){
            return $this->user($this);
        } else if($this->user_type == 'influencer') {
            return $this->influencer($this);
        } else {
            throw new CustomValidationException('User not found.');
        }
    }

    private function user($data)
    {
        return [
            'id'                        =>   $data->id,
            'first_name'                =>   $data->first_name,                
            'last_name'                 =>   $data->last_name,                 
            'email'                     =>   $data->email,                
            'user_type'                 =>   $data->user_type,               
            'gender'                    =>   $data->gender,                
            'profile_image'             =>   $data->profile_image,                 
            'cover_image'               =>   $data->cover_image,               
            'phone_number'              =>   $data->phone_number,              
            'date_of_birth'             =>   $data->date_of_birth,             
            'interest'                  =>   $data->interest,                  
            'country'                   =>   $data->country,               
            'state'                     =>   $data->state,                 
            'city'                      =>   $data->city,              
            'about'                     =>   $data->about,                        
            'is_profile_complete'       =>   $data->is_profile_complete,              
            'device_type'               =>   $data->device_type,              
            'device_token'              =>   $data->device_token,                
            'is_forgot'                 =>   $data->is_forgot,                
            'push_notification'         =>   $data->push_notification,                
            'is_verified'               =>   $data->is_verified,              
            'is_social'                 =>   $data->is_social,                      
            'is_active'                 =>   $data->is_active,                
            'is_blocked'                =>   $data->is_blocked,
            'interest'                  =>   UserInterestResource::collection($this->user_interest),
            'followers_count'           =>   Follow::where('following_id', $data->id)->where('status', 'accept')->count(),
            'following_count'           =>   Follow::where('follower_id', $data->id)->where('status', 'accept')->count(),
            'videos_count'              =>   Post::where('user_id', $data->id)->count(),
            'connections_count'         =>   0,
            'is_follow'                 =>   Follow::where(['follower_id' => auth()->id(), 'following_id' => $data->id])->count(),
            'is_favourite'              =>   Favourite::where(['user_id' => auth()->id(), 'record_id' => $data->id, 'type' => 'user'])->count(),
            'avg_rating'                =>   $data->reviews()->avg('rating')
        ];
    }

    private function influencer($data)
    {
        return [
            'id'                        =>   $data->id,
            'first_name'                =>   $data->first_name,                
            'last_name'                 =>   $data->last_name,                 
            'email'                     =>   $data->email,                
            'user_type'                 =>   $data->user_type,               
            'gender'                    =>   $data->gender,                
            'profile_image'             =>   $data->profile_image,                 
            'cover_image'               =>   $data->cover_image,               
            'phone_number'              =>   $data->phone_number,                
            'website_link'              =>   $data->website_link,          
            'expertise'                 =>   UserInterestResource::collection($this->user_interest), // $data->expertise,                 
            'country'                   =>   $data->country,               
            'state'                     =>   $data->state,                 
            'city'                      =>   $data->city,              
            'about'                     =>   $data->about, 
            'session'                     =>   $data->session,  
            'package_type'                     =>   $data->package_type,                        
            'package_name'                     =>   $data->package_name,                        
            'is_profile_complete'       =>   $data->is_profile_complete,              
            'device_type'               =>   $data->device_type,              
            'device_token'              =>   $data->device_token,                
            'is_forgot'                 =>   $data->is_forgot,                
            'push_notification'         =>   $data->push_notification,                
            'is_verified'               =>   $data->is_verified,              
            'is_social'                 =>   $data->is_social,                      
            'is_active'                 =>   $data->is_active,                
            'is_blocked'                =>   $data->is_blocked,
            'avilability'               =>   $this->avilability($this->id),
            // 'interest'                  =>   UserInterestResource::collection($this->user_interest),
            'followers_count'           =>   Follow::where('following_id', $data->id)->where('status', 'accept')->count(),
            'following_count'           =>   Follow::where('follower_id', $data->id)->where('status', 'accept')->count(),
            'videos_count'              =>   Post::where('user_id', $data->id)->count(),
            'connections_count'         =>   0,
            'is_follow'                 =>   Follow::where(['follower_id' => auth()->id(), 'following_id' => $data->id])->count(),
            'is_favourite'              =>   Favourite::where(['user_id' => auth()->id(), 'record_id' => $data->id, 'type' => 'user'])->count(),
            'avg_rating'                =>   $data->reviews()->avg('rating'),
            'is_merchant_setup'         =>   $data->is_merchant_setup
        ];
    }

    private function avilability($userId)
    {
        $avilability = Availability::where('user_id', $userId)->select('id', 'user_id', 'day', 'is_available', 'start_time', 'end_time', 'fee')->groupBy('day')
        ->orderByRaw("FIELD(day, 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')")->get();
        return UserAvailibilityResource::collection($avilability);
    }
}
