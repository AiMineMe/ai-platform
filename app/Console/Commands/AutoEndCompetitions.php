<?php

namespace App\Console\Commands;

use App\Enums\Wallet\Type;
use App\Models\MiningCompetition;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AutoEndCompetitions extends Command
{
    protected $signature = 'competition:auto-end';
    protected $description = 'Auto end expired competitions and distribute prizes';

    public function handle(): void
    {
        $expiredCompetitions = MiningCompetition::where('is_active', true)
            ->where('ends_at', '<', now())
            ->get();

        Log::info('------- Starting competition auto-end------- 1');
        if ($expiredCompetitions->isEmpty()) {
            $this->info('No expired competitions found.');
            return;
        }

        Log::info("Processing {$expiredCompetitions->count()} expired competitions...");
        foreach ($expiredCompetitions as $competition) {
            try {
                $this->endCompetition($competition);
                $this->info("Ended: {$competition->name}");
            } catch (\Exception $e) {
                $this->error("Failed: {$competition->name} - {$e->getMessage()}");
            }
        }

        $this->info('Done!');
    }

    private function endCompetition($competition): void
    {
        DB::beginTransaction();

        $participants = $competition->participants()
            ->orderBy('mined_amount', 'desc')
            ->get();

        if ($participants->isNotEmpty()) {
            $basePrize = (float) $competition->prize_pool;
            $collectedFees = $this->getCollectedPrizeFees($competition);
            $totalPrizePool = $basePrize + $collectedFees;
            $prizeDistribution = $this->getPrizeDistribution($competition);

            foreach ($participants as $index => $participant) {
                $rank = $index + 1;
                Log::info("Processing {$rank} prizes for {$competition->name}");
                $participant->update(['rank' => $rank]);
                $prizePercentage = $this->getPrizeForRank($prizeDistribution, $rank);
                if ($prizePercentage > 0) {
                    $prizeAmount = ($totalPrizePool * $prizePercentage) / 100;

                    $wallet = $participant->user->wallets()
                        ->where('type', Type::MAIN->value)
                        ->first();

                    $wallet->increment('balance', $prizeAmount);

                    Transaction::create([
                        'transaction_id' => 'PRIZE_' . Str::random(8),
                        'user_id' => $participant->user_id,
                        'type' => 'credit',
                        'wallet_type' => 'main_wallet',
                        'amount' => $prizeAmount,
                        'post_balance' => $wallet->fresh()->balance,
                        'status' => 'completed',
                        'details' => "Competition Prize: {$competition->name} (#{$rank})"
                    ]);

                    $participant->update(['prize_won' => $prizeAmount]);
                    $this->line("Rank #{$rank}: {$participant->user->name} - $" . number_format($prizeAmount, 2));
                }
            }
        }

        $competition->update(['is_active' => false]);
        DB::commit();
    }


    /**
     * @param $competition
     * @return array[]
     */
    private function getPrizeDistribution($competition): array
    {
        $prizes = $competition->prizes;
        if (is_array($prizes) && !empty($prizes)) {
            return $prizes;
        }

        return [
            ['position' => 1, 'percentage' => 50],
            ['position' => 2, 'percentage' => 30],
            ['position' => 3, 'percentage' => 20]
        ];
    }


    /**
     * @param $prizeDistribution
     * @param $rank
     * @return float
     */
    private function getPrizeForRank($prizeDistribution, $rank): float
    {
        foreach ($prizeDistribution as $prize) {
            if (isset($prize['position']) && $prize['position'] == $rank) {
                return (float) ($prize['reward'] ?? $prize['percentage'] ?? 0);
            }
        }

        return 0;
    }


    /**
     * @param $competition
     * @return float
     */
    private function getCollectedPrizeFees($competition): float
    {
        $totalFees = $competition->participants()->sum('entry_fee_paid');
        $adminFeePercentage = $competition->admin_fee_percentage ?? 30.0;
        $adminCut = ($totalFees * $adminFeePercentage) / 100;
        return $totalFees - $adminCut;
    }
}
