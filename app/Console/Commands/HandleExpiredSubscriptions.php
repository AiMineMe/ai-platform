<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EmailTemplateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HandleExpiredSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:handle-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle expired user subscriptions and send notification emails and SMS';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting expired subscription cleanup...');
        $expiredUsers = User::whereNotNull('subscription_plan_id')
            ->whereNotNull('subscription_expires_at')
            ->where('subscription_expires_at', '<', now())
            ->with('subscriptionPlan')
            ->get();

        if ($expiredUsers->isEmpty()) {
            $this->info('No expired subscriptions found.');
            return 0;
        }

        foreach ($expiredUsers as $user) {
            try {
                $planName = $user->subscriptionPlan?->name ?? 'Unknown Plan';
                $expiredAt = $user->subscription_expires_at;
                $user->update([
                    'subscription_plan_id' => null,
                    'subscription_expires_at' => null
                ]);

                $this->sendExpirationNotifications($user, $planName, $expiredAt);
                Log::info("Subscription expired for user {$user->id}", [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'plan_name' => $planName,
                    'expired_at' => $expiredAt
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to process expired subscription", [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        return 0;
    }


    /**
     * @param User $user
     * @param string $planName
     * @param $expiredAt
     * @return void
     */
    private function sendExpirationNotifications(User $user, string $planName, $expiredAt): void
    {
        try {
            EmailTemplateService::sendTemplateEmail('subscription_expired', $user, [
                'user_name' => e($user->name),
                'plan_name' => e($planName),
                'expired_date' => $expiredAt->format('M d, Y')
            ]);


        } catch (\Exception $e) {
            Log::error("Failed to send subscription expiration notifications", [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'error' => $e->getMessage()
            ]);
        }
    }
}
