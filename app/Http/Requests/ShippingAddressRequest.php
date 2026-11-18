<?php

namespace App\Http\Requests;

use App\Rules\VietnamesePhoneRule;
use App\Services\ShippingService;
use App\Support\WardDirectory;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Sửa địa chỉ nhận hàng đã lưu ở trang tài khoản. */
class ShippingAddressRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $official = WardDirectory::match($this->input('province'), $this->input('ward'));
        if ($official) {
            $this->merge(['ward' => $official]);
        }
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', new VietnamesePhoneRule],
            'email' => ['required', 'email', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'province' => ['required', Rule::in(ShippingService::PROVINCES)],
            'ward' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, Closure $fail) {
                if (WardDirectory::wardsOf($this->input('province')) && ! WardDirectory::match($this->input('province'), $value)) {
                    $fail('Chọn phường/xã/đặc khu có trong danh sách của tỉnh/thành đã chọn');
                }
            }],
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
