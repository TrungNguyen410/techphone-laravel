@extends('layouts.admin')

@section('title', 'Liên hệ hỗ trợ')

@section('content')
    <form class="admin-page-toolbar" method="GET" action="{{ route('admin.contacts.index') }}">
        <select name="status" data-auto-submit>
            <option value="">Tất cả trạng thái</option>
            @foreach (App\Models\Contact::STATUSES as $status => $label)
                <option value="{{ $status }}" @selected($filter === $status)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
    <div class="admin-table-card">
        <div class="admin-table-title"><div><h2>Yêu cầu liên hệ</h2><span>{{ $contacts->count() }} yêu cầu · {{ $unread }} chưa đọc</span></div></div>
        @if ($contacts->isEmpty())
            @include('partials.empty-state', ['title' => 'Chưa có yêu cầu liên hệ', 'description' => 'Hãy thay đổi bộ lọc.'])
        @else
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead><tr><th>Khách hàng</th><th>Chủ đề</th><th>Ngày gửi</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                    <tbody>
                        @foreach ($contacts as $contact)
                            <tr>
                                <td><span><strong>{{ $contact->full_name }}</strong><small class="table-subtext">{{ $contact->phone }}</small></span></td>
                                <td><strong>{{ $contact->subject }}</strong></td>
                                <td>{{ format_date($contact->created_at) }}</td>
                                <td><span class="contact-status {{ $contact->status }}">{{ $contact->statusLabel() }}</span></td>
                                <td>
                                    <div class="review-admin-actions">
                                        <a class="approve" href="{{ route('admin.contacts.index', array_merge(request()->only('status'), ['view' => $contact->id])) }}" aria-label="Xem liên hệ"><x-icon name="eye" /></a>
                                        <form method="POST" action="{{ route('admin.contacts.destroy', $contact) }}" class="inline-form" data-confirm="Yêu cầu này sẽ bị xóa khỏi hệ thống." data-confirm-title="Xóa yêu cầu liên hệ?">
                                            @csrf @method('DELETE')
                                            <button class="delete" aria-label="Xóa liên hệ"><x-icon name="trash-2" /></button>
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

    @if ($selected)
        @php $closeUrl = route('admin.contacts.index', request()->only('status')); @endphp
        <dialog class="accessible-dialog contact-detail-modal" aria-label="Liên hệ: {{ $selected->subject }}" data-autoshow data-close-url="{{ $closeUrl }}">
            <a class="icon-button modal-close" href="{{ $closeUrl }}" aria-label="Đóng liên hệ"><x-icon name="x" /></a>
            <span class="contact-status {{ $selected->status }}">{{ $selected->statusLabel() }}</span>
            <h2>{{ $selected->subject }}</h2>
            <div class="contact-customer-meta">
                <strong>{{ $selected->full_name }}</strong>
                <span>{{ $selected->email }}</span>
                <span>{{ $selected->phone }}</span>
                <small>Gửi lúc {{ format_date($selected->created_at, true) }}</small>
            </div>
            <p class="contact-message">{{ $selected->message }}</p>
            <form method="POST" action="{{ route('admin.contacts.update', $selected) }}">
                @csrf @method('PUT')
                <label class="form-field">
                    <span>Ghi chú xử lý</span>
                    <textarea name="admin_note" rows="4">{{ old('admin_note', $selected->admin_note) }}</textarea>
                </label>
                <div class="admin-modal-actions">
                    <button class="btn btn-light" name="status" value="read">Lưu ghi chú</button>
                    <button class="btn btn-primary" name="status" value="resolved">Đánh dấu đã giải quyết</button>
                </div>
            </form>
        </dialog>
    @endif
@endsection
