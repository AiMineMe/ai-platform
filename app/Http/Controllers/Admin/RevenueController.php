<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRevenue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RevenueController extends Controller
{
    public function index(Request $request)
    {
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);

        // Revenue overview
        $totalRevenue = AdminRevenue::where('created_at', '>=', $startDate)->sum('amount');
        $subscriptionRevenue = AdminRevenue::where('revenue_type', 'subscription')
            ->where('created_at', '>=', $startDate)->sum('amount');
        $miningFeeRevenue = AdminRevenue::where('revenue_type', 'mining_fee')
            ->where('created_at', '>=', $startDate)->sum('amount');
        $competitionRevenue = AdminRevenue::where('revenue_type', 'competition_fee')
            ->where('created_at', '>=', $startDate)->sum('amount');

        // Daily breakdown
        $dailyRevenue = AdminRevenue::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(CASE WHEN revenue_type = "subscription" THEN amount ELSE 0 END) as subscriptions'),
            DB::raw('SUM(CASE WHEN revenue_type = "mining_fee" THEN amount ELSE 0 END) as mining_fees'),
            DB::raw('SUM(CASE WHEN revenue_type = "competition_fee" THEN amount ELSE 0 END) as competition_fees'),
            DB::raw('SUM(amount) as total')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Recent transactions
        $recentRevenue = AdminRevenue::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Revenue by type pie chart data
        $revenueByType = AdminRevenue::select('revenue_type', DB::raw('SUM(amount) as total'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('revenue_type')
            ->get();

        return Inertia::render('Admin/Revenue/Index', [
            'overview' => [
                'total_revenue' => $totalRevenue,
                'subscription_revenue' => $subscriptionRevenue,
                'mining_fee_revenue' => $miningFeeRevenue,
                'competition_revenue' => $competitionRevenue
            ],
            'dailyRevenue' => $dailyRevenue,
            'recentRevenue' => $recentRevenue,
            'revenueByType' => $revenueByType,
            'days' => $days
        ]);
    }

    public function export(Request $request)
    {
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);

        $revenues = AdminRevenue::with('user:id,name,email')
            ->where('created_at', '>=', $startDate)
            ->orderBy('created_at', 'desc')
            ->get();

        // You can implement CSV export here
        $filename = "admin_revenue_" . now()->format('Y-m-d') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($revenues) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Date', 'Type', 'Amount', 'User', 'Description']);

            foreach ($revenues as $revenue) {
                fputcsv($file, [
                    $revenue->created_at->format('Y-m-d H:i:s'),
                    $revenue->revenue_type,
                    $revenue->amount,
                    $revenue->user->name,
                    $revenue->description
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
