@extends('layouts.admin')

@php
    $isProduct = $kind === 'product';
    $noun = $isProduct ? 'sản phẩm' : 'phụ kiện';
    $route = $isProduct ? 'admin.products' : 'admin.accessories';
    $formOpen = $creating || $editing;
@endphp

@section('title', $isProduct ? 'Quản lý sản phẩm' : 'Quản lý phụ kiện')

@section('content')
    <form class="admin-page-toolbar" method="GET" action="{{ route($route.'.index') }}">
        <div class="admin-search"><x-icon name="search" /><input name="search" value="{{ request('search') }}" placeholder="Tìm {{ $noun }}..."></div>
        <select name="brand" data-auto-submit>
            <option value="">Tất cả thương hiệu</option>
            @foreach ($brands as $brand)
                <option value="{{ $brand->id }}" @selected((string) request('brand') === (string) $brand->id)>{{ $brand->name }}</option>
            @endforeach
        </select>
        <select name="status" data-auto-submit>
            <option value="">Tất cả trạng thái</option>
            <option value="active" @selected(request('status') === 'active')>Đang bán</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Ngừng bán</option>
        </select>
        <a class="btn btn-primary" href="{{ route($route.'.index', ['create' => 1]) }}"><x-icon name="plus" /> Thêm mới</a>
    </form>

    <div class="admin-table-card">
        <div class="admin-table-title"><div><h2>Danh sách {{ $noun }}</h2><span>{{ $items->total() }} mục</span></div></div>
        @if ($items->isEmpty())
            @include('partials.empty-state', ['title' => 'Chưa có dữ liệu', 'description' => 'Hãy thay đổi bộ lọc hoặc thêm dữ liệu mới.'])
        @else
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>{{ $isProduct ? 'Sản phẩm' : 'Phụ kiện' }}</th><th>Thương hiệu</th><th>Giá bán</th><th>Tồn kho</th><th>Đã bán</th><th>Trạng thái</th><th class="table-actions-cell">Thao tác</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td><div class="table-product"><img src="{{ media_url($item->image) }}" alt=""><span><strong>{{ $item->name }}</strong><small>#{{ $item->id }} · {{ $item->categoryName() }}</small></span></div></td>
                                <td>{{ $item->brandName() }}</td>
                                <td><strong>{{ money($item->price) }}</strong></td>
                                <td><span @class(['stock-low' => $item->stock < 10])>{{ $item->stock }}</span></td>
                                <td>{{ $item->sold }}</td>
                                <td><span class="status-badge {{ $item->status === 'active' ? 'status-completed' : 'status-cancelled' }}">{{ $item->status === 'active' ? 'Đang bán' : 'Ngừng bán' }}</span></td>
                                <td class="table-actions-cell">
                                    <div class="table-actions">
                                        <a href="{{ route($route.'.index', array_merge(request()->except('create'), ['edit' => $item->id])) }}" aria-label="Sửa {{ $item->name }}"><x-icon name="edit-2" /></a>
                                        <form method="POST" action="{{ route($route.'.destroy', $item->id) }}" class="inline-form" data-confirm="Mục này sẽ bị xóa khỏi danh sách quản trị và cửa hàng." data-confirm-title="Xóa dữ liệu này?">
                                            @csrf @method('DELETE')
                                            <button class="danger" aria-label="Xóa {{ $item->name }}"><x-icon name="trash-2" /></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $items->links() }}
        @endif
    </div>

    @if ($formOpen)
        @php
            $item = $editing;
            $value = fn ($key, $default = '') => old($key, $item?->{$key} ?? $default);
            $images = old('keep_images', $item?->galleryImages() ?? []);
        @endphp
        <dialog class="accessible-dialog admin-form-dialog" aria-label="{{ $item ? 'Chỉnh sửa '.$item->name : 'Thêm '.$noun }}" data-autoshow data-close-url="{{ route($route.'.index', request()->except(['create', 'edit'])) }}">
            <form class="admin-form-modal" method="POST" enctype="multipart/form-data" action="{{ $item ? route($route.'.update', $item->id) : route($route.'.store') }}">
                @csrf
                @if ($item) @method('PUT') @endif
                <div class="admin-modal-head">
                    <div><span>{{ $item ? 'Chỉnh sửa dữ liệu' : 'Tạo dữ liệu mới' }}</span><h2>{{ $item ? $item->name : 'Thêm '.$noun }}</h2></div>
                    <a href="{{ route($route.'.index', request()->except(['create', 'edit'])) }}" class="admin-modal-close" aria-label="Đóng"><x-icon name="x" /></a>
                </div>
                <div class="form-grid">
                    <label class="form-field full"><span>Tên {{ $noun }} *</span><input name="name" required value="{{ $value('name') }}">@error('name')<small>{{ $message }}</small>@enderror</label>
                    <label class="form-field">
                        <span>Thương hiệu *</span>
                        <select name="brand_id" required>
                            <option value="">Chọn thương hiệu</option>
                            @foreach ($brands->where('active', true) as $brand)
                                <option value="{{ $brand->id }}" @selected((string) $value('brand_id') === (string) $brand->id)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                        @error('brand_id')<small>{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field">
                        <span>Danh mục *</span>
                        <select name="category_id" required>
                            <option value="">Chọn danh mục</option>
                            @foreach ($categories->where('active', true) as $category)
                                <option value="{{ $category->id }}" @selected((string) $value('category_id', $isProduct ? $categories->firstWhere('name', 'Điện thoại')?->id : '') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')<small>{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field"><span>Giá bán *</span><input type="number" min="0" name="price" required value="{{ $value('price') }}">@error('price')<small>{{ $message }}</small>@enderror</label>
                    <label class="form-field"><span>Giá cũ</span><input type="number" min="0" name="old_price" value="{{ $value('old_price') }}"></label>
                    <label class="form-field"><span>Tồn kho *</span><input type="number" min="0" name="stock" required value="{{ $value('stock', 10) }}">@error('stock')<small>{{ $message }}</small>@enderror</label>
                    <label class="form-field">
                        <span>Trạng thái</span>
                        <select name="status">
                            <option value="active" @selected($value('status', 'active') === 'active')>Đang bán</option>
                            <option value="inactive" @selected($value('status') === 'inactive')>Ngừng bán</option>
                        </select>
                    </label>
                    @if ($isProduct)
                        @foreach (['ram' => ['RAM', '8GB'], 'storage' => ['Bộ nhớ', '256GB'], 'screen' => ['Màn hình', '6.7 inch OLED, 120Hz'], 'battery' => ['Pin', '5000 mAh'], 'camera' => ['Camera', '50MP'], 'chip' => ['Chip', 'Chip 8 nhân hiệu năng cao']] as $key => [$label, $default])
                            <label class="form-field"><span>{{ $label }}</span><input name="{{ $key }}" value="{{ $value($key, $item ? '' : $default) }}"></label>
                        @endforeach
                    @endif

                    <div class="form-field full admin-image-field">
                        <span>Ảnh {{ $noun }} (tối đa 5 ảnh, ảnh đầu tiên là ảnh đại diện)</span>
                        <div class="admin-image-upload multiple has-image">
                            <div class="admin-image-grid" data-image-manager>
                                @forelse ($images as $image)
                                    <article @class(['primary' => $loop->first])>
                                        <img src="{{ media_url($image) }}" alt="Ảnh {{ $loop->iteration }}">
                                        <input type="hidden" name="keep_images[]" value="{{ $image }}">
                                        <label class="image-primary-choice"><input type="radio" name="primary_image" value="{{ $image }}" @checked($loop->first)> Ảnh đại diện</label>
                                        <div><button type="button" class="danger" data-remove-image><x-icon name="trash-2" /> Xóa</button></div>
                                    </article>
                                @empty
                                    <div class="admin-image-empty"><x-icon name="image" /><small>Chưa chọn ảnh</small></div>
                                @endforelse
                            </div>
                            <div class="admin-image-actions">
                                <label class="btn btn-light"><x-icon name="upload-cloud" /> Thêm ảnh từ máy
                                    <input type="file" name="new_images[]" accept=".jpg,.jpeg,.png,.webp,.gif" multiple hidden data-image-input>
                                </label>
                                <small data-image-input-label>JPG, PNG, WEBP hoặc GIF · mỗi ảnh tối đa 5MB</small>
                            </div>
                        </div>
                        @error('new_images.*')<small>{{ $message }}</small>@enderror
                    </div>

                    <label class="form-field full"><span>Mô tả *</span><textarea name="description" rows="4" required>{{ $value('description') }}</textarea>@error('description')<small>{{ $message }}</small>@enderror</label>
                </div>
                <div class="admin-modal-actions">
                    <a class="btn btn-light" href="{{ route($route.'.index', request()->except(['create', 'edit'])) }}">Hủy</a>
                    <button class="btn btn-primary">{{ $item ? 'Lưu thay đổi' : 'Thêm mới' }}</button>
                </div>
            </form>
        </dialog>
    @endif
@endsection
