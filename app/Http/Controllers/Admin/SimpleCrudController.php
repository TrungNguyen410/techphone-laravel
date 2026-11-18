<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD dạng bảng + modal cho các danh mục đơn giản (danh mục, thương hiệu, banner, voucher).
 * Lớp con khai báo model, các trường của form và luật kiểm tra dữ liệu.
 */
abstract class SimpleCrudController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** Tên resource trong route: "categories", "brands"... */
    abstract protected function resource(): string;

    /** @return array{title: string, singular: string, columns: array<int, string>} */
    abstract protected function meta(): array;

    /**
     * Các trường của form: key => [label, type, required, full, options].
     *
     * @return array<string, array<string, mixed>>
     */
    abstract protected function fields(): array;

    abstract protected function rules(?Model $item): array;

    /** @return array<string, mixed> Giá trị mặc định khi thêm mới */
    protected function defaults(): array
    {
        return [];
    }

    public function __construct(protected readonly ImageUploader $uploader) {}

    public function index(Request $request): View
    {
        $model = $this->modelClass();

        return view('admin.simple-crud', [
            'resource' => $this->resource(),
            'meta' => $this->meta(),
            'fields' => $this->fields(),
            'items' => $this->listQuery()->get(),
            'editing' => $request->filled('edit') ? $model::find($request->query('edit')) : null,
            'creating' => $request->boolean('create'),
            'defaults' => $this->defaults(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->modelClass()::create($this->payload($request, null));

        return $this->done('Đã lưu '.mb_strtolower($this->meta()['singular']));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $item = $this->modelClass()::findOrFail($id);
        $item->update($this->payload($request, $item));

        return $this->done('Đã lưu '.mb_strtolower($this->meta()['singular']));
    }

    public function destroy(int $id): RedirectResponse
    {
        $item = $this->modelClass()::findOrFail($id);
        $this->beforeDelete($item);
        $item->delete();

        return $this->done('Đã xóa '.mb_strtolower($this->meta()['singular']));
    }

    protected function listQuery()
    {
        return $this->modelClass()::query()->latest('id');
    }

    protected function beforeDelete(Model $item): void {}

    /** Hook cho lớp con tinh chỉnh dữ liệu trước khi lưu. */
    protected function prepare(array $data, ?Model $item): array
    {
        return $data;
    }

    private function payload(Request $request, ?Model $item): array
    {
        $attributes = collect($this->fields())
            ->flatMap(fn ($field, $key) => [
                $key => mb_strtolower($field['label']),
                $key.'_file' => mb_strtolower($field['label']),
                $key.'_url' => 'URL '.mb_strtolower($field['label']),
            ])
            ->all();
        $rules = $this->rules($item);
        foreach ($this->fields() as $key => $field) {
            if ($field['type'] === 'image') {
                $rules[$key.'_file'] = [...($rules[$key.'_file'] ?? ['nullable', 'image', 'max:5120']), 'mimes:jpg,jpeg,png,webp,gif'];
                $rules[$key.'_url'] = ['nullable', 'url:http,https', 'max:500'];
            }
        }
        $data = $request->validate($rules, [
            'required' => 'Vui lòng nhập :attribute',
            'required_without' => 'Vui lòng tải ảnh lên từ máy tính hoặc nhập URL ảnh',
            'unique' => ':attribute đã tồn tại',
            'image' => 'File tải lên phải là ảnh',
            'mimes' => 'Chỉ hỗ trợ ảnh JPG, PNG, WEBP hoặc GIF',
            'max' => ':attribute vượt quá giới hạn cho phép',
            'url' => ':attribute không hợp lệ (cần bắt đầu bằng http:// hoặc https://)',
        ], $attributes);

        foreach ($this->fields() as $key => $field) {
            if ($field['type'] === 'checkbox') {
                $data[$key] = $request->boolean($key);
            }
            if ($field['type'] === 'image') {
                // Ưu tiên ảnh tải lên từ máy tính, sau đó tới URL ảnh, cuối cùng là yêu cầu xóa ảnh.
                if ($request->hasFile($key.'_file')) {
                    $this->uploader->delete($item?->{$key});
                    $data[$key] = $this->uploader->store($request->file($key.'_file'), $this->resource());
                } elseif ($request->filled($key.'_url')) {
                    $this->uploader->delete($item?->{$key});
                    $data[$key] = trim((string) $request->input($key.'_url'));
                } elseif ($request->boolean($key.'_remove')) {
                    $this->uploader->delete($item?->{$key});
                    $data[$key] = '';
                } else {
                    unset($data[$key]);
                }
                unset($data[$key.'_file'], $data[$key.'_url'], $data[$key.'_remove']);
            }
        }

        return $this->prepare($data, $item);
    }

    private function done(string $message): RedirectResponse
    {
        return redirect()->route('admin.'.$this->resource().'.index')->with('toast', ['type' => 'success', 'message' => $message]);
    }
}
