<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    /**
     * Tặng voucher dùng 1 lần cho khách (khách xem ở Tài khoản → Voucher của tôi).
     */
    public function giftVoucher(Request $request, User $user, VoucherService $vouchers): RedirectResponse
    {
        abort_if($user->isAdmin(), 404, 'Không tìm thấy khách hàng');

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Voucher::TYPES))],
            'value' => ['required', 'integer', 'min:1', Rule::when($request->input('type') === 'percent', 'max:100')],
            'max_discount' => ['nullable', 'integer', 'min:0'],
            'min_order' => ['nullable', 'integer', 'min:0'],
            'end_date' => ['required', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:120'],
        ], [
            'value.required' => 'Vui lòng nhập giá trị giảm',
            'value.min' => 'Giá trị giảm phải lớn hơn 0',
            'value.max' => 'Giảm theo phần trăm tối đa 100%',
            'end_date.required' => 'Vui lòng chọn hạn dùng',
            'end_date.after_or_equal' => 'Hạn dùng phải từ hôm nay trở đi',
            'min' => 'Không được nhỏ hơn :min',
        ]);

        $voucher = $vouchers->giftToCustomer($user, $data);

        return redirect()->to($this->backToDashboard($request))
            ->with('gifted_voucher', $voucher->id)
            ->with('toast', ['type' => 'success', 'message' => "Đã tặng mã {$voucher->code} cho {$user->full_name}"]);
    }

    /** Quay về đúng trang/kỳ thống kê đang xem, bỏ tham số mở hộp thoại tặng voucher. */
    private function backToDashboard(Request $request): string
    {
        $back = (string) $request->input('return_to');
        if (! str_starts_with($back, url('/admin'))) {
            return route('admin.dashboard');
        }
        $parts = parse_url($back);
        parse_str($parts['query'] ?? '', $query);
        unset($query['gift']);

        return url($parts['path'] ?? '/admin/dashboard').($query ? '?'.http_build_query($query) : '');
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
