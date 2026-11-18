<?php

namespace App\Services\Chat;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gọi Google Gemini API (generateContent / streamGenerateContent) bằng HTTP, không cần thư viện ngoài.
 *
 * - Model quá tải (500/503) được gọi lại một lần rồi chuyển sang các model dự phòng.
 * - 429 nghĩa là hết hạn mức (gói miễn phí giới hạn theo ngày): model đó bị bỏ qua 10 phút, chatbot trả lời
 *   ở chế độ offline ngay thay vì chờ.
 */
class GeminiClient
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

    private const OVERLOADED_STATUSES = [500, 503];

    private const QUOTA_COOLDOWN_SECONDS = 600;

    private const RETRY_DELAY_MS = 1200;

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key'));
    }

    public function model(): string
    {
        return (string) config('services.gemini.model');
    }

    /** @return array<int, string> */
    public function fallbackModels(): array
    {
        $models = array_map('trim', explode(',', (string) config('services.gemini.fallback_model')));

        return array_values(array_filter($models, fn ($model) => $model !== '' && $model !== $this->model()));
    }

    /** @return array<int, string> Các model đang tạm hết hạn mức */
    public function quotaExhausted(): array
    {
        return array_values(array_filter([$this->model(), ...$this->fallbackModels()], fn ($model) => $this->isCoolingDown($model)));
    }

    /**
     * Trả về lượt trả lời của mô hình đúng như nhận được (kể cả thought signature) để gửi lại khi gọi công cụ.
     *
     * @param  array{systemInstruction: string, contents: array, tools?: array, toolMode?: string}  $request
     * @return array{content: array{role: string, parts: array}, finishReason: string}
     */
    public function generate(array $request): array
    {
        $response = $this->post('generateContent', $request);
        $candidate = $response->json('candidates.0') ?? [];

        return [
            'content' => ['role' => 'model', 'parts' => $this->normalizeParts($candidate['content']['parts'] ?? [])],
            'finishReason' => (string) ($candidate['finishReason'] ?? ''),
        ];
    }

    /**
     * Stream qua SSE: chữ được chuyển cho $onText ngay khi tới, lượt trả lời gộp (chữ + lời gọi công cụ)
     * được trả về giống generate().
     *
     * @param  callable(string): void  $onText
     * @return array{content: array{role: string, parts: array}, finishReason: string}
     */
    public function stream(array $request, callable $onText): array
    {
        $response = $this->post('streamGenerateContent?alt=sse', $request, stream: true);
        $body = $response->toPsrResponse()->getBody();

        $parts = [];
        $text = '';
        $finishReason = '';
        $handle = function (string $event) use (&$parts, &$text, &$finishReason, $onText) {
            $data = implode('', array_map(
                fn ($line) => trim(substr($line, 5)),
                array_filter(explode("\n", $event), fn ($line) => str_starts_with($line, 'data:')),
            ));
            if ($data === '') {
                return;
            }
            $chunk = json_decode($data, true);
            $candidate = $chunk['candidates'][0] ?? [];
            $finishReason = (string) ($candidate['finishReason'] ?? $finishReason);
            foreach ($candidate['content']['parts'] ?? [] as $part) {
                if (isset($part['text']) && is_string($part['text']) && empty($part['thought']) && ! isset($part['functionCall'])) {
                    $text .= $part['text'];
                    $onText($part['text']);
                } else {
                    $parts[] = $part;
                }
            }
        };

        $buffer = '';
        while (! $body->eof()) {
            $buffer .= str_replace("\r\n", "\n", $body->read(2048));
            while (($boundary = strpos($buffer, "\n\n")) !== false) {
                $handle(substr($buffer, 0, $boundary));
                $buffer = substr($buffer, $boundary + 2);
            }
        }
        if (trim($buffer) !== '') {
            $handle($buffer);
        }

        $parts = $this->normalizeParts($parts);

        return [
            'content' => ['role' => 'model', 'parts' => $text !== '' ? [['text' => $text], ...$parts] : $parts],
            'finishReason' => $finishReason,
        ];
    }

    private function post(string $method, array $request, bool $stream = false): Response
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Chatbot AI chưa được cấu hình GEMINI_API_KEY');
        }

        $attempts = [$this->model(), $this->model(), ...$this->fallbackModels()];
        $lastError = new RuntimeException('Gemini API đã hết hạn mức, tạm dùng chế độ offline');
        $unreachable = [];
        $sentRequest = false;
        foreach ($attempts as $model) {
            if ($this->isCoolingDown($model) || in_array($model, $unreachable, true)) {
                continue;
            }
            // Chỉ chờ giữa hai lần gọi thật; model đang bị bỏ qua không tốn thời gian.
            if ($sentRequest && ! app()->runningUnitTests()) {
                usleep(self::RETRY_DELAY_MS * 1000);
            }
            $sentRequest = true;

            try {
                $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.api_key')])
                    ->acceptJson()
                    ->timeout(max(5, (int) config('services.gemini.timeout', 40)))
                    ->withOptions(['stream' => $stream])
                    ->post(self::API_BASE.'/models/'.rawurlencode($model).':'.$method, $this->body($request));
            } catch (ConnectionException $exception) {
                $lastError = new RuntimeException('Không kết nối được Gemini API: '.$exception->getMessage());
                $unreachable[] = $model;

                continue;
            }
            if ($response->successful()) {
                return $response;
            }

            $lastError = new RuntimeException('Gemini API trả lỗi '.$response->status().': '.mb_substr((string) $response->body(), 0, 300));
            if ($response->status() === 429) {
                Cache::put($this->cooldownKey($model), true, self::QUOTA_COOLDOWN_SECONDS);
            } elseif (! in_array($response->status(), self::OVERLOADED_STATUSES, true)) {
                throw $lastError;
            }
        }

        throw $lastError;
    }

    /** toolMode "NONE" vẫn gửi khai báo công cụ (lịch sử có lời gọi công cụ) nhưng buộc mô hình trả lời bằng chữ. */
    private function body(array $request): array
    {
        $tools = $request['tools'] ?? [];

        return array_filter([
            'systemInstruction' => ['parts' => [['text' => $request['systemInstruction']]]],
            'contents' => $request['contents'],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => (int) config('services.gemini.max_output_tokens', 2048),
            ],
            'tools' => $tools ? [['functionDeclarations' => $tools]] : null,
            'toolConfig' => $tools && ! empty($request['toolMode']) ? ['functionCallingConfig' => ['mode' => $request['toolMode']]] : null,
        ], fn ($value) => $value !== null);
    }

    /** JSON {} giải mã thành [] trong PHP; tham số công cụ phải gửi lại dưới dạng object. */
    private function normalizeParts(array $parts): array
    {
        return array_map(function ($part) {
            if (isset($part['functionCall']) && empty($part['functionCall']['args'])) {
                $part['functionCall']['args'] = (object) [];
            }

            return $part;
        }, $parts);
    }

    private function isCoolingDown(string $model): bool
    {
        return (bool) Cache::get($this->cooldownKey($model));
    }

    private function cooldownKey(string $model): string
    {
        return 'gemini-cooldown:'.$model;
    }
}
