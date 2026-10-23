<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Contact;
use App\Models\Setting;
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
    }
}
