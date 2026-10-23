<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lưu ảnh tải lên vào storage/app/public/uploads/{thư mục}, trả về đường dẫn tương đối.
 */
class ImageUploader
{
    public const RULES = ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'];

    public function store(UploadedFile $file, string $folder): string
    {
        $name = now()->format('YmdHis').'-'.Str::lower(Str::random(10)).'.'.$file->extension();

        return $file->storeAs('uploads/'.$folder, $name, 'public');
    }

    /**
     * @param  array<int, UploadedFile>|null  $files
     * @return array<int, string>
     */
    public function storeMany(?array $files, string $folder): array
    {
        return array_map(fn (UploadedFile $file) => $this->store($file, $folder), $files ?? []);
    }

    /** Xóa file đã tải lên (không xóa ảnh mẫu hoặc URL ngoài). */
    public function delete(?string $path): void
    {
        if ($path && Str::startsWith($path, 'uploads/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
