<?php

namespace App\Http\Requests;

/**
 * Quản trị viên sửa thông tin người nhận và ghi chú của đơn (giá, phí ship và sản phẩm không đổi).
 * Email người nhận không bắt buộc, giống lúc khách đặt hàng.
 */
class AdminOrderCustomerRequest extends ShippingAddressRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'email' => ['nullable', 'email', 'max:150'],
            'order_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
