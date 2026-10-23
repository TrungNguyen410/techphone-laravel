<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * Danh sách khách hàng kèm số đơn và tổng chi tiêu (chỉ tính đơn đã giao/hoàn thành).
     */
    public function index(Request $request): View
    {
        $query = User::customers()
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn (Builder $orders) => $orders->whereIn('status', ['delivered', 'completed'])], 'total')
            ->latest();

        if ($search = mb_substr(trim((string) $request->query('search')), 0, 100)) {
            $query->where(fn (Builder $inner) => $inner
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }

        return view('admin.customers.index', ['customers' => $query->paginate(20)->withQueryString()]);
    }

    public function toggleLock(User $user): RedirectResponse
    {
        abort_if($user->isAdmin(), 404, 'Không tìm thấy khách hàng');

        $user->update(['status' => $user->status === 'active' ? 'locked' : 'active']);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $user->status === 'active' ? 'Đã mở khóa tài khoản' : 'Đã khóa tài khoản',
        ]);
    }
}
