{{--
    Thông tin người nhận dùng chung cho trang thanh toán và trang tài khoản.
    Từ 01/07/2025 địa chỉ gồm tỉnh/thành + phường/xã (không còn quận/huyện): danh sách phường/xã
    gợi ý theo tỉnh đã chọn (public/data/vn-wards.json), vẫn gõ tay được nếu không tải được danh sách.
    Tham số: $values (giá trị mặc định), $prefix (tiền tố id), $provinces.
--}}
@php
    $value = fn ($name) => old($name, $values[$name] ?? '');
    $listId = $prefix.'-ward-options';
@endphp
<div class="form-grid" data-address-fields data-wards-url="{{ asset(\App\Support\WardDirectory::DATA_FILE) }}">
    <label class="form-field">
        <span>Họ và tên *</span>
        <input name="full_name" autocomplete="name" value="{{ $value('full_name') }}" @error('full_name') aria-invalid="true" @enderror>
        @error('full_name')<small>{{ $message }}</small>@enderror
    </label>
    <label class="form-field">
        <span>Số điện thoại *</span>
        <input type="tel" name="phone" autocomplete="tel" value="{{ $value('phone') }}" @error('phone') aria-invalid="true" @enderror data-checkout-phone="1">
        @error('phone')<small>{{ $message }}</small>@enderror
    </label>
    <label class="form-field full">
        <span>Email *</span>
        <input type="email" name="email" autocomplete="email" value="{{ $value('email') }}" @error('email') aria-invalid="true" @enderror>
        @error('email')<small>{{ $message }}</small>@enderror
    </label>
    <label class="form-field">
        <span>Tỉnh/thành phố *</span>
        <select name="province" data-province @error('province') aria-invalid="true" @enderror>
            <option value="">Chọn tỉnh/thành phố</option>
            @foreach ($provinces as $province)
                <option value="{{ $province }}" @selected($value('province') === $province)>{{ $province }}</option>
            @endforeach
        </select>
        @error('province')<small>{{ $message }}</small>@enderror
    </label>
    <label class="form-field">
        <span>Phường/xã/đặc khu *</span>
        <input name="ward" value="{{ $value('ward') }}" list="{{ $listId }}" autocomplete="off" data-ward-input
               placeholder="Gõ để tìm, ví dụ: Sài Gòn" @error('ward') aria-invalid="true" @enderror>
        <datalist id="{{ $listId }}" data-ward-options>
            @foreach (\App\Support\WardDirectory::wardsOf($value('province')) as $ward)
                <option value="{{ $ward }}"></option>
            @endforeach
        </datalist>
        @error('ward')<small>{{ $message }}</small>@else<small class="field-hint" data-ward-hint hidden></small>@enderror
    </label>
    <label class="form-field full">
        <span>Số nhà, tên đường *</span>
        <input name="address" autocomplete="street-address" value="{{ $value('address') }}" placeholder="Ví dụ: 12 Lê Lợi" @error('address') aria-invalid="true" @enderror>
        @error('address')<small>{{ $message }}</small>@enderror
    </label>
</div>
