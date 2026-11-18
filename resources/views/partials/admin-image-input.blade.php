{{--
    Ô chọn 1 ảnh trong trang quản trị: tải ảnh từ máy tính (bấm chọn hoặc kéo thả vào khung) hoặc dán URL ảnh.
    Tham số: $name (gửi lên {name}_file / {name}_url / {name}_remove), $label, $current (đường dẫn ảnh hiện tại),
    $required (bắt buộc khi chưa có ảnh), $removable (cho phép xóa ảnh hiện tại).
--}}
@php
    $required = $required ?? false;
    $removable = $removable ?? ! $required;
    $inputId = 'image-'.$name.'-'.\Illuminate\Support\Str::random(4);
@endphp
<div class="form-field full admin-image-field">
    <span>{{ $label }}{{ $required ? ' *' : '' }}</span>
    <div @class(['admin-image-upload', 'has-image' => $current]) data-image-drop>
        <div class="admin-image-preview" data-single-image-preview>
            @if ($current)
                <img src="{{ media_url($current) }}" alt="Xem trước {{ mb_strtolower($label) }}">
            @else
                <div><x-icon name="image" /><small>Chưa chọn ảnh</small></div>
            @endif
        </div>
        <div class="admin-image-actions">
            <label class="btn btn-light" for="{{ $inputId }}"><x-icon name="upload-cloud" /> {{ $current ? 'Chọn ảnh khác từ máy tính' : 'Tải ảnh lên từ máy tính' }}</label>
            <input id="{{ $inputId }}" type="file" name="{{ $name }}_file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif" data-single-image-input>
            <small data-image-file-label>Hoặc kéo thả ảnh vào khung này · JPG, PNG, WEBP, GIF · tối đa 5MB</small>
            <div class="admin-image-url-input">
                <label for="{{ $inputId }}-url">Hoặc dùng URL ảnh</label>
                <div>
                    <input id="{{ $inputId }}-url" type="url" name="{{ $name }}_url" inputmode="url" value="{{ old($name.'_url') }}"
                           placeholder="https://cdn.example.com/anh.jpg" data-image-url-input>
                </div>
            </div>
            @if ($current && $removable)
                <label class="admin-image-remove"><input type="checkbox" name="{{ $name }}_remove" value="1"> Xóa ảnh</label>
            @endif
        </div>
    </div>
    @error($name.'_file')<small>{{ $message }}</small>@enderror
    @error($name.'_url')<small>{{ $message }}</small>@enderror
</div>
