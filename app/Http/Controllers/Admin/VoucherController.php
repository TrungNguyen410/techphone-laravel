<?php

namespace App\Http\Controllers\Admin;

use App\Models\Voucher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class VoucherController extends SimpleCrudController
{
    protected function modelClass(): string
    {
        return Voucher::class;
    }

    protected function resource(): string
    {
        return 'vouchers';
    }

    protected function meta(): array
    {
        return [
            'title' => 'Danh sách voucher',
            'singular' => 'Voucher',
            'columns' => ['Mã', 'Loại', 'Giá trị', 'Đơn tối thiểu', 'Đã dùng', 'Hiệu lực', 'Trạng thái'],
        ];
    }

    protected function fields(): array
    {
        return [
            'code' => ['label' => 'Mã voucher', 'type' => 'text', 'required' => true],
            'type' => ['label' => 'Loại giảm', 'type' => 'select', 'options' => Voucher::TYPES],
            'value' => ['label' => 'Giá trị', 'type' => 'number', 'required' => true],
            'min_order' => ['label' => 'Đơn tối thiểu', 'type' => 'number', 'required' => true],
            'max_discount' => ['label' => 'Giảm tối đa', 'type' => 'number', 'required' => true],
            'quantity' => ['label' => 'Số lượng (0 = không giới hạn)', 'type' => 'number', 'required' => true],
            'start_date' => ['label' => 'Ngày bắt đầu', 'type' => 'date', 'required' => true],
            'end_date' => ['label' => 'Ngày kết thúc', 'type' => 'date', 'required' => true],
            'active' => ['label' => 'Đang hoạt động', 'type' => 'checkbox'],
        ];
    }

    protected function defaults(): array
    {
        return [
            'type' => 'percent', 'value' => 10, 'min_order' => 0, 'max_discount' => 0, 'quantity' => 100,
            'start_date' => now()->startOfYear()->toDateString(), 'end_date' => now()->endOfYear()->toDateString(), 'active' => true,
        ];
    }

    protected function rules(?Model $item): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('vouchers', 'code')->ignore($item)->whereNull('deleted_at')],
            'type' => ['required', Rule::in(array_keys(Voucher::TYPES))],
            'value' => ['required', 'integer', 'min:0', Rule::when(request('type') === 'percent', 'max:100')],
            'min_order' => ['required', 'integer', 'min:0'],
            'max_discount' => ['required', 'integer', 'min:0'],
            'quantity' => ['required', 'integer', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'active' => ['nullable'],
        ];
    }

    protected function prepare(array $data, ?Model $item): array
    {
        return [...$data, 'code' => strtoupper($data['code'])];
    }
}
