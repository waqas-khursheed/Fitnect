<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Availability;

class UserAvailibilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $is_slot_check = 0;
        if(isset($this->is_slot_check) && $this->is_slot_check == 1){
            $is_slot_check = 1;
        }
        return [
            'day'            =>  $this->day,
            'is_available'   =>  $this->is_available,
            'slots'          =>  $this->slots($this->day, $this->user_id, $is_slot_check)
        ];
    }
    
    private function slots($day, $userId, $is_slot_check)
    {
        return Availability::
        where('user_id', $userId)
        ->where('day', $day)
        ->select('start_time', 'end_time', 'fee')->get();
    }
}
