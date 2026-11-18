<?php

namespace App\Http\Requests;

use App\Rules\VietnamesePhoneRule;
use App\Services\ShippingService;
use App\Support\WardDirectory;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * "Giao đến địa chỉ đã lưu" (address_mode = saved): lấy thông tin người nhận từ tài khoản trên máy chủ,
     * không tin dữ liệu gửi lên. Tên phường/xã khách gõ tắt ("sai gon") được đổi về tên chính thức.
     */
    protected function prepareForValidation(): void
    {
        $saved = $this->user()?->savedShippingAddress();
        if ($this->usesSavedAddress() && $saved) {
            $this->merge($saved);
        }

        $official = WardDirectory::match($this->input('province'), $this->input('ward'));
        if ($official) {
            $this->merge(['ward' => $official]);
        }
    }

    public function rules(): array
    {
        return [
            'address_mode' => ['nullable', Rule::in(['saved', 'new'])],
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', new VietnamesePhoneRule],
            'email' => ['required', 'email', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'province' => ['required', Rule::in(ShippingService::PROVINCES)],
            'ward' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, Closure $fail) {
                // Chỉ kiểm tra khi có danh sách phường/xã của tỉnh đã chọn (thiếu file dữ liệu thì vẫn cho nhập tay).
                if (WardDirectory::wardsOf($this->input('province')) && ! WardDirectory::match($this->input('province'), $value)) {
                    $fail('Chọn phường/xã/đặc khu có trong danh sách của tỉnh/thành đã chọn');
                }
            }],
            'save_address' => ['nullable', 'boolean'],
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

    public function usesSavedAddress(): bool
    {
        return $this->input('address_mode') === 'saved';
    }

    /** @return array{full_name: string, email: string, phone: string, address: string, province: string, ward: string} */
    public function customer(): array
    {
        return $this->only(['full_name', 'email', 'phone', 'address', 'province', 'ward']);
    }
}
