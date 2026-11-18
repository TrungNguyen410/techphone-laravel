<?php

namespace App\Services;

use App\Models\Accessory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Số liệu trang tổng quan quản trị. Doanh thu chỉ tính đơn đã giao hoặc hoàn thành,
 * nhóm theo ngày đặt hàng (giờ Việt Nam - múi giờ của ứng dụng).
 */
class DashboardService
{
    public const REVENUE_STATUSES = ['delivered', 'completed'];

    public const LOW_STOCK_THRESHOLD = 10;

    private const STATUS_KEYS = ['pending', 'confirmed', 'shipping', 'completed', 'cancelled'];

    private const YEARS_SHOWN = 5;

    private const TOP_PRODUCTS = 5;

    private const TOP_CUSTOMERS = 5;

    private const LOW_STOCK_SHOWN = 20;

    public function statistics(int $year, int $month): array
    {
        $revenue = $this->revenue($year, $month);

        // Đơn "đã giao" được gộp chung nhóm "hoàn thành" trên biểu đồ.
        $statusCounts = Order::selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $statusCounts['completed'] = ($statusCounts['completed'] ?? 0) + ($statusCounts['delivered'] ?? 0);

        return [
            'stats' => [
                'products' => Product::count(),
                'orders' => Order::count(),
                'customers' => User::customers()->count(),
                'revenue' => array_sum($revenue['monthly']),
            ],
            'revenue' => $revenue,
            'recentOrders' => Order::latest()->limit(5)->get(),
            'topProducts' => $this->topProducts(),
            'topCustomers' => $this->topCustomers(),
            'lowStock' => $this->lowStock(),
            'orderStatus' => array_map(fn ($status) => [
                'status' => $status,
                'label' => Order::statusLabelOf($status),
                'count' => (int) ($statusCounts[$status] ?? 0),
            ], self::STATUS_KEYS),
        ];
    }

    /**
     * Ba chuỗi doanh thu (đồng): 5 năm gần nhất tới năm đã chọn, các tháng của năm đó và từng ngày của tháng đã chọn.
     * Tháng hiện tại chỉ có số liệu từ ngày 1 tới hôm nay, năm hiện tại chỉ tới tháng này (không vẽ thời điểm chưa tới),
     * tháng/năm chưa tới để trống.
     *
     * @return array{year: int, month: int, today: string, isCurrentMonth: bool,
     *     yearly: array<int, array{year: int, total: int}>, monthly: array<int, int>, daily: array<int, int>}
     */
    public function revenue(int $year, int $month): array
    {
        $today = now();
        $firstYear = $year - self::YEARS_SHOWN + 1;

        $byYear = $this->revenueQuery()
            ->whereBetween('created_at', [Carbon::create($firstYear)->startOfYear(), Carbon::create($year)->endOfYear()])
            ->selectRaw('YEAR(created_at) as period, SUM(total) as revenue')
            ->groupBy('period')
            ->pluck('revenue', 'period');
        $byMonth = $this->revenueQuery()
            ->whereBetween('created_at', [Carbon::create($year)->startOfYear(), Carbon::create($year)->endOfYear()])
            ->selectRaw('MONTH(created_at) as period, SUM(total) as revenue')
            ->groupBy('period')
            ->pluck('revenue', 'period');

        // Năm hiện tại chỉ vẽ tới tháng này (không kéo đường xuống 0 ở các tháng chưa tới).
        $monthsShown = match (true) {
            $year === $today->year => $today->month,
            $year > $today->year => 0,
            default => 12,
        };

        $monthStart = Carbon::create($year, $month);
        $isCurrentMonth = $monthStart->isSameMonth($today);
        $daysShown = match (true) {
            $isCurrentMonth => $today->day,
            $monthStart->gt($today) => 0,
            default => $monthStart->daysInMonth,
        };
        $byDay = $daysShown === 0 ? collect() : $this->revenueQuery()
            ->whereBetween('created_at', [$monthStart->copy()->startOfMonth(), $monthStart->copy()->endOfMonth()])
            ->selectRaw('DAY(created_at) as period, SUM(total) as revenue')
            ->groupBy('period')
            ->pluck('revenue', 'period');

        return [
            'year' => $year,
            'month' => $month,
            'today' => $today->toDateString(),
            'isCurrentMonth' => $isCurrentMonth,
            'yearly' => array_map(fn (int $value) => ['year' => $value, 'total' => (int) ($byYear[$value] ?? 0)], range($firstYear, $year)),
            'monthly' => $monthsShown === 0 ? [] : array_map(fn (int $value) => (int) ($byMonth[$value] ?? 0), range(1, $monthsShown)),
            'daily' => $daysShown === 0 ? [] : array_map(fn (int $value) => (int) ($byDay[$value] ?? 0), range(1, $daysShown)),
        ];
    }

    /**
     * Bán chạy nhất gồm cả điện thoại và phụ kiện, theo số lượng đã bán cộng dồn.
     *
     * @return Collection<int, Model>
     */
    public function topProducts(): Collection
    {
        return Product::where('sold', '>', 0)->orderByDesc('sold')->limit(self::TOP_PRODUCTS)->get()
            ->concat(Accessory::where('sold', '>', 0)->orderByDesc('sold')->limit(self::TOP_PRODUCTS)->get())
            ->sortByDesc('sold')
            ->take(self::TOP_PRODUCTS)
            ->values();
    }

    /**
     * Khách chi tiêu nhiều nhất (tổng tiền đơn đã giao/hoàn thành), kèm số đơn và lần mua gần nhất.
     *
     * @return Collection<int, User>
     */
    public function topCustomers(): Collection
    {
        $totals = Order::query()
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as order_count, SUM(total) as total_spent, MAX(created_at) as last_order_at')
            ->groupBy('user_id');

        return User::customers()
            ->joinSub($totals, 'totals', 'totals.user_id', '=', 'users.id')
            ->select('users.*', 'totals.order_count', 'totals.total_spent', 'totals.last_order_at')
            ->orderByDesc('totals.total_spent')
            ->orderBy('users.id')
            ->limit(self::TOP_CUSTOMERS)
            ->get();
    }

    /**
     * Mặt hàng đang bán còn từ 10 sản phẩm trở xuống (điện thoại và phụ kiện), ít nhất xếp trước.
     *
     * @return array{threshold: int, total: int, outOfStock: int, items: Collection<int, Model>}
     */
    public function lowStock(): array
    {
        $query = fn (string $model) => $model::active()->where('stock', '<=', self::LOW_STOCK_THRESHOLD);

        $total = $query(Product::class)->count() + $query(Accessory::class)->count();
        $outOfStock = $query(Product::class)->where('stock', 0)->count() + $query(Accessory::class)->where('stock', 0)->count();
        $items = $query(Product::class)->orderBy('stock')->orderBy('name')->limit(self::LOW_STOCK_SHOWN)->get()
            ->concat($query(Accessory::class)->orderBy('stock')->orderBy('name')->limit(self::LOW_STOCK_SHOWN)->get())
            ->sortBy([['stock', 'asc'], ['name', 'asc']])
            ->take(self::LOW_STOCK_SHOWN)
            ->values();

        return ['threshold' => self::LOW_STOCK_THRESHOLD, 'total' => $total, 'outOfStock' => $outOfStock, 'items' => $items];
    }

    private function revenueQuery()
    {
        return DB::table('orders')->whereNull('deleted_at')->whereIn('status', self::REVENUE_STATUSES);
    }
}
