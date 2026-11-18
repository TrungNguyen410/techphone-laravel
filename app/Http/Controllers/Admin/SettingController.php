<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageUploader;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function edit(): View
    {
        return view('admin.settings', ['settings' => $this->settings->all()]);
    }

    public function update(Request $request, ImageUploader $uploader): RedirectResponse
    {
        $data = $request->validate([
            'storeName' => ['required', 'string', 'max:100'],
            'hotline' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'facebook' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'youtube' => ['nullable', 'url', 'max:255'],
            'zaloUrl' => ['nullable', 'url', 'max:255'],
            'logo_file' => ['nullable', ...ImageUploader::RULES],
            'logo_url' => ['nullable', 'url:http,https', 'max:500'],
        ], [
            'required' => 'Vui lòng nhập thông tin này',
            'email.email' => 'Email không đúng định dạng',
            'url' => 'Đường dẫn không hợp lệ',
            'logo_file.image' => 'File tải lên phải là ảnh',
            'logo_file.mimes' => 'Chỉ hỗ trợ ảnh JPG, PNG, WEBP hoặc GIF',
            'logo_file.max' => 'Ảnh tối đa 5MB',
        ]);

        unset($data['logo_file'], $data['logo_url']);
        if ($request->hasFile('logo_file')) {
            $uploader->delete($this->settings->get('logo'));
            $data['logo'] = $uploader->store($request->file('logo_file'), 'settings');
        } elseif ($request->filled('logo_url')) {
            $uploader->delete($this->settings->get('logo'));
            $data['logo'] = trim((string) $request->input('logo_url'));
        } elseif ($request->boolean('logo_remove')) {
            $uploader->delete($this->settings->get('logo'));
            $data['logo'] = '';
        }

        $this->settings->save(array_map(fn ($value) => is_string($value) ? trim($value) : ($value ?? ''), $data));

        return back()->with('toast', ['type' => 'success', 'message' => 'Đã lưu cài đặt hệ thống']);
    }
}
