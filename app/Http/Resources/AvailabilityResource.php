<?php

namespace App\Http\Resources;

use App\Exceptions\CustomValidationException;
use App\Models\Availability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailabilityResource extends JsonResource
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
        ->where('is_available', '1')
        // ->when($is_slot_check == 1, function($query) {
        //     $currentTime = now()->addHour(5)->format('H:i:s');
        //     $query->where('start_time', '<', $currentTime);
        // })
        ->select('start_time', 'end_time', 'fee')->get();
        // return AvailabilityResource::collection($avilability);
    }

}
