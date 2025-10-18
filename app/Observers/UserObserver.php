<?php

namespace App\Observers;

use App\Enums\User\RoleStatus;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Support\Facades\Log;

readonly class UserObserver
{
    public function __construct(protected WalletService $walletService)
    {
    }

    /**
     * Handle the User "creating" event.
     */
    public function creating(User $user): void
    {
        Log::info("User creating event triggered", ['email' => $user->email]);
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        try {
            if($user->role == RoleStatus::ADMIN->value) {
                Log::info("Skipping wallet creation for admin user", ['user_id' => $user->id]);
                return;
            }

            $wallets = $this->walletService->createDefaultWallets($user);
            Log::info("Default wallets created for new user", [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'wallets_created' => count($wallets)
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to create default wallets for user", [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {

    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {

    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {

    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {

    }
}
