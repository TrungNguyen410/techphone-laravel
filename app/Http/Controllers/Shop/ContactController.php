<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Rules\VietnamesePhoneRule;
use App\Support\VietnamesePhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public const FAQS = [
        ['TechPhone có giao hàng toàn quốc không?', 'Có. Chúng tôi giao hàng toàn quốc, miễn phí với đơn hàng từ 10 triệu đồng.'],
        ['Sản phẩm được bảo hành bao lâu?', 'Điện thoại và phụ kiện được bảo hành từ 6 đến 24 tháng tùy sản phẩm.'],
        ['Tôi có thể đổi trả sản phẩm không?', 'Bạn có thể đổi trả trong 30 ngày nếu sản phẩm đáp ứng điều kiện chính sách.'],
        ['TechPhone có hỗ trợ thu cũ đổi mới?', 'Có. Vui lòng mang thiết bị đến cửa hàng để được kiểm tra và định giá trực tiếp.'],
    ];

    public function index(): View
    {
        return view('shop.contact', ['faqs' => self::FAQS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', new VietnamesePhoneRule],
            'email' => ['required', 'email', 'max:150'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:3000'],
        ], [
            'required' => 'Vui lòng nhập thông tin này',
            'email.email' => 'Email không hợp lệ',
        ]);

        Contact::create([...$data, 'phone' => VietnamesePhone::normalize($data['phone']), 'email' => mb_strtolower($data['email'])]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Đã gửi liên hệ. '.setting('storeName').' sẽ phản hồi sớm nhất!',
        ]);
    }
}
