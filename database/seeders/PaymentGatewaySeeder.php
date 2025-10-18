<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        PaymentGateway::truncate();
        PaymentGateway::create([
            'name' => 'Stripe',
            'slug' => 'stripe',
            'type' => 'automatic',
            'currency' => 'USD',
            'rate' => 1,
            'min_amount' => 1.00,
            'max_amount' => 10000.00,
            'fixed_charge' => 0.30,
            'percent_charge' => 2.90,
            'description' => 'Pay securely with your credit or debit card via Stripe',
            'credentials' => [
                'publishable_key' => 'pk_test_51KiXEgK5OxdbbQz688TP1NcuhdtZ6NoI2quvXAMXXtJtBkxTFuZOlYYBhaHG5DkaIPhPJB5FjRjKVVAqi7KhWkXT004r2aGuVq',
                'secret_key' => 'sk_test_51KiXEgK5OxdbbQz6HS933RmEqJV9zN0kqZlDJUjKWg93tM7I3CUJt88fVB4QFlU9FCV2Lr62H6JhuIWc3eUAS7Ky00yeOmml2x',
            ],
            'parameters' => null,
            'status' => true,
            'sort_order' => 1,
        ]);
        PaymentGateway::create([
            'name' => 'NowPayments',
            'slug' => 'nowpayments',
            'type' => 'automatic',
            'currency' => 'USD',
            'rate' => 1,
            'min_amount' => 1.00,
            'max_amount' => 100000.00,
            'fixed_charge' => 0.00,
            'percent_charge' => 0.50,
            'description' => 'Pay with cryptocurrency via NowPayments (BTC, ETH, USDT, and 300+ coins)',
            'credentials' => [
                'api_key' => '668EP5E-7B0M9X6-GXNTQ5Z-8CQFD3M',
                'ipn_secret' => 'Uc22733ESZqnxY12jU6XduR4bIrrzPQU',
            ],
            'parameters' => null,
            'status' => true,
            'sort_order' => 2,
        ]);
        PaymentGateway::create([
            'name' => 'Bitcoin',
            'slug' => 'bitcoin',
            'type' => 'manual',
            'currency' => 'BTC',
            'file' => 'gateways/bitcoin.png',
            'rate' => 45000.00,
            'min_amount' => 1,
            'max_amount' => 10.0,
            'fixed_charge' => 0.00,
            'percent_charge' => 1.00,
            'description' => 'Pay with Bitcoin cryptocurrency',
            'credentials' => [
                'wallet_address' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
            ],
            'parameters' => [
                [
                    'field_name' => 'proof_file',
                    'field_label' => 'Transaction Screenshot',
                    'field_type' => 'file',
                    'field_required' => false,
                    'field_placeholder' => 'Upload screenshot (optional)',
                ],
            ],
            'status' => true,
            'sort_order' => 2,
        ]);
        PaymentGateway::create([
            'name' => 'Ethereum',
            'slug' => 'ethereum',
            'type' => 'manual',
            'currency' => 'ETH',
            'file' => 'gateways/ethereum.png',
            'rate' => 2500.00,
            'min_amount' => 1,
            'max_amount' => 100.0,
            'fixed_charge' => 0.00,
            'percent_charge' => 1.00,
            'description' => 'Pay with Ethereum cryptocurrency',
            'credentials' => [
                'wallet_address' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
            ],
            'parameters' => [
                [
                    'field_name' => 'proof_file',
                    'field_label' => 'Transaction Screenshot',
                    'field_type' => 'file',
                    'field_required' => false,
                    'field_placeholder' => 'Upload screenshot (optional)',
                ],
            ],
            'status' => true,
            'sort_order' => 3,
        ]);
        PaymentGateway::create([
            'name' => 'USDT (ERC-20)',
            'slug' => 'usdt-erc20',
            'type' => 'manual',
            'currency' => 'USDT',
            'file' => 'gateways/usdt-erc20.png',
            'rate' => 1.00,
            'min_amount' => 1,
            'max_amount' => 100000.00,
            'fixed_charge' => 0.00,
            'percent_charge' => 0.50,
            'description' => 'Pay with Ethereum cryptocurrency and Network: Ethereum (ERC-20)',
            'credentials' => [
                'wallet_address' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
            ],
            'parameters' => [
                [
                    'field_name' => 'proof_file',
                    'field_label' => 'Transaction Screenshot',
                    'field_type' => 'file',
                    'field_required' => false,
                    'field_placeholder' => 'Upload screenshot (optional)',
                ],
            ],
            'status' => true,
            'sort_order' => 4,
        ]);
    }
}
