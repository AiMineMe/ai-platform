<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CheckExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:check-expired';
    protected $description = 'Check and update expired subscriptions';

    public function handle(): void
    {
        $expiredCount = User::whereNotNull('subscription_plan_id')
            ->where('subscription_expires_at', '<', now())
            ->update([
                'subscription_plan_id' => null,
                'subscription_expires_at' => null
            ]);

        $this->info("Updated {$expiredCount} expired subscriptions");
    }
}
