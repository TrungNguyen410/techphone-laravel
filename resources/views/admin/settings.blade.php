@extends('layouts.admin')

@section('title', 'Cài đặt hệ thống')

@section('content')
    <form class="settings-layout" method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        <section class="admin-card settings-card">
            <div class="settings-heading"><x-icon name="settings" /><div><h2>Thông tin cửa hàng</h2><p>Thông tin hiển thị trên website và hóa đơn.</p></div></div>
            <div class="form-grid">
                <label class="form-field full"><span>Tên cửa hàng</span><input name="storeName" value="{{ old('storeName', $settings['storeName']) }}" required>@error('storeName')<small>{{ $message }}</small>@enderror</label>
                <label class="form-field"><span><x-icon name="phone" /> Hotline</span><input name="hotline" value="{{ old('hotline', $settings['hotline']) }}" required>@error('hotline')<small>{{ $message }}</small>@enderror</label>
                <label class="form-field"><span><x-icon name="mail" /> Email</span><input name="email" type="email" value="{{ old('email', $settings['email']) }}" required>@error('email')<small>{{ $message }}</small>@enderror</label>
                <label class="form-field full"><span><x-icon name="map-pin" /> Địa chỉ</span><textarea name="address" rows="3" required>{{ old('address', $settings['address']) }}</textarea>@error('address')<small>{{ $message }}</small>@enderror</label>
                <div class="form-field full admin-image-field">
                    <span>Logo cửa hàng</span>
                    <div @class(['admin-image-upload', 'has-image' => $settings['logo']])>
                        <div class="admin-image-preview" data-single-image-preview>
                            @if ($settings['logo'])
                                <img src="{{ media_url($settings['logo']) }}" alt="Xem trước logo cửa hàng">
                            @else
                                <div><x-icon name="image" /><small>Chưa chọn ảnh</small></div>
                            @endif
                        </div>
                        <div class="admin-image-actions">
                            <label class="btn btn-light"><x-icon name="upload-cloud" /> {{ $settings['logo'] ? 'Chọn ảnh khác' : 'Chọn ảnh từ máy' }}
                                <input type="file" name="logo_file" accept=".jpg,.jpeg,.png,.webp,.gif" hidden data-single-image-input>
                            </label>
                            @if ($settings['logo'])
                                <label class="admin-image-remove"><input type="checkbox" name="logo_remove" value="1"> Xóa logo</label>
                            @endif
                            <small>JPG, PNG, WEBP hoặc GIF · tối đa 5MB</small>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="admin-card settings-card">
            <div class="settings-heading"><x-icon name="globe" /><div><h2>Mạng xã hội</h2><p>Các kênh chính thức của {{ $settings['storeName'] }}.</p></div></div>
            <div class="form-grid">
                @foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'zaloUrl' => 'Zalo URL (tùy chọn)'] as $key => $label)
                    <label class="form-field full">
                        <span>{{ $label }}</span>
                        <input name="{{ $key }}" type="url" value="{{ old($key, $settings[$key]) }}" @if ($key === 'zaloUrl') placeholder="https://zalo.me/..." @endif>
                        @error($key)<small>{{ $message }}</small>@enderror
                    </label>
                @endforeach
            </div>
        </section>
        <div class="settings-actions">
            <button class="btn btn-primary"><x-icon name="save" /> Lưu cài đặt</button>
        </div>
    </form>
@endsection
