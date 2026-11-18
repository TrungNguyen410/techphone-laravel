<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Khung chat "Tư vấn AI" trên mọi trang cửa hàng. Khách chưa đăng nhập được nhận diện bằng visitor_id
 * (chuỗi ngẫu nhiên lưu trong trình duyệt); cuộc trò chuyện chỉ chủ của nó mới đọc được.
 */
class ChatController extends Controller
{
    private const VISITOR_RULE = ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{8,100}$/'];

    public function __construct(private readonly ChatService $chat) {}

    public function config(): JsonResponse
    {
        return response()->json($this->chat->config());
    }

    public function send(Request $request): JsonResponse
    {
        $data = $this->validateMessage($request);
        $result = $this->chat->sendMessage($data['message'], ! empty($data['session_id']) ? (int) $data['session_id'] : null, $data['visitor_id'] ?? null, $request->user(), (string) $request->ip());

        return response()->json(['sessionId' => $result['sessionId'], 'message' => $result['message']->toClient()], 201);
    }

    /**
     * Server-Sent Events: session → delta* → done (hoặc error). Lỗi phát hiện trước khi bắt đầu stream
     * (dữ liệu sai, chatbot tắt, không phải chủ cuộc trò chuyện) vẫn trả JSON với mã lỗi tương ứng.
     */
    public function stream(Request $request): StreamedResponse|JsonResponse
    {
        $data = $this->validateMessage($request);
        $user = $request->user();
        if (! $this->chat->config()['enabled']) {
            throw new BusinessException('Chatbot đang tạm ngưng. Vui lòng liên hệ hotline để được hỗ trợ.', 503);
        }
        if (! empty($data['session_id'])) {
            $this->chat->findOwnedSession((int) $data['session_id'], $user, $data['visitor_id'] ?? null);
        }
        $clientKey = (string) $request->ip();

        return response()->stream(function () use ($data, $user, $clientKey) {
            $send = function (array $event) {
                echo 'data: '.json_encode($event, JSON_UNESCAPED_UNICODE)."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            try {
                $result = $this->chat->sendMessage(
                    $data['message'],
                    ! empty($data['session_id']) ? (int) $data['session_id'] : null,
                    $data['visitor_id'] ?? null,
                    $user,
                    $clientKey,
                    [
                        'onSession' => fn (int $sessionId) => $send(['type' => 'session', 'sessionId' => $sessionId]),
                        'onDelta' => fn (string $text) => $send(['type' => 'delta', 'text' => $text]),
                        'onReset' => fn () => $send(['type' => 'reset']),
                    ],
                );
                $send(['type' => 'done', 'sessionId' => $result['sessionId'], 'message' => $result['message']->toClient()]);
            } catch (Throwable $exception) {
                report($exception);
                $send(['type' => 'error', 'message' => 'Xin lỗi, trợ lý đang gặp sự cố. Vui lòng thử lại.']);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function session(Request $request, int $session): JsonResponse
    {
        $visitorId = $this->validVisitorId($request->query('visitor_id'));
        $chatSession = $this->chat->findOwnedSession($session, $request->user(), $visitorId);

        return response()->json([
            'session' => ['id' => $chatSession->id, 'title' => $chatSession->title],
            'messages' => $chatSession->messages()->get()->map->toClient()->all(),
        ]);
    }

    public function feedback(Request $request, int $message): JsonResponse
    {
        $data = $request->validate([
            'value' => ['nullable', Rule::in(['up', 'down'])],
            'visitor_id' => self::VISITOR_RULE,
        ], ['value.in' => 'Đánh giá phải là up hoặc down']);

        $updated = $this->chat->setFeedback($message, $data['value'] ?? null, $request->user(), $data['visitor_id'] ?? null);

        return response()->json(['id' => $updated->id, 'feedback' => $updated->feedback]);
    }

    /** @return array{message: string, session_id?: ?int, visitor_id?: ?string} */
    private function validateMessage(Request $request): array
    {
        return Validator::make($request->all(), [
            'message' => ['required', 'string', 'max:'.ChatService::MAX_MESSAGE_LENGTH],
            'session_id' => ['nullable', 'integer', 'min:1'],
            'visitor_id' => self::VISITOR_RULE,
        ], [
            'message.required' => 'Vui lòng nhập nội dung tin nhắn',
            'message.max' => 'Tin nhắn tối đa '.ChatService::MAX_MESSAGE_LENGTH.' ký tự',
            'visitor_id.regex' => 'Mã khách truy cập không hợp lệ',
        ])->validate();
    }

    private function validVisitorId(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9_-]{8,100}$/', $value) ? $value : null;
    }
}
