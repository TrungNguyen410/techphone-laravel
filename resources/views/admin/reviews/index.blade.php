@extends('layouts.admin')

@section('title', 'Quản lý đánh giá')

@section('content')
    <form class="admin-page-toolbar" method="GET" action="{{ route('admin.reviews.index') }}">
        <select name="status" data-auto-submit>
            <option value="">Tất cả trạng thái</option>
            @foreach (App\Models\Review::STATUSES as $status => $label)
                <option value="{{ $status }}" @selected($filter === $status)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
    <div class="admin-table-card">
        <div class="admin-table-title"><div><h2>Danh sách đánh giá</h2><span>{{ $reviews->total() }} đánh giá</span></div></div>
        @if ($reviews->isEmpty())
            @include('partials.empty-state', ['title' => 'Chưa có dữ liệu', 'description' => 'Hãy thay đổi bộ lọc.'])
        @else
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead><tr><th>Khách hàng</th><th>Mặt hàng</th><th>Đánh giá</th><th>Nội dung</th><th>Ảnh</th><th>Ngày gửi</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                    <tbody>
                        @foreach ($reviews as $review)
                            <tr>
                                <td><strong>{{ $review->user_name }}</strong>@if ($review->verified_purchase)<small class="table-subtext">Đã mua hàng</small>@endif</td>
                                <td>{{ $review->targetName() }}</td>
                                <td><span class="table-rating">{{ $review->rating }} <x-icon name="star" /></span></td>
                                <td><p class="review-comment-cell">{{ $review->comment }}</p></td>
                                <td><div class="review-admin-images">@foreach (array_slice($review->images ?? [], 0, 3) as $image)<img src="{{ media_url($image) }}" alt="">@endforeach</div></td>
                                <td>{{ format_date($review->created_at) }}</td>
                                <td><span class="review-status {{ $review->status }}">{{ App\Models\Review::STATUSES[$review->status] }}</span></td>
                                <td>
                                    <div class="review-admin-actions">
                                        <a class="edit" href="{{ route('admin.reviews.index', [...request()->only('status', 'page'), 'edit' => $review->id]) }}" aria-label="Sửa đánh giá" title="Sửa đánh giá"><x-icon name="edit-2" /></a>
                                        @if ($review->status !== 'approved')
                                            <form method="POST" action="{{ route('admin.reviews.moderate', [$review, 'approved']) }}" class="inline-form">
                                                @csrf @method('PATCH')
                                                <button class="approve" aria-label="Duyệt đánh giá"><x-icon name="check" /></button>
                                            </form>
                                        @endif
                                        @if ($review->status !== 'rejected')
                                            <form method="POST" action="{{ route('admin.reviews.moderate', [$review, 'rejected']) }}" class="inline-form">
                                                @csrf @method('PATCH')
                                                <button class="reject" aria-label="Từ chối đánh giá"><x-icon name="x" /></button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" class="inline-form" data-confirm="Đánh giá sẽ không còn hiển thị trong hệ thống." data-confirm-title="Xóa đánh giá?">
                                            @csrf @method('DELETE')
                                            <button class="delete" aria-label="Xóa đánh giá"><x-icon name="trash-2" /></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $reviews->links() }}
        @endif
    </div>

    @if ($editing)
        @php $closeUrl = route('admin.reviews.index', request()->only('status', 'page')); @endphp
        <dialog class="accessible-dialog admin-form-dialog" aria-label="Sửa đánh giá" data-autoshow data-close-url="{{ $closeUrl }}">
            <form class="admin-form-modal simple-form-modal" method="POST" action="{{ route('admin.reviews.update', $editing) }}">
                @csrf @method('PUT')
                <input type="hidden" name="status_filter" value="{{ $filter }}">
                <div class="admin-modal-head">
                    <div><span>Sửa đánh giá · {{ $editing->user_name }}</span><h2>{{ $editing->targetName() }}</h2></div>
                    <a href="{{ $closeUrl }}" class="admin-modal-close" aria-label="Đóng"><x-icon name="x" /></a>
                </div>
                <div class="form-grid">
                    <label class="form-field">
                        <span>Số sao</span>
                        <select name="rating">
                            @for ($star = 5; $star >= 1; $star--)
                                <option value="{{ $star }}" @selected((int) old('rating', $editing->rating) === $star)>{{ $star }} sao</option>
                            @endfor
                        </select>
                        @error('rating')<small>{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field">
                        <span>Trạng thái</span>
                        <select name="status">
                            @foreach (App\Models\Review::STATUSES as $status => $label)
                                <option value="{{ $status }}" @selected(old('status', $editing->status) === $status)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<small>{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field full">
                        <span>Nội dung *</span>
                        <textarea name="comment" rows="4" minlength="10" maxlength="2000" required>{{ old('comment', $editing->comment) }}</textarea>
                        @error('comment')<small>{{ $message }}</small>@enderror
                    </label>
                    @if (! empty($editing->images))
                        <div class="form-field full">
                            <span>Ảnh đính kèm (tick để gỡ ảnh)</span>
                            <div class="review-admin-edit-images">
                                @foreach ($editing->images as $image)
                                    <label><img src="{{ media_url($image) }}" alt=""><input type="checkbox" name="remove_images[]" value="{{ $image }}"> Gỡ</label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                <div class="admin-modal-actions">
                    <a class="btn btn-light" href="{{ $closeUrl }}">Hủy</a>
                    <button class="btn btn-primary">Lưu đánh giá</button>
                </div>
            </form>
        </dialog>
    @endif
@endsection
