<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;
use App\Models\User;
use App\Models\ConferenceCall;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AppointmentCronJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:appointment-cron-job';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(PaymentService $paymentService)
    {
        $now = Carbon::now();
        // Log::info('AppointmentCronJob started at ' . $now);
        // Local Meetup Appointment Complete 
        $local_meetup_app =  Appointment::where('status', 'pending')->where('type', 'local_meetup')
            ->where(function ($query) use ($now) {
                $query->whereRaw("CONCAT(date, ' ', end_time) <= ?", [$now->toDateTimeString()]);
            })->get();

        if ($local_meetup_app) {
            foreach ($local_meetup_app as $appointment) {

                Appointment::where('id', $appointment->id)->update(['status' => 'complete']);

                $user = User::where('id', $appointment->user_id)->first(['id', 'device_token', 'push_notification']);
                $influencer = User::where('id', $appointment->influencer_id)->first(['id', 'first_name', 'last_name']);

                $notification = [
                    'device_token'  => $user->device_token,
                    'sender_id'     => $influencer->id,
                    'receiver_id'   => $user->id,
                    'title'         => $influencer->first_name . ' ' . $influencer->last_name . ' has completed a appointment.',
                    'description'   => 'Your Appointment has been completed by ' . $influencer->first_name . ' ' . $influencer->last_name,
                    'record_id'     => $appointment->id,
                    'type'          => 'appointment_completed',
                    'created_at'    => now(),
                    'updated_at'    => now()
                ];

                if ($user->push_notification == '1' && $user->device_token != null) {
                    push_notification($notification);
                }
                in_app_notification($notification);
            }
        }


        // Live Session Appointment Complete 
        $live_session_appointment =  Appointment::where('status', 'pending')->where('type', 'live_session')
            ->where(function ($query) use ($now) {
                $query->whereRaw("CONCAT(date, ' ', end_time) <= ?", [$now->toDateTimeString()]);
            })->get();

        foreach ($live_session_appointment as $appointment) {

            $call =  ConferenceCall::where('appointment_id', $appointment->id)->where('status', 'start')->update(['status' => 'complete']);
            if ($call) {

                Appointment::where('id', $appointment->id)->update(['status' => 'complete']);

                $user = User::where('id', $appointment->user_id)->first(['id', 'device_token', 'push_notification']);
                $influencer = User::where('id', $appointment->influencer_id)->first(['id', 'first_name', 'last_name']);

                $notification = [
                    'device_token'  => $user->device_token,
                    'sender_id'     => $influencer->id,
                    'receiver_id'   => $user->id,
                    'title'         => $influencer->first_name . ' ' . $influencer->last_name . ' has completed a appointment.',
                    'description'   => 'Your Appointment has been completed by ' . $influencer->first_name . ' ' . $influencer->last_name,
                    'record_id'     => $appointment->id,
                    'type'          => 'appointment_completed',
                    'created_at'    => now(),
                    'updated_at'    => now()
                ];

                if ($user->push_notification == '1' && $user->device_token != null) {
                    push_notification($notification);
                }
                in_app_notification($notification);
            } else {

                try {
                    $refund = $paymentService->refund($appointment->strip_charge_id);

                    if ($refund->status === 'succeeded') {

                        Appointment::where('id', $appointment->id)->update(['status' => 'cancel']);

                        $user = User::where('id', $appointment->user_id)->first(['id', 'device_token', 'push_notification']);
                        $influencer = User::where('id', $appointment->influencer_id)->first(['id', 'first_name', 'last_name']);

                        $notification = [
                            'device_token'  => $user->device_token,
                            'sender_id'     => $influencer->id,
                            'receiver_id'   => $user->id,
                            'title'         => 'Appointment has been cancelled.',
                            'description'   => 'Your Appointment has been cancelled',
                            'record_id'     => $appointment->id,
                            'type'          => 'appointment_cancel',
                            'created_at'    => now(),
                            'updated_at'    => now()
                        ];

                        if ($user->push_notification == '1' && $user->device_token != null) {
                            push_notification($notification);
                        }
                        in_app_notification($notification);
                    }
                } catch (\Exception $exception) {
                    Log::info($exception->getMessage());
                }
            }
        }
    }
}
