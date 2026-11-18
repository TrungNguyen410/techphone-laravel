<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Services\Chat\ChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trang quản trị Chatbot AI: trạng thái Gemini, thống kê (số phiên, tỉ lệ hài lòng, thời gian phản hồi,
 * công cụ hay dùng), cấu hình lời chào/câu hỏi gợi ý và xem lại hội thoại.
 */
class ChatbotController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function index(Request $request): View
    {
        $sessions = ChatSession::with('user')
            ->when(mb_substr(trim((string) $request->query('search')), 0, 100), fn ($query, $search) => $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->latest('last_message_at')->latest('id')
            ->paginate(15)->withQueryString();

        return view('admin.chatbot.index', [
            'stats' => $this->chat->stats(),
            'config' => $this->chat->config(),
            'sessions' => $sessions,
            'transcript' => $request->filled('view') ? ChatSession::with(['user', 'messages'])->find((int) $request->query('view')) : null,
        ]);
    }

    public function updateConfig(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'greeting' => ['nullable', 'string', 'max:500'],
            'questions' => ['nullable', 'string', 'max:1000'],
        ], [
            'greeting.max' => 'Lời chào tối đa 500 ký tự',
        ]);

        $questions = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['questions'] ?? '')) ?: [])));
        if (count($questions) > 6) {
            return back()->withInput()->withErrors(['questions' => 'Tối đa 6 câu hỏi gợi ý']);
        }

        $this->chat->updateConfig([
            'enabled' => $request->boolean('enabled'),
            'greeting' => $data['greeting'] ?? '',
            'quickQuestions' => $questions,
        ]);

        return redirect()->route('admin.chatbot.index')->with('toast', ['type' => 'success', 'message' => 'Đã lưu cấu hình chatbot']);
    }
}
