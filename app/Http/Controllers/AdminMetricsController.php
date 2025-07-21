<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AdminMetricsController extends Controller
{


    public function sales(Request $request): JsonResponse
    {
        $query = Order::where('payment_status', 'paid');
        $groupBy = 'month'; // default grouping
        $format = 'Y-m';    // default format for labels
        $range = [];

        if ($request->filled('month')) {
            $month = (int) $request->month;
            $year = (int) ($request->year ?? now()->year);

            $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $query->whereBetween('created_at', [$start, $end]);
            $groupBy = 'day';
            $format = 'Y-m-d';

            $range = collect(Carbon::parse($start)->daysUntil($end))->map(fn($date) => $date->format($format));
        }
        elseif ($request->filled('week')) {
            $week = (int) $request->week;
            $year = (int) ($request->year ?? now()->year);

            $start = Carbon::now()->setISODate($year, $week)->startOfWeek();
            $end = $start->copy()->endOfWeek();

            $query->whereBetween('created_at', [$start, $end]);
            $groupBy = 'day';
            $format = 'Y-m-d';

            $range = collect(Carbon::parse($start)->daysUntil($end))->map(fn($date) => $date->format($format));
        }
        elseif ($request->filled('start_date') && $request->filled('end_date')) {
            $start = Carbon::parse($request->start_date)->startOfDay();
            $end = Carbon::parse($request->end_date)->endOfDay();

            $query->whereBetween('created_at', [$start, $end]);
            $groupBy = 'day';
            $format = 'Y-m-d';

            $range = collect(Carbon::parse($start)->daysUntil($end))->map(fn($date) => $date->format($format));
        } else {
            // Default: group by month this year
            $year = now()->year;
            $start = Carbon::create($year, 1, 1);
            $end = Carbon::create($year, 12, 31);

            $query->whereBetween('created_at', [$start, $end]);
            $groupBy = 'month';
            $format = 'Y-m';

            $range = collect(range(1, 12))->map(fn($m) => sprintf('%d-%02d', $year, $m));
        }

        $totalSales = $query->sum('total_price');
        $orderCount = $query->count();

        // Grouped data (sales + orders)
        $grouped = $query->get()->groupBy(fn($order) => $order->created_at->format($format));

        $series = $range->map(function ($period) use ($grouped) {
            $orders = $grouped[$period] ?? collect();

            return [
                'period' => $period,
                'orders' => $orders->count(),
                'sales'  => $orders->sum('total_price'),
            ];
        });

        return response()->json([
            'total_sales' => $totalSales,
            'total_orders' => $orderCount,
            'filtered' => $request->only(['month', 'week', 'year', 'start_date', 'end_date']),
            'series' => $series, // For charting
        ]);
    }


    public function profit(): JsonResponse
    {
        $orders = Order::with('items.variant')->where('payment_status', 'paid')->get();

        $totalRevenue = 0;
        $totalCost = 0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $retail = floatval($item->retail_price);
                $cost = floatval($item->variant->printful_price ?? 0); // ensure this is available

                $totalRevenue += $retail * $item->quantity;
                $totalCost += $cost * $item->quantity;
            }

            $shippingCost = floatval($order->shipping_details['shipping_cost'] ?? 0);
            $totalCost += $shippingCost;
        }

        $profit = $totalRevenue - $totalCost;

        return response()->json([
            'total_revenue' => round($totalRevenue, 2),
            'total_cost' => round($totalCost, 2),
            'net_profit' => round($profit, 2),
        ]);
    }

    public function summary(): JsonResponse
    {
        $orders = Order::with('items.variant')->where('payment_status', 'paid')->get();

        $totalRevenue = 0;
        $totalCost = 0;
        $totalOrders = $orders->count();

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $retail = floatval($item->retail_price);
                $cost = floatval($item->variant->printful_price ?? 0);

                $totalRevenue += $retail * $item->quantity;
                $totalCost += $cost * $item->quantity;
            }

            $shippingCost = floatval($order->shipping_details['shipping_cost'] ?? 0);
            $totalCost += $shippingCost;
        }

        $netProfit = $totalRevenue - $totalCost;

        return response()->json([
            'total_orders' => $totalOrders,
            'total_sales'  => round($totalRevenue, 2),
            'total_cost'   => round($totalCost, 2),
            'net_profit'   => round($netProfit, 2),
        ]);
    }
    public function orderCount(): JsonResponse
    {
        $orderCount = Order::where('payment_status', 'paid')->count();

        return response()->json([
            'total_orders' => $orderCount
        ]);
    }
    public function totalSales(): JsonResponse
    {
        $totalSales = Order::where('payment_status', 'paid')->sum('total_price');
        return response()->json([
            'total_sales' => round($totalSales, 2)
        ]);
    }

}
