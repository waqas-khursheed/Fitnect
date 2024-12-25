<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\AvailabilityResource;
use App\Models\Appointment;
use App\Models\Availability;
use App\Models\User;
use App\Models\Notification;
use App\Models\ConferenceCall;
use App\Services\PaymentService;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    use ApiResponser;

    /** My appointment */
    public function myAppointment(Request $request)
    {
        $this->validate($request, [
            'list_type'     =>     'required|in:upcoming,past,cancel'
        ]);

        try {
            $appointments = Appointment::with('user', 'influencer')->latest()
                ->when($request->list_type == 'cancel', function ($query) {
                    $query->where('status', 'cancel');
                })
                ->when($request->list_type == 'upcoming', function ($query) {
                    $query->where('date', '>=', date('Y-m-d'))->where('status', '!=', 'cancel');
                })
                ->when($request->list_type == 'past', function ($query) {
                    $query->where('date', '<', date('Y-m-d'))->where('status', '!=', 'cancel');
                })
                ->when(auth()->user()->user_type == 'user', function ($query) {
                    $query->where('user_id', auth()->id());
                })
                ->when(auth()->user()->user_type == 'influencer', function ($query) {
                    $query->where('influencer_id', auth()->id());
                })->get();

            if (count($appointments) > 0) {
                $data = AppointmentResource::collection($appointments);
                return $this->successDataResponse('Appointments found successfully.', $data, 200);
            } else {
                return $this->errorResponse('No appointments found.', 400);
            }
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** Detail appointment */
    public function detailAppointment(Request $request)
    {
        $this->validate($request, [
            'appointment_id'      =>   'required|exists:appointments,id'
        ]);

        try {
            $appointment = Appointment::with('user', 'influencer')
                ->whereId($request->appointment_id)
                ->first();

            $data = new AppointmentResource($appointment);
            return $this->successDataResponse('Appointment found successfully.', $data, 200);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** Checking available appointment slots */
    public function availableSlots(Request $request)
    {
        $this->validate($request, [
            'influencer_id'      =>   'required|exists:users,id',
            'date'               =>   'required|date_format:Y-m-d|after_or_equal:today'
        ]);

        try {
            $day = Carbon::parse($request->date)->format('l');

            $availability = Availability::where([
                'is_available'  => '1',
                'user_id'       => $request->influencer_id,
                'day'           => $day
            ])
                ->selectRaw('*, "1" as is_slot_check')
                ->groupBy('day')->get();

            if (count($availability) == 0) {
                return $this->errorResponse('Appointment slots not available.', 400);
            } else {
                $data = AvailabilityResource::collection($availability);
                return $this->successDataResponse('Slots.', $data, 200);
            }
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /** Book appointment */
    public function bookAppointment(Request $request, PaymentService $paymentService)
    {
        $this->validate($request, [
            'influencer_id'      =>   'required|exists:users,id',
            'type'               =>   'required|in:local_meetup,live_session',
            'date'               =>   'required|date_format:Y-m-d|after_or_equal:today',
            'start_time'         =>   'required|date_format:H:i:s',
            'end_time'           =>   'required|date_format:H:i:s',
            'fee'                =>   'required|numeric',
            'card_id'            =>   'required'
        ]);

        try {
            DB::beginTransaction();

            $authUser = auth()->user();
            $authId = $authUser->id;

            // Check if i booked Appointment
            $appointmentBookedByMe = Appointment::where([
                'date'       => $request->date,
                'start_time' => $request->start_time,
                'end_time'   => $request->end_time,
                'user_id'    => auth()->id()
            ])->where('status', '!=', 'cancel')->exists();

            if ($appointmentBookedByMe) {
                return $this->errorResponse('You already booked this appointment slot.', 400);
            }

            // Check if other booked Appointment
            $appointmentBookedByMe = Appointment::where([
                'date'       => $request->date,
                'start_time' => $request->start_time,
                'end_time'   => $request->end_time
            ])->where('status', '!=', 'cancel')->exists();

            if ($appointmentBookedByMe) {
                return $this->errorResponse('This appointment slot already has been booked.', 400);
            }

            // Fee calculation
            $fee = $request->fee;
            $platformFeePercent = env('PLATFORM_FEE');
            $merchantFeePercent = env('MERCHANT_FEE');
            // $profitPercent = env('PROFIT');

            $platformFee = ($platformFeePercent / 100) * $fee; // Deduct platform fee
            $merchantFee = ($merchantFeePercent / 100) * $fee; // Deduct merchant fee
            $profit = $fee - (ceil($platformFee) + ceil($merchantFee)); // Profit

            // Payment
            $influencer = User::find($request->influencer_id);
            $description = 'Appointment fee';
            $charge   = $paymentService->chargeAmount($request->card_id, auth()->user()->customer_id, $fee, $description);
            $trasnfer = $paymentService->transfers($profit, $influencer->account_id, $description);

            $appointment =  Appointment::create($request->only(
                'influencer_id',
                'type',
                'date',
                'start_time',
                'end_time',
                'fee'
            ) + ['user_id' => auth()->id(), 'platform_fee' => ceil($platformFee), 'merchant_fee' => ceil($merchantFee), 'profit' => $profit, 'strip_charge_id' => $charge->id]);
            // $appointment =  Appointment::create($request->only(
            //     'influencer_id',
            //     'type',
            //     'date',
            //     'start_time',
            //     'end_time',
            //     'fee'
            // ) + ['user_id' => auth()->id(), 'platform_fee' => ceil($platformFee), 'merchant_fee' => ceil($merchantFee), 'profit' => $profit]);

            // Noltification
            $notification = [
                'device_token'  => $influencer->device_token,
                'sender_id'     => $authId,
                'receiver_id'   => $influencer->id,
                'title'         => $authUser->first_name . ' ' . $authUser->last_name . ' has send a appointment request.',
                'description'   => 'An Appointment request has been send by ' . $authUser->first_name . ' ' . $authUser->last_name,
                'record_id'     => $appointment->id,
                'type'          => 'appointment_request',
                'created_at'    => now(),
                'updated_at'    => now()
            ];

            if ($influencer->push_notification == '1' && $influencer->device_token != null) {
                push_notification($notification);
            }
            in_app_notification($notification);



            // // Session minus
            // $influencerUser = User::find($request->influencer_id);
            // $influencerUser->session -= 1; // Decrease session count
            // $influencerUser->save();

            // // Prepare notification data
            // $notificationData = [
            //     'device_token'  => $influencerUser->device_token,
            //     'sender_id'     => 1,
            //     'receiver_id'   => $influencerUser->id,
            //     'record_id'     => 0,
            //     'created_at'    => now(),
            //     'updated_at'    => now(),
            // ];

            // // Determine notification type based on package type
            // if ($influencerUser->package_type === 'free') {

            //     $notificationData['title'] = "Please subscribe - Free session used up";
            //     $notificationData['description'] = "Your free session limit is used up. Please subscribe for more sessions.";
            //     $notificationData['type'] = "subscription_expired";

            //     if ($influencerUser->push_notification === '1' && $influencerUser->device_token) {
            //         push_notification($notificationData);
            //     }
            //     in_app_notification($notificationData);

            // } elseif (in_array($influencerUser->package_type, ['monthly', 'yearly'])) {

            //     if ($influencerUser->session < 1) {
                
            //         $notificationData['title'] = "Please update subscription - Sessions used up";
            //         $notificationData['description'] = "All your subscribed sessions are used up. Please update your subscription.";
            //         $notificationData['type'] = "subscription_expired";

            //         if ($influencerUser->push_notification === '1' && $influencerUser->device_token) {
            //             push_notification($notificationData);
            //         }
            //         in_app_notification($notificationData);
            //     }
            // }


            DB::commit();
            return $this->successResponse('Appointment has been booked successfully.');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    public function cancelAppointment(Request $request, PaymentService $paymentService)
    {
        $this->validate($request, [
            'appointment_id'      =>   'required|exists:appointments,id'
        ]);

        try {
            DB::beginTransaction();
            $appointment = Appointment::whereId($request->appointment_id)->first();
            if ($appointment) {

                $refund = $paymentService->refund($appointment->strip_charge_id);

                if ($refund->status === 'succeeded') {
                    $appointment->update([
                        'status' =>  'cancel'
                    ]);
                }
            }
            DB::commit();
            return $this->successResponse('Appointment has been canceled successfully.');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }


   
}
