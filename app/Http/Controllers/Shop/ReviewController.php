<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Accessory;
use App\Models\Product;
use App\Models\Review;
use App\Services\ImageUploader;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $approved = Review::approved()->latest()->get();
        $filter = (int) $request->query('rating', 0);

        return view('shop.reviews', [
            'reviews' => $filter ? $approved->where('rating', $filter) : $approved,
            'total' => $approved->count(),
            'average' => $approved->avg('rating') ?? 0,
            'filter' => $filter,
        ]);
    }

    public function store(Request $request, ReviewService $reviews): RedirectResponse
    {
        $data = $request->validate([
            'target_type' => ['required', Rule::in(['general', 'product', 'accessory'])],
            'target_id' => ['nullable', 'integer'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ImageUploader::RULES,
        ], [
            'comment.min' => 'Nội dung đánh giá cần ít nhất 10 ký tự',
            'comment.required' => 'Nội dung đánh giá cần ít nhất 10 ký tự',
            'images.max' => 'Bạn chỉ có thể tải tối đa 5 ảnh cho một đánh giá',
            'images.*.image' => 'Ảnh đánh giá không hợp lệ',
            'images.*.max' => 'Mỗi ảnh tối đa 5MB',
        ]);

        $target = match ($data['target_type']) {
            'product' => Product::findOrFail($data['target_id']),
            'accessory' => Accessory::findOrFail($data['target_id']),
            default => null,
        };

        $reviews->create($request->user(), $target, $data['rating'], $data['comment'], $request->file('images', []));

        return back()->with('toast', [
            'type' => 'success',
            'message' => $target ? 'Đánh giá đã được gửi và đang chờ duyệt' : 'Cảm ơn bạn! Đánh giá đang chờ duyệt.',
        ]);
    }
}
