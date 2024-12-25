<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\{
    UserPackage,
    User,
};

class UserSubscriptionCheckExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:user-subscription-check-expiry';

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
        $currentDateTime = Carbon::now();
        try {
            $expiredPackages = UserPackage::where('is_active', '1')
                ->whereDate('expires_at', '<', now())
                ->get();

            foreach ($expiredPackages as $package) {
                $package->update(['is_active' => '0']);

                $user = User::find($package->user_id);

                if ($user) {
                    $user->update([
                        'session' => '0',
                        'package_type' => 'free',
                        'package_name' => null,
                    ]);

                    $notification = [
                        'device_token'  => $user->device_token,
                        'sender_id'     => 1, // System or admin ID
                        'receiver_id'   => $user->id,
                        'title'         => 'Your subscription has expired',
                        'description'   => 'Your subscription has expired. You are now on the free plan. Renew your subscription to continue enjoying premium features.',
                        'record_id'     => 0,
                        'type'          => 'subscription_expired',
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];

                    if ($user->push_notification == '1' && $user->device_token) {
                        push_notification($notification);
                    }

                    in_app_notification($notification);
                }
            }

            Log::info('Expired packages and user updates processed successfully at ' . $currentDateTime);
        } catch (\Exception $e) {
            Log::error('Error processing expired packages: ' . $e->getMessage());
        }
    }
}

