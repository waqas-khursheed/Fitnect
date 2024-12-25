<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AppointmentReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:appointment-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        // Live Session Appointment Reminder Notification 
        $live_session_appointment_reminder =  Appointment::where('status', 'pending')->where('type', 'live_session')
            ->where(function ($query) use ($now) {
                $query->whereRaw("CONCAT(date, ' ', start_time) <= ?", [$now->toDateTimeString()]);
            })->where('is_reminder', '0')->get();

        foreach ($live_session_appointment_reminder as $appointment) {

            Appointment::where('id', $appointment->id)->update(['is_reminder' => '1']);

            $user = User::where('id', $appointment->user_id)->first(['id', 'first_name', 'last_name']);
            $influencer = User::where('id', $appointment->influencer_id)->first(['id', 'device_token', 'push_notification']);

            $notification = [
                'device_token'  => $influencer->device_token,
                'sender_id'     => $user->id,
                'receiver_id'   => $influencer->id,
                'title'         => 'Appointment session with ' . $user->first_name . ' ' . $user->last_name . ' started, please initiate the session for it.',
                'description'   => 'Your appointment session with ' . $user->first_name . ' ' . $user->last_name . ' is started, please initiate the session for it.',
                'record_id'     => $appointment->id,
                'type'          => 'appointment_reminder',
                'created_at'    => now(),
                'updated_at'    => now()
            ];

            if ($influencer->push_notification == '1' && $influencer->device_token != null) {
                push_notification($notification);
            }
            in_app_notification($notification);
        }

        $live_session_appointment_ids =  Appointment::where('type', 'live_session')
            ->where(function ($query) use ($now) {
                $query->whereRaw("CONCAT(date, ' ', end_time) <= ?", [$now->toDateTimeString()]);
            })->pluck('id');

        Notification::whereIn('record_id', $live_session_appointment_ids)->whereIn('type', ['appointment_reminder', 'appointment_call'])->delete();
        Log::info('Appointment Reminder started at ' . $now);
   
    }
}
