<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;

class DashboardService
{
    private const STATUS_KEYS = ['pending', 'confirmed', 'shipping', 'completed', 'cancelled'];

    /**
     * Số liệu tổng quan, doanh thu 12 tháng (triệu đồng) và phân bổ trạng thái đơn.
     * Doanh thu chỉ tính đơn đã giao hoặc hoàn thành.
     */
    public function statistics(int $year): array
    {
        $revenueOrders = Order::whereIn('status', ['delivered', 'completed'])
            ->whereYear('created_at', $year)
            ->get(['total', 'created_at']);

        $monthlyRevenue = array_fill(0, 12, 0.0);
        foreach ($revenueOrders as $order) {
            $monthlyRevenue[$order->created_at->month - 1] += $order->total;
        }

        // Đơn "đã giao" được gộp chung nhóm "hoàn thành" trên biểu đồ.
        $statusCounts = Order::selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $statusCounts['completed'] = ($statusCounts['completed'] ?? 0) + ($statusCounts['delivered'] ?? 0);

        return [
            'stats' => [
                'products' => Product::count(),
                'orders' => Order::count(),
                'customers' => User::customers()->count(),
                'revenue' => (int) $revenueOrders->sum('total'),
            ],
            'recentOrders' => Order::latest()->limit(5)->get(),
            'topProducts' => Product::orderByDesc('sold')->latest()->limit(5)->get(),
            'monthlyRevenue' => array_map(fn ($value) => round($value / 1000000, 1), $monthlyRevenue),
            'orderStatus' => array_map(fn ($status) => [
                'status' => $status,
                'label' => Order::statusLabelOf($status),
                'count' => (int) ($statusCounts[$status] ?? 0),
            ], self::STATUS_KEYS),
        ];
    }
}
