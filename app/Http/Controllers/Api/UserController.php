<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Appointment;
use App\Models\Availability;
use App\Models\Follow;
use App\Models\ProfileView;
use App\Models\User;
use App\Models\UserInterest;
use App\Models\UserPackage;
use App\Rules\AvailabilityRule;
use App\Services\HomeListService;
use App\Services\PaymentService;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class UserController extends Controller
{
    use ApiResponser;

    /** Profile */
    public function profile(Request $request)
    {
        $this->validate($request, [
            'user_id'   =>  'required|exists:users,id',
        ]);

        if (auth()->id() != $request->user_id) {
            ProfileView::where(['auth_id' => auth()->id(), 'user_id' => $request->user_id])->delete();
            ProfileView::create(['auth_id' => auth()->id(), 'user_id' => $request->user_id]);
        }

        return $this->successDataResponse('Profile.', new UserResource(User::find($request->user_id)));
    }

    /** Availability */
    public function availability(Request $request, PaymentService $paymentService)
    {
        // $validator = Validator::make($request->all(), [
        //     'availability.*.slots.*.fee' => ['required', new AvailabilityRule],
        // ], [
        //     'availability.*.slots.*.fee.required' => 'The fee is required for each slot.',
        // ]);

        // if ($validator->fails()) {
        //     return response()->json([
        //         'status' => 0,
        //         'message' => $validator->errors()->first(),
        //     ], 400);
        // }

        try {
            DB::beginTransaction();

            if (auth()->user()->is_merchant_setup == '0') {
                $accountsRetrieve = $paymentService->accountsRetrieve(auth()->user()->account_id);
                if ($accountsRetrieve->capabilities->transfers == 'inactive') {
                    $accountLink = $paymentService->accountLink(auth()->user()->account_id);
                    $url = $accountLink->url;
                    return $this->errorDataResponse('Please setup your account.', ['url' => $url], 400);
                } else {
                    User::whereId(auth()->id())->update(['is_merchant_setup' => '1']);
                }
            }

            $data = array();

            $appointments = Appointment::where('influencer_id', auth()->id())
                ->where('status', '!=', 'cancel')
                ->where('date', '>=', date('Y-m-d'))
                ->select(
                    DB::raw("DAYNAME(date) AS day_name")
                )
                ->pluck('day_name')->toArray();

            foreach ($request->availability as $availability) {
                for ($i = 0; $i < count($availability['slots']); $i++) {

                    if ($availability['is_available'] == 1) {
                        if ($availability['slots'][$i]['fee'] == null) {
                            return $this->errorResponse('The fee is required for each slot.', 400);
                        } else if ($availability['slots'][$i]['fee'] == 0) {
                            return $this->errorResponse('The fee must be greater than 0.', 400);
                        }
                    }

                    // Checking if day has same time multiple
                    $dayName = ucfirst($availability['day']);
                    if ($this->hasDuplicateSlots($availability['slots'])) {
                        return $this->errorResponse("Invalid time slot for {$dayName}", 400);
                    }

                    // Check for existing appointments on the same day and time
                    if ($this->hasAppointment($dayName, $appointments) == true) {
                        $existsAvailability = Availability::where('user_id', auth()->id())->where('day', $dayName)->where('is_available', '1')->get();
                        foreach ($existsAvailability as $existsAvailability) {
                            array_push($data, [
                                'day' => $existsAvailability->day,
                                'user_id' => auth()->id(),
                                'is_available' => $existsAvailability->is_available,
                                'start_time' => $existsAvailability->start_time,
                                'end_time' => $existsAvailability->end_time,
                                'fee' => $existsAvailability->fee,
                                'created_at' => $existsAvailability->created_at,
                                'updated_at' => $existsAvailability->updated_at
                            ]);
                        }
                    } else if ($this->hasAppointment($dayName, $appointments) == false) {
                        array_push($data, [
                            'day' => ucfirst($availability['day']),
                            'user_id' => auth()->id(),
                            'is_available' => $availability['is_available'],
                            'start_time' => $availability['slots'][$i]['start_time'],
                            'end_time' => $availability['slots'][$i]['end_time'],
                            'fee' => $availability['slots'][$i]['fee'],
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }

                    // $save = new Availability();
                    // $save->day = ucfirst($availability['day']);
                    // $save->user_id = auth()->id();
                    // $save->is_available = $availability['is_available'];
                    // $save->start_time = $availability['slots'][$i]['start_time'];
                    // $save->end_time = $availability['slots'][$i]['end_time'];
                    // $save->fee = $availability['slots'][$i]['fee'];
                    // $save->save();
                }
            }

            Availability::where('user_id', auth()->id())->delete();
            Availability::insert($data);

            DB::commit();
            return $this->successDataResponse('Availability saved successfully.', new UserResource(User::find(auth()->id())));
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    function hasAppointment($day, $existingAppointments)
    {
        return in_array($day, $existingAppointments);
    }

    private function hasDuplicateSlots($slots)
    {
        $slotTimes = array();
        foreach ($slots as $slot) {
            $time = $slot['start_time'] . '-' . $slot['end_time'];
            if (in_array($time, $slotTimes)) {
                return true;
            } else {
                $slotTimes[] = $time;
            }
        }
        return false;
    }

    /** Search */
    public function search(Request $request)
    {
        // $this->validate($request, [
        //     'search_text'   =>  'required',
        // ]);

        $users = User::where('id', '!=', auth()->id())->profileCompleted()->otpVerified()
            ->when($request->has('search_text'), function ($query) use ($request) {
                $query->where(DB::raw("concat(first_name, ' ', last_name)"), 'LIKE', "%" . $request->search_text . "%");
            })
            ->when($request->has('country'), function ($query) use ($request) {
                $query->where('country', $request->country);
            })
            ->when($request->has('state'), function ($query) use ($request) {
                $query->where('state', $request->state);
            })
            ->when($request->has('city'), function ($query) use ($request) {
                $query->where('city', $request->city);
            })
            ->latest()->get();

        if (count($users) > 0) {
            return $this->successDataResponse('Data found.', UserResource::collection($users));
        } else {
            return $this->errorResponse('Data not found.', 400);
        }
    }

    /** Home list */
    public function homeList(HomeListService $homeListService)
    {
        $users = User::where('id', '<>', auth()->id())->where('user_type', 'influencer')->profileCompleted()->otpVerified()->latest()->get();

        if (auth()->user()->user_type == 'user') {
            // $userType = 'influencer';
            $data['local_trainers'] = UserResource::collection($homeListService->local_trainers());
        } else {
            // $userType = 'user';
        }

        // $data['recent_profile'] = UserResource::collection(User::where('id', '<>', auth()->id())->where('user_type', $userType)->profileCompleted()->otpVerified()->latest()->get());
        $data['recent_profile'] = UserResource::collection($homeListService->recent_profile());
        $data['recommended']    = UserResource::collection($homeListService->recommended());
        $data['papular']        = UserResource::collection($users);

        return $this->successDataResponse('Home list.', $data);
    }

    /** Home list all */
    public function homeListAll(Request $request, HomeListService $homeListService)
    {
        $this->validate($request, [
            'type'     =>  'required|in:recent_profile,local_trainers,recommended,papular',
            'offset'   =>  'required|numeric'
        ]);
        $type = $request->type;

        if ($type == 'recent_profile') {
            $users = $homeListService->recent_profile($request->offset);
        } else if ($type == 'local_trainers') {
            $users = $homeListService->local_trainers($request->offset);
        } else if ($type == 'recommended') {
            $users = $homeListService->recommended($request->offset);
        } else if ($type == 'papular') {
            $users = User::where('id', '<>', auth()->id())->where('user_type', 'influencer')->profileCompleted()->otpVerified()->latest()->get();
        }

        return $this->successDataResponse('Home list.', UserResource::collection($users));
    }

    /** Follow create */
    public function followCreate(Request $request)
    {
        $this->validate($request, [
            'user_id' => 'required|exists:users,id'
        ]);

        try {
            DB::beginTransaction();
            $authId = auth()->user()->id;

            if ($authId == $request->user_id) {
                return $this->errorResponse("Can't follow your account", 400);
            }

            $already_followed = Follow::where('follower_id', $authId)->where('following_id', $request->user_id)->where('status', 'accept')->first();

            if (!empty($already_followed)) {
                $already_followed->delete();
                DB::commit();
                return $this->successResponse('Unfollow successfully.');
            } else {

                $follow = new Follow;
                $follow->follower_id = $authId;
                $follow->following_id = $request->user_id;
                $follow->save();

                $user = User::whereId($request->user_id)->first();

                // Notification 
                $notification = [
                    'device_token'  => $user->device_token,
                    'sender_id'     => $authId,
                    'receiver_id'   => $user->id,
                    'title'         => auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has follow you.',
                    'description'   => auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has followed you.',
                    'record_id'     => $follow->id,
                    'type'          => 'follow_you',
                    'created_at'    => now(),
                    'updated_at'    => now()
                ];

                if ($user->push_notification == '1' && $user->device_token != null) {
                    push_notification($notification);
                }
                in_app_notification($notification);
                // End Notification 
                DB::commit();
                return $this->successResponse('Follow successfully.');
            }
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** Follow list */
    public function following(Request $request)
    {
        $this->validate($request, [
            'user_id' => 'required|exists:users,id'
        ]);

        $following_ids = Follow::where('follower_id', $request->user_id)->where('status', 'accept')->pluck('following_id');

        if (count($following_ids) > 0) {

            $users = User::whereIn('users.id', $following_ids)
                ->select(
                    'id',
                    'first_name',
                    'last_name',
                    'profile_image',
                    'user_type',
                    DB::raw('(select count(id) from `follows` where (`follower_id` = ' . auth()->id() . ' and `following_id` = users.id) and `status` = "accept") as is_follow')
                )
                ->orderByRaw('first_name')
                ->get();

            return $this->successDataResponse('Following list found.', $users);
        } else {
            return $this->errorResponse('Following list not found.', 400);
        }
    }

    /** Followers list */
    public function followers(Request $request)
    {
        $this->validate($request, [
            'user_id' => 'required|exists:users,id'
        ]);

        $follower_ids = Follow::where('following_id', $request->user_id)->where('status', 'accept')->pluck('follower_id');
        if (count($follower_ids) > 0) {

            $users = User::whereIn('id', $follower_ids)
                ->select(
                    'id',
                    'first_name',
                    'last_name',
                    'profile_image',
                    'user_type',
                    DB::raw('(select count(id) from `follows` where (`follower_id` = ' . auth()->id() . ' and `following_id` = users.id) and `status` = "accept") as is_follow')
                )
                ->orderByRaw('first_name')
                ->get();

            return $this->successDataResponse('Follower list found.', $users);
        } else {
            return $this->errorResponse('Follower list not found.', 400);
        }
    }

    // Create Subscription
    public function createSubscription(Request $request)
    {

        $this->validate($request, [
            'package_id'   =>  'required',
            'package_name'   =>  'required|in:plus,pro,premium',
            'package_type'   =>  'required|in:monthly,yearly',
            'session'   =>  'required',
            'amount'   =>  'required',
            'json'   =>  'required',
        ]);

        $authId = auth()->user()->id;

        DB::beginTransaction();
        try {
            $user = User::whereId($authId)->first();

            $user->session += $request->session;
            $user->package_type = $request->package_type;
            $user->package_name = $request->package_name;
            $user->save();

            $currentDateTime = Carbon::now();
            if ($request->package_type == 'monthly') {
                $expires_at = $currentDateTime->addMonth();
            } else {
                $expires_at = $currentDateTime->addYear();
            }

            $jsonString = json_encode($request->json);

            $subscription = UserPackage::create([
                'user_id' => $authId,
                'package_id' => $request->package_id,
                'package_name' => $request->package_name,
                'package_type' => $request->package_type,
                'session' => $request->session,
                'amount' => $request->amount,
                'subscribed_at' => now(),
                'expires_at' => $expires_at,
                'json' => $jsonString,
            ]);

            $userResource = new UserResource($user);
            DB::commit();
            return $this->successDataResponse('Subscription created successfully.', $userResource);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}


// {
//     "monday": [
//         {
//             "start_time": "10:30:00",
//             "end_time": "13:00:00"
//         }
//     ],
//     "tuesday": [
//         {
//             "start_time": "10:30:00",
//             "end_time": "13:00:00"
//         }
//     ],
//     "wednesday": [
//         {
//             "start_time": "10:30:00",
//             "end_time": "13:00:00"
//         }
//     ],
//     "thursday": [
//         {
//             "start_time": "10:30:00",
//             "end_time": "13:00:00"
//         }
//     ],
//     "friday": [
//         {
//             "start_time": "10:30:00",
//             "end_time": "13:00:00"
//         }
//     ],
//     "saturday": [
//         {
//             "start_time": "10:30:00",
//             "end_time": "13:00:00"
//         }
//     ],
//     "sunday": [
//         {
//             "start_time": "10:30:00",
//             "end_time": "13:00:00"
//         }
//     ]
// }
