{{--
    Khung chat "Tư vấn AI" nổi trên mọi trang cửa hàng (tắt được trong Quản trị → Chatbot AI).
    Câu trả lời được stream (Server-Sent Events); thẻ sản phẩm trong khung chat thêm vào giỏ ngay.
--}}
@php
    $chatConfig = app(\App\Services\Chat\ChatService::class)->config();
    $chatEndpoints = [
        'send' => route('chat.send'),
        'stream' => route('chat.stream'),
        'session' => url('/chat/sessions'),
        'feedback' => url('/chat/messages'),
        'cart' => url('/cart/add'),
    ];
@endphp
@if ($chatConfig['enabled'])
    <div class="chat-widget" data-chat-widget data-config='@json($chatConfig)' data-endpoints='@json($chatEndpoints)'
         data-store-name="{{ $store['storeName'] }}">
        <section class="chat-panel panel" role="dialog" aria-label="Trợ lý AI tư vấn" hidden data-chat-panel>
            <header class="chat-header">
                <span class="chat-avatar">@include('partials.robot-mascot')</span>
                <div><strong>Trợ lý AI tư vấn</strong><small>Trả lời dựa trên dữ liệu cửa hàng</small></div>
                <button type="button" aria-label="Cuộc trò chuyện mới" title="Cuộc trò chuyện mới" data-chat-reset><x-icon name="trash-2" /></button>
                <button type="button" aria-label="Đóng trợ lý AI" data-chat-close><x-icon name="x" /></button>
            </header>
            <div class="chat-messages" aria-live="polite" data-chat-messages></div>
            <form class="chat-input" data-chat-form>
                <input maxlength="{{ \App\Services\Chat\ChatService::MAX_MESSAGE_LENGTH }}" placeholder="Nhập câu hỏi của bạn..." aria-label="Nội dung tin nhắn" autocomplete="off" data-chat-input>
                <button type="submit" aria-label="Gửi tin nhắn" disabled data-chat-send><x-icon name="send" /></button>
            </form>
        </section>
        <button type="button" class="chat-toggle" aria-expanded="false" aria-label="Mở trợ lý AI tư vấn" data-chat-toggle>
            <span data-chat-toggle-open>@include('partials.robot-mascot', ['class' => 'chat-toggle-robot'])</span>
            <span data-chat-toggle-close hidden><x-icon name="x" /></span>
            <span data-chat-toggle-label>Tư vấn AI</span>
        </button>
        <template data-chat-robot>@include('partials.robot-mascot')</template>
        <template data-chat-icons>
            <span data-icon="cart"><x-icon name="shopping-cart" /></span>
            <span data-icon="up"><x-icon name="thumbs-up" /></span>
            <span data-icon="down"><x-icon name="thumbs-down" /></span>
        </template>
    </div>
    @once
        @push('scripts')
            <script src="{{ asset('js/chat-widget.js') }}?v={{ filemtime(public_path('js/chat-widget.js')) }}" defer></script>
        @endpush
    @endonce
@endif
