@extends('layouts.admin')

@section('title', $meta['title'])

@php
    $formOpen = $creating || $editing;
    $indexUrl = route('admin.'.$resource.'.index');
@endphp

@section('content')
    <div class="admin-page-toolbar">
        <div></div>
        <a class="btn btn-primary" href="{{ route('admin.'.$resource.'.index', ['create' => 1]) }}"><x-icon name="plus" /> Thêm {{ mb_strtolower($meta['singular']) }}</a>
    </div>

    <div class="admin-table-card">
        <div class="admin-table-title"><div><h2>{{ $meta['title'] }}</h2><span>{{ $items->count() }} mục</span></div></div>
        @if ($items->isEmpty())
            @include('partials.empty-state', ['title' => 'Chưa có dữ liệu', 'description' => 'Hãy thêm dữ liệu mới.'])
        @else
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            @foreach ($meta['columns'] as $column)<th>{{ $column }}</th>@endforeach
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                @include('admin.rows.'.$resource, ['item' => $item])
                                <td>
                                    <div class="table-actions">
                                        <a href="{{ route('admin.'.$resource.'.index', ['edit' => $item->id]) }}" aria-label="Sửa"><x-icon name="edit-2" /></a>
                                        <form method="POST" action="{{ route('admin.'.$resource.'.destroy', $item->id) }}" class="inline-form"
                                              data-confirm="Dữ liệu sẽ bị xóa khỏi hệ thống." data-confirm-title="Xóa {{ mb_strtolower($meta['singular']) }}?">
                                            @csrf @method('DELETE')
                                            <button class="danger" aria-label="Xóa"><x-icon name="trash-2" /></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($formOpen)
        @php
            $item = $editing;
            $value = function ($key) use ($item, $defaults) {
                $current = $item?->{$key} ?? ($defaults[$key] ?? '');
                if ($current instanceof \DateTimeInterface) {
                    $current = $current->format('Y-m-d');
                }

                return old($key, $current);
            };
        @endphp
        <dialog class="accessible-dialog admin-form-dialog" aria-label="{{ $item ? 'Chỉnh sửa' : 'Thêm mới' }} {{ $meta['singular'] }}" data-autoshow data-close-url="{{ $indexUrl }}">
            <form class="admin-form-modal simple-form-modal" method="POST" enctype="multipart/form-data"
                  action="{{ $item ? route('admin.'.$resource.'.update', $item->id) : route('admin.'.$resource.'.store') }}">
                @csrf
                @if ($item) @method('PUT') @endif
                <div class="admin-modal-head">
                    <div><span>{{ $item ? 'Chỉnh sửa' : 'Thêm mới' }}</span><h2>{{ $meta['singular'] }}</h2></div>
                    <a href="{{ $indexUrl }}" class="admin-modal-close" aria-label="Đóng"><x-icon name="x" /></a>
                </div>
                <div class="form-grid">
                    @foreach ($fields as $key => $field)
                        @switch($field['type'])
                            @case('checkbox')
                                <label class="admin-checkbox">
                                    <input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $item ? $item->{$key} : ($defaults[$key] ?? false)))>
                                    <span>{{ $field['label'] }}</span>
                                </label>
                                @break
                            @case('image')
                                <div class="form-field full admin-image-field">
                                    <span>{{ $field['label'] }}{{ ! empty($field['required']) ? ' *' : '' }}</span>
                                    <div @class(['admin-image-upload', 'has-image' => $item?->{$key}])>
                                        <div class="admin-image-preview" data-single-image-preview>
                                            @if ($item?->{$key})
                                                <img src="{{ media_url($item->{$key}) }}" alt="Xem trước {{ mb_strtolower($field['label']) }}">
                                            @else
                                                <div><x-icon name="image" /><small>Chưa chọn ảnh</small></div>
                                            @endif
                                        </div>
                                        <div class="admin-image-actions">
                                            <label class="btn btn-light"><x-icon name="upload-cloud" /> {{ $item?->{$key} ? 'Chọn ảnh khác' : 'Chọn ảnh từ máy' }}
                                                <input type="file" name="{{ $key }}_file" accept=".jpg,.jpeg,.png,.webp,.gif" hidden data-single-image-input>
                                            </label>
                                            @if ($item?->{$key} && empty($field['required']))
                                                <label class="admin-image-remove"><input type="checkbox" name="{{ $key }}_remove" value="1"> Xóa ảnh</label>
                                            @endif
                                            <small>JPG, PNG, WEBP hoặc GIF · tối đa 5MB</small>
                                        </div>
                                    </div>
                                    @error($key.'_file')<small>{{ $message }}</small>@enderror
                                </div>
                                @break
                            @default
                                <label @class(['form-field', 'full' => ! empty($field['full'])])>
                                    <span>{{ $field['label'] }}{{ ! empty($field['required']) ? ' *' : '' }}</span>
                                    @if ($field['type'] === 'select')
                                        <select name="{{ $key }}">
                                            @foreach ($field['options'] as $optionValue => $optionLabel)
                                                <option value="{{ $optionValue }}" @selected((string) $value($key) === (string) $optionValue)>{{ $optionLabel }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($field['type'] === 'textarea')
                                        <textarea name="{{ $key }}" rows="3" @required(! empty($field['required']))>{{ $value($key) }}</textarea>
                                    @else
                                        <input type="{{ $field['type'] }}" name="{{ $key }}" value="{{ $value($key) }}" @required(! empty($field['required'])) @if ($field['type'] === 'number') min="0" @endif>
                                    @endif
                                    @error($key)<small>{{ $message }}</small>@enderror
                                </label>
                        @endswitch
                    @endforeach
                </div>
                <div class="admin-modal-actions">
                    <a class="btn btn-light" href="{{ $indexUrl }}">Hủy</a>
                    <button class="btn btn-primary">Lưu dữ liệu</button>
                </div>
            </form>
        </dialog>
    @endif
@endsection
