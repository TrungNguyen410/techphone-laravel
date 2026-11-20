<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $filter = array_key_exists((string) $request->query('status'), Review::STATUSES) ? $request->query('status') : null;

        return view('admin.reviews.index', [
            'reviews' => Review::with(['product', 'accessory'])
                ->when($filter, fn ($query) => $query->where('status', $filter))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filter' => $filter,
            'editing' => is_numeric($request->query('edit')) ? Review::with(['product', 'accessory'])->find((int) $request->query('edit')) : null,
        ]);
    }

    /**
     * Sửa đánh giá: số sao, nội dung, trạng thái và gỡ bớt ảnh không phù hợp.
     */
    public function update(Request $request, Review $review, ImageUploader $uploader): RedirectResponse
    {
        // Đánh giá cũ có thể ngắn hơn 10 ký tự: chỉ bắt độ dài tối thiểu khi admin thật sự sửa nội dung.
        $commentChanged = trim((string) $request->input('comment')) !== trim((string) $review->comment);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:2000', ...($commentChanged ? ['min:10'] : [])],
            'status' => ['required', Rule::in(array_keys(Review::STATUSES))],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['string'],
        ], [
            'rating.between' => 'Số sao phải là số nguyên từ 1 đến 5',
            'comment.required' => 'Nội dung đánh giá phải có ít nhất 10 ký tự',
            'comment.min' => 'Nội dung đánh giá phải có ít nhất 10 ký tự',
            'status.in' => 'Trạng thái đánh giá không hợp lệ',
        ]);

        $images = $review->images ?? [];
        $removed = array_values(array_intersect($images, $data['remove_images'] ?? []));

        $review->update([
            'rating' => $data['rating'],
            'comment' => trim($data['comment']),
            'status' => $data['status'],
            'images' => array_values(array_diff($images, $removed)),
        ]);

        // Chỉ xóa file sau khi đã lưu CSDL thành công, để đánh giá không bao giờ trỏ tới ảnh đã mất.
        foreach ($removed as $image) {
            $uploader->delete($image);
        }

        return redirect()->route('admin.reviews.index', $request->only('status_filter') ? ['status' => $request->input('status_filter')] : [])
            ->with('toast', ['type' => 'success', 'message' => 'Đã cập nhật đánh giá']);
    }

    /** Duyệt (approved) hoặc từ chối (rejected) đánh giá. */
    public function moderate(Review $review, string $status): RedirectResponse
    {
        $review->update(['status' => $status]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $status === 'approved' ? 'Đã duyệt đánh giá' : 'Đã từ chối đánh giá',
        ]);
    }

    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Đã xóa đánh giá']);
    }
}
