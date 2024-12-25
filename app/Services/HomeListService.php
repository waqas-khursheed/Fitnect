<?php

namespace App\Services;

use App\Models\ProfileView;
use App\Models\User;
use App\Services\BaseService;

class HomeListService extends BaseService {

    /** Local trainsers */
    public function local_trainers($offset = null)
    {
        return User::where('id', '<>', auth()->id())
        ->where('user_type', 'influencer')
        ->where('country', auth()->user()->country)
        ->where('state', auth()->user()->state)
        ->where('city', auth()->user()->city)
        ->profileCompleted()
        ->otpVerified()
        ->latest()
        ->when($offset != null, function($query) use ($offset) {
            $query->offset($offset);
        })
        ->limit(10)
        ->get();
    }

    /** Recent profile */
    public function recent_profile($offset = null)
    {
        // if(auth()->user()->user_type == 'user'){
        //     $userType = 'influencer';
        // } else {
        //     $userType = 'user';
        // }
        return User::where('id', '<>', auth()->id())
        ->whereIn('id', ProfileView::where('auth_id', auth()->id())->pluck('user_id'))
        ->profileCompleted()
        ->otpVerified()
        ->latest()
        ->when($offset != null, function($query) use ($offset) {
            $query->offset($offset);
        })
        ->limit(10)
        ->get();
    }

    /** Recommended */
    public function recommended($offset = null)
    {
        $usesInterest  = auth()->user()->user_interest->pluck('interest_id');

        return User::where('id', '<>', auth()->id())
        ->whereHas('user_interest', function ($query) use ($usesInterest) {
            $query->whereIn('interest_id', $usesInterest);
        })
        ->where('user_type', 'influencer')
        ->profileCompleted()
        ->otpVerified()
        ->latest()
        ->when($offset != null, function($query) use ($offset) {
            $query->offset($offset);
        })
        ->limit(10)
        ->get();
    }

}