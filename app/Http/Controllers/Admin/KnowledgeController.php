<?php

namespace App\Http\Controllers\Admin;

use App\Models\KnowledgeArticle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Kho kiến thức chatbot: bài viết chính sách/dịch vụ mà chatbot dùng để trả lời.
 * Từ khóa (cách nhau bằng dấu phẩy, nên có cả dạng không dấu) giúp chatbot tìm đúng bài.
 */
class KnowledgeController extends SimpleCrudController
{
    protected function modelClass(): string
    {
        return KnowledgeArticle::class;
    }

    protected function resource(): string
    {
        return 'knowledge';
    }

    protected function meta(): array
    {
        return [
            'title' => 'Kho kiến thức chatbot',
            'singular' => 'Bài viết',
            'columns' => ['Tiêu đề', 'Nhóm', 'Từ khóa', 'Trạng thái'],
            'note' => 'Chatbot AI dùng các bài viết này để trả lời câu hỏi về chính sách và dịch vụ. Thêm từ khóa (cách nhau bằng dấu phẩy, nên có cả dạng không dấu) để chatbot tìm đúng bài.',
        ];
    }

    protected function fields(): array
    {
        return [
            'title' => ['label' => 'Tiêu đề', 'type' => 'text', 'required' => true, 'full' => true],
            'category' => ['label' => 'Nhóm', 'type' => 'select', 'options' => KnowledgeArticle::CATEGORIES],
            'keywords_text' => ['label' => 'Từ khóa (cách nhau bằng dấu phẩy)', 'type' => 'text'],
            'content' => ['label' => 'Nội dung', 'type' => 'textarea', 'required' => true, 'full' => true],
            'active' => ['label' => 'Đang sử dụng', 'type' => 'checkbox'],
        ];
    }

    protected function defaults(): array
    {
        return ['category' => 'faq', 'active' => true];
    }

    protected function rules(?Model $item): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'category' => ['required', Rule::in(array_keys(KnowledgeArticle::CATEGORIES))],
            'keywords_text' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string', 'min:10', 'max:5000'],
            'active' => ['nullable'],
        ];
    }

    protected function prepare(array $data, ?Model $item): array
    {
        $keywords = array_slice(array_values(array_unique(array_filter(array_map('trim', explode(',', (string) ($data['keywords_text'] ?? '')))))), 0, 30);
        unset($data['keywords_text']);

        return [...$data, 'keywords' => $keywords];
    }

    protected function listQuery()
    {
        return KnowledgeArticle::orderBy('category')->latest('updated_at');
    }
}
