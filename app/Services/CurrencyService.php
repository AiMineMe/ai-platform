<?php

namespace App\Services;

use App\Models\Currency;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyService
{
    private const REQUEST_TIMEOUT = 30;

    /**
     * @return void
     */
    public function updateAllPrices(): void
    {
        if(Currency::count() == 0){
            $this->seedTopCryptos();
        }

        $this->updateCryptoPrices();
    }


    /**
     * @return void
     */
    public function seedTopCryptos(): void
    {
        $cryptoData = [
            ['symbol' => 'BTC', 'name' => 'Bitcoin'],
            ['symbol' => 'ETH', 'name' => 'Ethereum'],
            ['symbol' => 'USDT', 'name' => 'Tether'],
            ['symbol' => 'BNB', 'name' => 'BNB'],
            ['symbol' => 'SOL', 'name' => 'Solana'],
            ['symbol' => 'USDC', 'name' => 'USD Coin'],
            ['symbol' => 'XRP', 'name' => 'XRP'],
            ['symbol' => 'STETH', 'name' => 'Lido Staked ETH'],
            ['symbol' => 'DOGE', 'name' => 'Dogecoin'],
            ['symbol' => 'ADA', 'name' => 'Cardano'],
            ['symbol' => 'TRX', 'name' => 'TRON'],
            ['symbol' => 'AVAX', 'name' => 'Avalanche'],
            ['symbol' => 'LINK', 'name' => 'Chainlink'],
            ['symbol' => 'SHIB', 'name' => 'Shiba Inu'],
            ['symbol' => 'BCH', 'name' => 'Bitcoin Cash'],
            ['symbol' => 'DOT', 'name' => 'Polkadot'],
            ['symbol' => 'NEAR', 'name' => 'NEAR Protocol'],
            ['symbol' => 'MATIC', 'name' => 'Polygon'],
            ['symbol' => 'LTC', 'name' => 'Litecoin'],
            ['symbol' => 'ICP', 'name' => 'Internet Computer'],
            ['symbol' => 'DAI', 'name' => 'Dai'],
            ['symbol' => 'UNI', 'name' => 'Uniswap'],
            ['symbol' => 'LEO', 'name' => 'UNUS SED LEO'],
            ['symbol' => 'ETC', 'name' => 'Ethereum Classic'],
            ['symbol' => 'RNDR', 'name' => 'Render Token'],
            ['symbol' => 'HBAR', 'name' => 'Hedera'],
            ['symbol' => 'KAS', 'name' => 'Kaspa'],
            ['symbol' => 'TAO', 'name' => 'Bittensor'],
            ['symbol' => 'ARB', 'name' => 'Arbitrum'],
            ['symbol' => 'XLM', 'name' => 'Stellar'],
            ['symbol' => 'CRO', 'name' => 'Cronos'],
            ['symbol' => 'OKB', 'name' => 'OKB'],
            ['symbol' => 'MNT', 'name' => 'Mantle'],
            ['symbol' => 'FIL', 'name' => 'Filecoin'],
            ['symbol' => 'ATOM', 'name' => 'Cosmos'],
            ['symbol' => 'VET', 'name' => 'VeChain'],
            ['symbol' => 'XMR', 'name' => 'Monero'],
            ['symbol' => 'INJ', 'name' => 'Injective'],
            ['symbol' => 'TON', 'name' => 'The Open Network'],
            ['symbol' => 'SUI', 'name' => 'Sui'],
            ['symbol' => 'AAVE', 'name' => 'Aave'],
            ['symbol' => 'OP', 'name' => 'Optimism'],
            ['symbol' => 'IMX', 'name' => 'Immutable X'],
            ['symbol' => 'FDUSD', 'name' => 'First Digital USD'],
            ['symbol' => 'MKR', 'name' => 'Maker'],
            ['symbol' => 'RETH', 'name' => 'Rocket Pool ETH'],
            ['symbol' => 'FTM', 'name' => 'Fantom'],
            ['symbol' => 'BONK', 'name' => 'Bonk'],
            ['symbol' => 'ALGO', 'name' => 'Algorand'],
            ['symbol' => 'THETA', 'name' => 'THETA']
        ];

        foreach ($cryptoData as $crypto) {
            Currency::updateOrCreate(
                ['symbol' => $crypto['symbol']],
                [
                    'name' => $crypto['name'],
                    'type' => 'crypto',
                    'current_price' => 0,
                    'previous_price' => 0,
                    'base_currency' => 'USD',
                    'tradingview_symbol' => $this->getTradingViewSymbol($crypto['symbol'])
                ]
            );
        }

        $this->updateCryptoPrices();

        Log::info("Seeded " . count($cryptoData) . " crypto currencies with TradingView symbols, prices and images");
    }


    /**
     * @return void
     */
    public function updateCryptoPrices(): void
    {
        $currencies = Currency::where('type', 'crypto')->get();

        if ($currencies->isEmpty()) {
            Log::info("No crypto currencies found in database");
            return;
        }

        $coinIds = $currencies->pluck('symbol')->map(function ($symbol) {
            return $this->getCoinGeckoId($symbol);
        })->filter()->unique()->toArray();

        if (empty($coinIds)) {
            Log::warning("No valid coin IDs found for crypto currencies");
            return;
        }
        $url = "https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids=" . implode(',', $coinIds) . "&order=market_cap_desc&per_page=250&page=1&price_change_percentage=24h";
        $response = $this->makeHttpRequest($url);

        if (!$response) {
            Log::error("Crypto API request failed");
            return;
        }

        try {
            $coinsData = $response->json();
            $this->processCryptoPriceData($coinsData, $currencies);
            Log::info("Successfully updated " . count($currencies) . " crypto prices and images");
        } catch (\Exception $e) {
            Log::error("Crypto price processing failed: " . $e->getMessage());
        }
    }


    /**
     * @param array $coinsData
     * @param $currencies
     * @return void
     */
    private function processCryptoPriceData(array $coinsData, $currencies): void
    {
        $coinDataMap = [];
        foreach ($coinsData as $coinInfo) {
            $coinDataMap[$coinInfo['id']] = $coinInfo;
        }

        foreach ($currencies as $currency) {
            $coinId = $this->getCoinGeckoId($currency->symbol);

            if (!$coinId || !isset($coinDataMap[$coinId])) {
                continue;
            }

            $coinInfo = $coinDataMap[$coinId];
            $newPrice = $coinInfo['current_price'] ?? 0;
            $changePercent = $coinInfo['price_change_percentage_24h'] ?? null;
            $imageUrl = $coinInfo['image'] ?? null;
            $tradingViewSymbol = $this->getTradingViewSymbol($currency->symbol);

            $updateData = [
                'total_volume' => $coinInfo['total_volume'] ?? 0,
                'market_cap' => $coinInfo['market_cap'] ?? 0,
                'rank' => $coinInfo['market_cap_rank'] ?? 0,
                'previous_price' => $currency->current_price,
                'current_price' => $newPrice,
                'change_percent' => $changePercent,
                'last_updated' => Carbon::now(),
            ];

            if ($imageUrl) {
                $updateData['image_url'] = $imageUrl;
            }

            if ($tradingViewSymbol && $currency->tradingview_symbol !== $tradingViewSymbol) {
                $updateData['tradingview_symbol'] = $tradingViewSymbol;
            }

            $currency->update($updateData);
        }
    }

    /**
     * @param string $symbol
     * @return string|null
     */
    private function getCoinGeckoId(string $symbol): ?string
    {
        $symbolMap = [
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum',
            'USDT' => 'tether',
            'BNB' => 'binancecoin',
            'SOL' => 'solana',
            'USDC' => 'usd-coin',
            'XRP' => 'xrp',
            'STETH' => 'staked-ether',
            'DOGE' => 'dogecoin',
            'ADA' => 'cardano',
            'TRX' => 'tron',
            'AVAX' => 'avalanche-2',
            'LINK' => 'chainlink',
            'SHIB' => 'shiba-inu',
            'BCH' => 'bitcoin-cash',
            'DOT' => 'polkadot',
            'NEAR' => 'near',
            'MATIC' => 'polygon',
            'LTC' => 'litecoin',
            'ICP' => 'internet-computer',
            'DAI' => 'dai',
            'UNI' => 'uniswap',
            'LEO' => 'leo-token',
            'ETC' => 'ethereum-classic',
            'RNDR' => 'render-token',
            'HBAR' => 'hedera-hashgraph',
            'KAS' => 'kaspa',
            'TAO' => 'bittensor',
            'ARB' => 'arbitrum',
            'XLM' => 'stellar',
            'CRO' => 'cronos',
            'OKB' => 'okb',
            'MNT' => 'mantle',
            'FIL' => 'filecoin',
            'ATOM' => 'cosmos',
            'VET' => 'vechain',
            'XMR' => 'monero',
            'INJ' => 'injective-protocol',
            'TON' => 'the-open-network',
            'SUI' => 'sui',
            'AAVE' => 'aave',
            'OP' => 'optimism',
            'IMX' => 'immutable-x',
            'FDUSD' => 'first-digital-usd',
            'MKR' => 'maker',
            'RETH' => 'rocket-pool-eth',
            'FTM' => 'fantom',
            'BONK' => 'bonk',
            'ALGO' => 'algorand',
            'THETA' => 'theta-token'
        ];

        return $symbolMap[strtoupper($symbol)] ?? null;
    }

    /**
     * @param string $symbol
     * @return string|null
     */
    private function getTradingViewSymbol(string $symbol): ?string
    {
        $tradingViewMap = [
            'BTC' => 'BTCUSD',
            'ETH' => 'ETHUSD',
            'USDT' => 'USDTUSD',
            'BNB' => 'BNBUSD',
            'SOL' => 'SOLUSD',
            'USDC' => 'USDCUSD',
            'XRP' => 'XRPUSD',
            'STETH' => 'STETHUSD',
            'DOGE' => 'DOGEUSD',
            'ADA' => 'ADAUSD',
            'TRX' => 'TRXUSD',
            'AVAX' => 'AVAXUSD',
            'LINK' => 'LINKUSD',
            'SHIB' => 'SHIBUSD',
            'BCH' => 'BCHUSD',
            'DOT' => 'DOTUSD',
            'NEAR' => 'NEARUSD',
            'MATIC' => 'MATICUSD',
            'LTC' => 'LTCUSD',
            'ICP' => 'ICPUSD',
            'DAI' => 'DAIUSD',
            'UNI' => 'UNIUSD',
            'LEO' => 'LEOUSD',
            'ETC' => 'ETCUSD',
            'RNDR' => 'RNDRUSD',
            'HBAR' => 'HBARUSD',
            'KAS' => 'KASUSD',
            'TAO' => 'TAOUSD',
            'ARB' => 'ARBUSD',
            'XLM' => 'XLMUSD',
            'CRO' => 'CROUSD',
            'OKB' => 'OKBUSD',
            'MNT' => 'MNTUSD',
            'FIL' => 'FILUSD',
            'ATOM' => 'ATOMUSD',
            'VET' => 'VETUSD',
            'XMR' => 'XMRUSD',
            'INJ' => 'INJUSD',
            'TON' => 'TONUSD',
            'SUI' => 'SUIUSD',
            'AAVE' => 'AAVEUSD',
            'OP' => 'OPUSD',
            'IMX' => 'IMXUSD',
            'FDUSD' => 'FDUSDUSD',
            'MKR' => 'MKRUSD',
            'RETH' => 'RETHUSD',
            'FTM' => 'FTMUSD',
            'BONK' => 'BONKUSD',
            'ALGO' => 'ALGOUSD',
            'THETA' => 'THETAUSD'
        ];

        return $tradingViewMap[strtoupper($symbol)] ?? null;
    }

    /**
     * @param string $url
     * @return Response|null
     */
    private function makeHttpRequest(string $url): Response|null
    {
        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)->get($url);
            return $response->successful() ? $response : null;
        } catch (\Exception $e) {
            Log::error("HTTP request failed for {$url}: " . $e->getMessage());
            return null;
        }
    }


}
