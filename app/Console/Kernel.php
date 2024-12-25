<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\UserSubscriptionCheckExpiry::class,
        \App\Console\Commands\AppointmentCronJob::class, 
        \App\Console\Commands\AppointmentReminder::class, 

    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('app:user-subscription-check-expiry')->everyMinute();
        $schedule->command('app:appointment-cron-job')->everyMinute();
        $schedule->command('app:appointment-reminder')->everyMinute();

    }
    
    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}