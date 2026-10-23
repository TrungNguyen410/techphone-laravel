<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
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
