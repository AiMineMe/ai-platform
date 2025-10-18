<?php

namespace Database\Seeders;

use App\Models\WithdrawalGateway;
use Illuminate\Database\Seeder;

class WithdrawalGatewaySeeder extends Seeder
{
    public function run(): void
    {
        WithdrawalGateway::query()->delete();
        WithdrawalGateway::create([
            'name' => 'Bank Transfer',
            'currency' => 'USD',
            'rate' => 1,
            'min_amount' => 50.00,
            'max_amount' => 50000.00,
            'fixed_charge' => 5.00,
            'percent_charge' => 1.50,
            'description' => 'Direct bank transfer to your registered bank account. Processing time: 1-3 business days.',
            'parameters' => [
                [
                    'field_name' => 'account_holder_name',
                    'field_type' => 'text',
                    'field_label' => 'Account Holder Name',
                    'field_required' => true,
                    'field_placeholder' => 'Enter full name as on bank account',
                ],
                [
                    'field_name' => 'bank_name',
                    'field_type' => 'text',
                    'field_label' => 'Bank Name',
                    'field_required' => true,
                    'field_placeholder' => 'Enter your bank name',
                ],
                [
                    'field_name' => 'account_number',
                    'field_type' => 'text',
                    'field_label' => 'Account Number',
                    'field_required' => true,
                    'field_placeholder' => 'Enter your account number',
                ],
                [
                    'field_name' => 'routing_number',
                    'field_type' => 'text',
                    'field_label' => 'Routing Number',
                    'field_required' => true,
                    'field_placeholder' => 'Enter routing number',
                ],
                [
                    'field_name' => 'account_type',
                    'field_type' => 'select',
                    'field_label' => 'Account Type',
                    'field_required' => true,
                    'field_placeholder' => 'Select account type',
                    'field_options' => [
                        'checking' => 'Checking Account',
                        'savings' => 'Savings Account',
                    ],
                ],
                [
                    'field_name' => 'swift_code',
                    'field_type' => 'text',
                    'field_label' => 'SWIFT Code',
                    'field_required' => false,
                    'field_placeholder' => 'Enter SWIFT code (for international transfers)',
                ],
            ],
            'status' => true,
        ]);
        WithdrawalGateway::create([
            'name' => 'PayPal',
            'currency' => 'USD',
            'rate' => 1,
            'min_amount' => 10.00,
            'max_amount' => 10000.00,
            'fixed_charge' => 0.30,
            'percent_charge' => 2.90,
            'description' => 'Instant withdrawal to your PayPal account. Funds available immediately.',
            'parameters' => [
                [
                    'field_name' => 'paypal_email',
                    'field_type' => 'email',
                    'field_label' => 'PayPal Email',
                    'field_required' => true,
                    'field_placeholder' => 'Enter your PayPal email address',
                ],
                [
                    'field_name' => 'full_name',
                    'field_type' => 'text',
                    'field_label' => 'Full Name',
                    'field_required' => true,
                    'field_placeholder' => 'Enter your full name',
                ],
            ],
            'status' => true,
        ]);
        WithdrawalGateway::create([
            'name' => 'USDT (Tether)',
            'currency' => 'USD',
            'rate' => 1,
            'min_amount' => 10.00,
            'max_amount' => 50000.00,
            'fixed_charge' => 1.00,
            'percent_charge' => 0.20,
            'description' => 'Withdraw USDT to your wallet. Multiple blockchain networks available.',
            'parameters' => [
                [
                    'field_name' => 'wallet_address',
                    'field_type' => 'text',
                    'field_label' => 'USDT Wallet Address',
                    'field_required' => true,
                    'field_placeholder' => 'Enter your USDT wallet address',
                ],
                [
                    'field_name' => 'network',
                    'field_type' => 'select',
                    'field_label' => 'Network',
                    'field_required' => true,
                    'field_placeholder' => 'Select blockchain network',
                    'field_options' => [
                        'trc20' => 'TRC-20 (Tron)',
                        'erc20' => 'ERC-20 (Ethereum)',
                        'bep20' => 'BEP-20 (BSC)',
                        'polygon' => 'Polygon',
                    ],
                ],
                [
                    'field_name' => 'memo',
                    'field_type' => 'text',
                    'field_label' => 'Memo/Tag (if required)',
                    'field_required' => false,
                    'field_placeholder' => 'Enter memo or tag if required by exchange',
                ],
            ],
            'status' => true,
        ]);
    }
}
