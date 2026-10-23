<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $selected = $request->filled('view') ? Contact::find($request->query('view')) : null;
        // Mở xem yêu cầu mới thì tự chuyển sang "Đang xử lý".
        if ($selected?->status === 'new') {
            $selected->update(['status' => 'read']);
        }

        $filter = array_key_exists((string) $request->query('status'), Contact::STATUSES) ? $request->query('status') : null;

        return view('admin.contacts.index', [
            'contacts' => Contact::when($filter, fn ($query) => $query->where('status', $filter))->latest()->get(),
            'unread' => Contact::where('status', 'new')->count(),
            'filter' => $filter,
            'selected' => $selected,
        ]);
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Contact::STATUSES))],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $contact->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => 'Đã cập nhật yêu cầu liên hệ']);
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()->route('admin.contacts.index')->with('toast', ['type' => 'success', 'message' => 'Đã xóa yêu cầu liên hệ']);
    }
}
