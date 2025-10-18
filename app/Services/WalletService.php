<?php

namespace App\Services;

use App\Enums\Wallet\Status;
use App\Enums\Wallet\Type;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    public function createDefaultWallets(User $user): array
    {
        $wallets = [];
        $currency = Setting::get('default_currency', 'USDT');
        DB::transaction(function () use ($user, &$wallets, &$currency) {
            try {
                $mainWallet = $this->createWalletRecord($user, $currency, Type::MAIN);
                $wallets[] = $mainWallet;

                $tradeWallet = $this->createWalletRecord($user, $currency, Type::TRADE);
                $wallets[] = $tradeWallet;

                Log::info("Created wallets for user {$user->id}", [
                    'main_wallet_id' => $mainWallet->id,
                    'trade_wallet_id' => $tradeWallet->id,
                    'currency' => $currency
                ]);

            } catch (\Exception $e) {
                Log::error("Failed to create {$currency} wallets for user {$user->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

        });

        return $wallets;
    }

    private function createWalletRecord(User $user, string $currency, Type $type): Wallet
    {
        return Wallet::create([
            'user_id' => $user->id,
            'name' => $this->generateWalletName($currency, $type),
            'type' => $type->value,
            'currency' => Setting::get('default_currency', 'USD'),
            'address' => $this->generateWalletAddress($currency, $type),
            'balance' => 0.00000000,
            'status' => Status::ACTIVE->value,
            'last_activity' => now(),
        ]);
    }

    private function generateWalletName(string $currency, Type $type): string
    {
        $typeName = match($type) {
            Type::MAIN => 'Main',
            Type::TRADE => 'Trade',
        };

        return "{$currency} {$typeName} Wallet";
    }

    private function generateWalletAddress(string $currency, Type $type): string
    {
        return strtolower($currency) . '_' . uniqid() . '_' . $type->value;
    }
}
