<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Contact;
use App\Models\KnowledgeArticle;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Support\SvgImageFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Voucher, banner, liên hệ và cài đặt cửa hàng mẫu.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $year = now()->year;
        $vouchers = [
            ['TECH10', 'percent', 10, 5000000, 1000000, 100, 12, "{$year}-01-01", "{$year}-12-31", true],
            ['GIAM200K', 'fixed', 200000, 3000000, 200000, 200, 28, "{$year}-01-01", "{$year}-12-31", true],
            ['FREESHIP', 'shipping', 30000, 500000, 30000, 500, 75, "{$year}-01-01", "{$year}-12-31", true],
            ['EXPIRED50', 'percent', 50, 1000000, 500000, 20, 4, ($year - 1).'-01-01', ($year - 1).'-12-31', true],
            ['USEDUP', 'fixed', 500000, 5000000, 500000, 10, 10, "{$year}-01-01", "{$year}-12-31", true],
            ['OFFLINE', 'percent', 5, 1000000, 200000, 50, 0, "{$year}-01-01", "{$year}-12-31", false],
        ];
        foreach ($vouchers as [$code, $type, $value, $minOrder, $maxDiscount, $quantity, $used, $start, $end, $active]) {
            Voucher::create([
                'code' => $code, 'type' => $type, 'value' => $value, 'min_order' => $minOrder, 'max_discount' => $maxDiscount,
                'quantity' => $quantity, 'used' => $used, 'start_date' => $start, 'end_date' => $end, 'active' => $active,
            ]);
        }

        $banners = [
            ['Lên đời flagship', 'Thu cũ đổi mới, trợ giá đến 4 triệu đồng', '/products', '#1d4ed8', '#06b6d4', true],
            ['Sale giữa năm', 'Giảm đến 30% điện thoại và phụ kiện', '/products', '#7c3aed', '#ec4899', true],
            ['Phụ kiện thông minh', 'Sạc nhanh, tai nghe, đồng hồ chính hãng', '/accessories', '#0f766e', '#14b8a6', true],
            ['Chiến dịch tạm ẩn', 'Banner ẩn dùng để kiểm thử trang quản trị', '/', '#475569', '#94a3b8', false],
        ];
        foreach ($banners as $position => [$title, $description, $link, $colorA, $colorB, $active]) {
            $path = 'seed/banners/banner-'.($position + 1).'.svg';
            Storage::disk('public')->put($path, SvgImageFactory::banner($title, $description, $colorA, $colorB));
            Banner::create([
                'title' => $title, 'description' => $description, 'image' => $path, 'link' => $link,
                'position' => $active ? $position + 1 : 99, 'active' => $active,
            ]);
        }

        $contacts = [
            ['Nguyễn Minh Anh', 'user@gmail.com', '0911111111', 'Bảo hành sản phẩm', 'Tôi muốn hỏi về chính sách bảo hành iPhone.', 'new', null],
            ['Trần Hoàng Nam', 'nam@gmail.com', '0922222222', 'Kiểm tra đơn hàng', 'Vui lòng cập nhật trạng thái giao hàng giúp tôi.', 'read', 'Đã gọi khách, đơn hàng đang được giao.'],
            ['Lê Thu Trang', 'trang@example.com', '0933333333', 'Tư vấn phụ kiện', 'Cần tư vấn sạc nhanh phù hợp cho Galaxy S25.', 'resolved', 'Đã tư vấn sạc Anker 65W.'],
        ];
        foreach ($contacts as [$name, $email, $phone, $subject, $message, $status, $note]) {
            Contact::create([
                'full_name' => $name, 'email' => $email, 'phone' => $phone, 'subject' => $subject,
                'message' => $message, 'status' => $status, 'admin_note' => $note,
            ]);
        }

        $settings = [
            ['storeName', 'TechPhone', 'general', 'Tên cửa hàng'],
            ['hotline', '1900 6868', 'general', 'Hotline'],
            ['email', 'support@techphone.vn', 'general', 'Email hỗ trợ'],
            ['address', '123 Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh', 'general', 'Địa chỉ cửa hàng'],
            ['logo', '', 'general', 'Logo'],
            ['facebook', 'https://facebook.com/techphone', 'social', 'Facebook'],
            ['instagram', 'https://instagram.com/techphone', 'social', 'Instagram'],
            ['youtube', 'https://youtube.com/@techphone', 'social', 'YouTube'],
            ['zaloUrl', '', 'social', 'Zalo'],
            ['freeShippingThreshold', 10000000, 'checkout', 'Ngưỡng miễn phí vận chuyển'],
        ];
        foreach ($settings as [$key, $value, $group, $label]) {
            Setting::create(['key' => $key, 'value' => $value, 'group' => $group, 'label' => $label]);
        }

        // Voucher tặng riêng (hiện ở Tài khoản → Voucher của tôi của khách 0911111111, người khác không dùng được).
        $minhAnh = User::where('phone', '0911111111')->first();
        if ($minhAnh) {
            Voucher::create([
                'code' => 'TRIANMA2026', 'type' => 'percent', 'value' => 15, 'min_order' => 0, 'max_discount' => 500000,
                'quantity' => 1, 'used' => 0, 'start_date' => now()->toDateString(), 'end_date' => now()->addDays(30)->toDateString(),
                'active' => true, 'assigned_user_id' => $minhAnh->id, 'note' => 'Tri ân khách hàng thân thiết',
            ]);
        }

        $this->seedKnowledgeBase();
    }

    /** Kho kiến thức chatbot: chính sách cửa hàng chatbot dùng để trả lời. */
    private function seedKnowledgeBase(): void
    {
        $articles = [
            ['Chính sách bảo hành', 'warranty', ['bảo hành', 'bao hanh', 'lỗi', 'hỏng', 'sửa chữa', 'warranty'],
                'Điện thoại chính hãng được bảo hành 12 tháng tại trung tâm bảo hành hãng, phụ kiện bảo hành 6-12 tháng. '
                .'Trong 30 ngày đầu, máy lỗi phần cứng do nhà sản xuất được đổi máy mới cùng model. '
                .'Không bảo hành khi máy rơi vỡ, vào nước, can thiệp phần mềm trái phép. Mang máy và hóa đơn (hoặc mã đơn) đến cửa hàng để được hỗ trợ.'],
            ['Chính sách đổi trả', 'return', ['đổi trả', 'doi tra', 'trả lại', 'tra lai', 'không ưng', 'hoàn tiền', 'hoan tien', 'trả hàng', 'đổi máy'],
                'Đổi mới trong 30 ngày nếu lỗi do nhà sản xuất. Trong 7 ngày, khách có thể trả máy còn nguyên hộp, đủ phụ kiện '
                .'với phí 10% giá trị hóa đơn. Tiền hoàn qua tài khoản ngân hàng trong 3-5 ngày làm việc.'],
            ['Giao hàng và phí vận chuyển', 'shipping', ['giao hàng', 'giao hang', 'ship', 'vận chuyển', 'van chuyen', 'phí ship', 'bao lâu'],
                'Miễn phí giao hàng cho đơn từ 10.000.000đ. TP. Hồ Chí Minh: 20.000đ, nhận trong 1 ngày. '
                .'Hà Nội, Đà Nẵng: 30.000đ, 2 ngày. Tỉnh thành khác: 40.000đ, 3-4 ngày. Khách được kiểm tra hàng trước khi nhận.'],
            ['Phương thức thanh toán', 'payment', ['thanh toán', 'thanh toan', 'chuyển khoản', 'chuyen khoan', 'cod', 'vnpay', 'momo', 'qr'],
                'Hỗ trợ thanh toán khi nhận hàng (COD), chuyển khoản ngân hàng qua mã VietQR (hệ thống tự động xác nhận khi nhận được tiền, '
                .'ghi đúng nội dung là mã đơn hàng), ví MoMo và thẻ/QR qua cổng VNPay.'],
            ['Mua trả góp', 'installment', ['trả góp', 'tra gop', 'góp', '0%', 'lãi suất'],
                'Hỗ trợ trả góp 0% qua thẻ tín dụng của các ngân hàng liên kết cho đơn từ 3.000.000đ, kỳ hạn 6-12 tháng. '
                .'Trả góp qua công ty tài chính cần CCCD, duyệt hồ sơ khoảng 15 phút tại cửa hàng.'],
            ['Thông tin cửa hàng', 'store', ['địa chỉ', 'dia chi', 'hotline', 'giờ mở cửa', 'mo cua', 'liên hệ', 'cửa hàng'],
                'Cửa hàng mở cửa 8:00-21:30 tất cả các ngày trong tuần. Hotline 1900 6868, email support@techphone.vn, '
                .'địa chỉ 123 Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh.'],
        ];
        foreach ($articles as [$title, $category, $keywords, $content]) {
            KnowledgeArticle::create(['title' => $title, 'category' => $category, 'keywords' => $keywords, 'content' => $content, 'active' => true]);
        }
    }
}
