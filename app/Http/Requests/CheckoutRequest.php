<?php

namespace App\Http\Requests;

use App\Rules\VietnamesePhoneRule;
use App\Services\ShippingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', new VietnamesePhoneRule],
            'email' => ['required', 'email', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'province' => ['required', Rule::in(ShippingService::PROVINCES)],
            'district' => ['nullable', 'string', 'max:100'],
            'ward' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::in(['cod', 'bank', 'momo', 'card'])],
            'checkout_key' => ['required', 'string', 'max:120'],
            'transfer_content' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập thông tin này',
            'email.email' => 'Email không đúng định dạng',
            'province.in' => 'Vui lòng chọn tỉnh/thành phố',
        ];
    }
}
