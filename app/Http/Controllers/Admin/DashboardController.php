<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Voucher;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        $year = (int) $request->query('year', now()->year);
        $year = $year >= 2000 && $year <= 2100 ? $year : now()->year;
        $month = (int) $request->query('month', now()->month);
        $month = $month >= 1 && $month <= 12 ? $month : now()->month;

        $data = $dashboard->statistics($year, $month);

        return view('admin.dashboard', $data + [
            'year' => $year,
            'month' => $month,
            // ?gift={id}: mở hộp thoại tặng voucher cho một khách trong danh sách top khách hàng.
            'giftCustomer' => $request->filled('gift')
                ? ($data['topCustomers']->firstWhere('id', (int) $request->query('gift'))
                    ?? User::customers()->find((int) $request->query('gift')))
                : null,
            'giftedVoucher' => session('gifted_voucher') ? Voucher::with('assignedUser')->find(session('gifted_voucher')) : null,
        ]);
    }
}
