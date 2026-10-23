<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Lỗi nghiệp vụ (hết hàng, voucher không hợp lệ, sai trạng thái đơn...).
 * Trên giao diện web được hiển thị thành thông báo lỗi và quay lại trang trước.
 */
class BusinessException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $this->getMessage()], $this->status);
        }

        return back()->withInput()->with('toast', ['type' => 'error', 'message' => $this->getMessage()]);
    }
}
