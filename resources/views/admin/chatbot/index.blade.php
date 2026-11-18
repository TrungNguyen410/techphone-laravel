@extends('layouts.admin')

@section('title', 'Chatbot AI')

@php
    use App\Models\ChatMessage;
    use App\Services\Chat\ChatTools;

    $quotaExhausted = $stats['aiEnabled'] && $stats['aiQuotaExhausted'];
    $percent = fn ($value, $total) => $total ? round($value / $total * 100).'%' : '0%';
    $cards = [
        ['Cuộc trò chuyện', $stats['sessions'], $stats['sessionsToday'].' hôm nay', 'message-circle', 'blue'],
        ['Câu trả lời', $stats['answers'], $percent($stats['fallback'], $stats['answers']).' offline/dự phòng', 'activity', 'violet'],
        ['Hài lòng', $stats['liked'].' / '.($stats['liked'] + $stats['disliked']), $stats['disliked'].' đánh giá chưa tốt', $stats['disliked'] > $stats['liked'] ? 'thumbs-down' : 'thumbs-up', 'green'],
        ['Thời gian phản hồi', number_format($stats['avgLatencyMs'] / 1000, 1, ',', '.').'s', 'Trung bình mỗi câu trả lời', 'clock', 'orange'],
    ];
    $questionsText = old('questions', implode("\n", $config['quickQuestions']));
@endphp

@section('content')
    <div @class(['chatbot-status', 'is-on' => $stats['aiEnabled'] && ! $quotaExhausted, 'is-off' => ! $stats['aiEnabled'] || $quotaExhausted])>
        <x-icon name="cpu" />
        <div>
            <strong>
                @if (! $stats['aiEnabled'])
                    Chatbot đang chạy chế độ offline
                @elseif ($quotaExhausted)
                    Gemini API tạm hết hạn mức
                @else
                    Gemini API đang hoạt động ({{ $stats['aiModel'] }})
                @endif
            </strong>
            <span>
                @if (! $stats['aiEnabled'])
                    Chưa cấu hình GEMINI_API_KEY trong file .env. Chatbot trả lời theo luật dựa trên dữ liệu thật của cửa hàng.
                @elseif ($quotaExhausted)
                    Model {{ implode(', ', $stats['aiQuotaExhausted']) }} đã hết lượt gọi (gói miễn phí giới hạn theo ngày). Chatbot tạm trả lời ở chế độ offline và sẽ tự dùng lại Gemini khi có hạn mức.
                @else
                    Câu trả lời được sinh bởi Gemini, dữ liệu lấy trực tiếp từ cửa hàng qua function calling.
                @endif
            </span>
        </div>
        <a class="btn btn-light" href="{{ route('admin.knowledge.index') }}"><x-icon name="book-open" /> Kho kiến thức</a>
    </div>

    <section class="stat-grid">
        @foreach ($cards as [$label, $value, $change, $icon, $color])
            <article class="stat-card">
                <div class="stat-icon {{ $color }}"><x-icon :name="$icon" /></div>
                <div><span>{{ $label }}</span><strong>{{ $value }}</strong><small>{{ $change }}</small></div>
            </article>
        @endforeach
    </section>

    <form class="admin-card chatbot-config" method="POST" action="{{ route('admin.chatbot.config') }}" aria-label="Cấu hình chatbot">
        @csrf @method('PUT')
        <div class="card-heading"><div><h2>Cấu hình chatbot</h2><span>Áp dụng ngay cho khung chat trên website</span></div></div>
        <label class="admin-checkbox">
            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $config['enabled']))>
            <span>Bật chatbot trên website</span>
        </label>
        <div class="form-grid">
            <label class="form-field full">
                <span>Lời chào (hỗ trợ **in đậm** và dòng bắt đầu bằng "- ")</span>
                <textarea name="greeting" rows="4" maxlength="500">{{ old('greeting', $config['greeting']) }}</textarea>
                @error('greeting')<small>{{ $message }}</small>@enderror
            </label>
            <label class="form-field full">
                <span>Câu hỏi gợi ý (mỗi dòng một câu, tối đa 6)</span>
                <textarea name="questions" rows="4">{{ $questionsText }}</textarea>
                @error('questions')<small>{{ $message }}</small>@enderror
            </label>
        </div>
        <div class="chatbot-config-actions">
            <small>{{ count(array_filter(preg_split('/\R/', $questionsText) ?: [], fn ($line) => trim($line) !== '')) }}/6 câu hỏi</small>
            <button class="btn btn-primary"><x-icon name="save" /> Lưu cấu hình</button>
        </div>
    </form>

    <section class="dashboard-bottom-grid chatbot-insights">
        <div class="admin-card chatbot-tools">
            <div class="card-heading"><div><h2>Chức năng được dùng</h2><span>Số lần chatbot gọi công cụ lấy dữ liệu</span></div></div>
            @forelse ($stats['tools'] as $name => $count)
                <div><span>{{ ChatTools::LABELS[$name] ?? $name }}</span><strong>{{ $count }}</strong></div>
            @empty
                <p class="dashboard-empty">Chưa có dữ liệu.</p>
            @endforelse
        </div>
        <div class="admin-card chatbot-questions">
            <div class="card-heading"><div><h2>Câu hỏi gần đây</h2><span>Dùng để bổ sung kho kiến thức</span></div></div>
            @forelse ($stats['recentQuestions'] as $question)
                <div>
                    <a href="{{ route('admin.chatbot.index', ['view' => $question->chat_session_id]) }}">{{ \Illuminate\Support\Str::limit($question->content, 90) }}</a>
                    <small>{{ format_date($question->created_at, true) }}</small>
                </div>
            @empty
                <p class="dashboard-empty">Chưa có câu hỏi nào.</p>
            @endforelse
        </div>
    </section>

    <form class="admin-page-toolbar" method="GET" action="{{ route('admin.chatbot.index') }}">
        <div class="admin-search"><x-icon name="search" /><input name="search" maxlength="100" value="{{ request('search') }}" placeholder="Tìm theo câu hỏi đầu tiên..."></div>
    </form>
    <div class="admin-table-card">
        <div class="admin-table-title"><div><h2>Lịch sử hội thoại</h2><span>{{ $sessions->total() }} cuộc trò chuyện</span></div></div>
        @if ($sessions->isEmpty())
            @include('partials.empty-state', ['title' => 'Chưa có cuộc trò chuyện nào', 'description' => 'Khi khách dùng khung "Tư vấn AI", hội thoại sẽ xuất hiện ở đây.'])
        @else
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead><tr><th>Câu hỏi đầu tiên</th><th>Khách hàng</th><th>Số tin nhắn</th><th>Hoạt động gần nhất</th><th class="table-actions-cell">Thao tác</th></tr></thead>
                    <tbody>
                        @foreach ($sessions as $session)
                            <tr>
                                <td><strong>{{ \Illuminate\Support\Str::limit($session->title, 70) }}</strong></td>
                                <td>{{ $session->customerName() }}</td>
                                <td>{{ $session->message_count }}</td>
                                <td>{{ format_date($session->last_message_at, true) }}</td>
                                <td class="table-actions-cell">
                                    <div class="table-actions">
                                        <a href="{{ route('admin.chatbot.index', array_merge(request()->except('view'), ['view' => $session->id])) }}" aria-label="Xem hội thoại {{ $session->title }}"><x-icon name="eye" /></a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $sessions->links() }}
        @endif
    </div>

    @if ($transcript)
        @php $closeUrl = route('admin.chatbot.index', request()->except('view')); @endphp
        <dialog class="accessible-dialog order-detail-modal chat-transcript-modal" aria-label="Nội dung hội thoại" data-autoshow data-close-url="{{ $closeUrl }}">
            <a class="icon-button modal-close" href="{{ $closeUrl }}" aria-label="Đóng hội thoại">×</a>
            <span class="eyebrow">Hội thoại chatbot · {{ $transcript->customerName() }}</span>
            <h2>{{ $transcript->title }}</h2>
            <div class="chat-transcript">
                @foreach ($transcript->messages as $message)
                    <div class="chat-message {{ $message->role }}">
                        <div class="chat-bubble" @if ($message->role === 'assistant') data-rich-text @endif>{{ $message->content }}</div>
                        @if ($message->role === 'assistant')
                            <small class="chat-meta">
                                {{ ChatMessage::SOURCE_LABELS[$message->source] ?? $message->source }}
                                @if ($message->tool_calls)
                                    · {{ collect($message->tool_calls)->map(fn ($call) => ChatTools::LABELS[$call['name'] ?? ''] ?? ($call['name'] ?? ''))->implode(', ') }}
                                @endif
                                @if ($message->latency_ms)
                                    · {{ number_format($message->latency_ms / 1000, 1, ',', '.') }}s
                                @endif
                                @if ($message->feedback)
                                    · {{ $message->feedback === 'up' ? '👍' : '👎' }}
                                @endif
                            </small>
                        @endif
                    </div>
                @endforeach
            </div>
        </dialog>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/chat-widget.js') }}?v={{ filemtime(public_path('js/chat-widget.js')) }}"></script>
@endpush
