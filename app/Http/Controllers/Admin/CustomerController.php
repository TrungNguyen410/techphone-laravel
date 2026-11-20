<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Voucher;
use App\Rules\VietnamesePhoneRule;
use App\Services\CustomerAccountService;
use App\Services\VoucherService;
use App\Support\VietnamesePhone;
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

        return view('admin.customers.index', [
            'customers' => $query->paginate(20)->withQueryString(),
            'editing' => is_numeric($request->query('edit')) ? User::customers()->find((int) $request->query('edit')) : null,
        ]);
    }

    /**
     * Sửa thông tin tài khoản khách hàng: họ tên, email, số điện thoại, địa chỉ, vai trò và trạng thái.
     * Chỉ áp dụng cho tài khoản khách hàng (không sửa tài khoản quản trị khác tại đây).
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isAdmin(), 404, 'Không tìm thấy khách hàng');

        $request->merge(['phone' => VietnamesePhone::normalize((string) $request->input('phone')) ?? $request->input('phone')]);
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user)],
            // Tài khoản Google/Facebook có thể chưa có số điện thoại.
            'phone' => [$user->isSocialAccount() ? 'nullable' : 'required', new VietnamesePhoneRule, Rule::unique('users', 'phone')->ignore($user)],
            'address' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(['customer', 'admin'])],
            'status' => ['required', Rule::in(array_keys(User::STATUSES))],
        ], [
            'required' => 'Vui lòng nhập thông tin này',
            'full_name.max' => 'Họ và tên tối đa 100 ký tự',
            'email.email' => 'Email không hợp lệ',
            'email.unique' => 'Email này đã được dùng cho tài khoản khác',
            'phone.unique' => 'Số điện thoại này đã được đăng ký',
            'role.in' => 'Vai trò người dùng không hợp lệ',
            'status.in' => 'Trạng thái khách hàng không hợp lệ',
        ]);

        $phone = $data['phone'] ?: null;
        $user->forceFill([
            'full_name' => trim($data['full_name']),
            'email' => filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null,
            'phone' => $phone,
            'address' => trim((string) ($data['address'] ?? '')),
            'role' => $data['role'],
            'status' => $data['status'],
            // Số mới chưa được xác minh nên bỏ dấu "đã xác minh" của số cũ.
            'phone_verified_at' => $phone === $user->phone ? $user->phone_verified_at : null,
        ])->save();

        return redirect()->route('admin.customers.index', $request->only('search', 'page'))
            ->with('toast', ['type' => 'success', 'message' => $user->isAdmin()
                ? "Đã cấp quyền quản trị cho {$user->full_name}"
                : "Đã cập nhật khách hàng {$user->full_name}"]);
    }

    /**
     * Xóa tài khoản theo yêu cầu của khách: xóa dữ liệu cá nhân, ẩn danh đơn hàng/đánh giá, giải phóng SĐT và email.
     */
    public function destroy(Request $request, User $user, CustomerAccountService $accounts): RedirectResponse
    {
        $name = $user->full_name;
        $accounts->delete($user);

        return redirect()->route('admin.customers.index', $request->only('search', 'page'))
            ->with('toast', ['type' => 'success', 'message' => "Đã xóa tài khoản {$name} và dữ liệu cá nhân"]);
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
