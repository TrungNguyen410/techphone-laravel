<?php

namespace Database\Seeders;

use App\Models\Accessory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Đơn hàng và đánh giá mẫu. Đơn TP260601 + SĐT 0911111111 dùng để thử chức năng tra cứu đơn.
 */
class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $minhAnh = User::where('phone', '0911111111')->first();
        $hoangNam = User::where('phone', '0922222222')->first();
        $lan = User::where('phone', '0955555555')->first();
        $huy = User::where('phone', '0966666666')->first();
        $year = now()->year;

        // Các đơn đại diện cho mọi trạng thái của vòng đời đơn hàng.
        $this->createOrder('TP26062001', $minhAnh, 'pending', 'cod', [['product', 'Galaxy S25 Ultra', 1], ['accessory', 'Sạc nhanh GaN 65W', 2]],
            now()->subDays(1), voucher: 'TECH10', note: 'Vui lòng gọi trước khi giao.');
        $this->createOrder('TP26062002', $hoangNam, 'confirmed', 'bank', [['product', 'Xiaomi 15 Ultra', 1]],
            now()->subDays(2), voucher: 'GIAM200K', note: 'Đã nhận chuyển khoản.', paymentStatus: 'paid');
        $this->createOrder('TP26062003', $minhAnh, 'shipping', 'momo', [['accessory', 'AirPods Pro 2 USB-C', 1]],
            now()->subDays(3), voucher: 'FREESHIP', note: 'Đang giao hàng.', paymentStatus: 'paid');
        $this->createOrder('TP26062004', $hoangNam, 'delivered', 'cod', [['product', 'OPPO Find X8 Pro', 1]],
            now()->subDays(5), note: 'Đã giao, chờ xác nhận hoàn thành.');
        $this->createOrder('TP260601', $minhAnh, 'completed', 'cod', [['product', 'iPhone 16 Pro Max', 1]],
            now()->subDays(12), note: 'Đơn mẫu đã hoàn thành.');
        $this->createOrder('TP26062006', $hoangNam, 'cancelled', 'cod', [['product', 'vivo X200 Pro', 1]],
            now()->subDays(6), note: 'Khách hủy trước khi xác nhận.');
        $this->createOrder('TP26062007', $minhAnh, 'pending', 'bank', [['accessory', 'Loa JBL Flip 6', 1]],
            now()->subHours(5), note: 'Ma chuyen khoan: TP26062007 0911111111');

        // Lịch sử đơn đã hoàn thành trong năm để biểu đồ doanh thu có dữ liệu.
        $history = [
            [1, $lan, [['product', 'Galaxy S25', 1]]],
            [1, $huy, [['product', 'Redmi Note 14', 2]]],
            [2, $hoangNam, [['product', 'iPhone 16 Pro', 1], ['accessory', 'Ốp lưng MagSafe trong suốt', 1]]],
            [3, $lan, [['product', 'Xiaomi 14T Pro', 1]]],
            [3, $minhAnh, [['accessory', 'Apple Watch Series 10', 1]]],
            [4, $huy, [['product', 'Galaxy Z Flip6', 1]]],
            [5, $lan, [['product', 'OPPO Reno13 Pro', 1], ['accessory', 'Kính cường lực Privacy', 2]]],
            [5, $hoangNam, [['product', 'Google Pixel 9 Pro', 1]]],
            [6, $huy, [['product', 'iPhone 15 Pro Max', 1]]],
            [7, $minhAnh, [['accessory', 'Tai nghe Sony WH-1000XM5', 1]]],
            [7, $lan, [['product', 'vivo V50', 1]]],
            [8, $huy, [['product', 'Galaxy S25 Plus', 1], ['accessory', 'Galaxy Buds3 Pro', 1]]],
            [9, $hoangNam, [['product', 'OnePlus 13', 1]]],
            [9, $lan, [['product', 'HONOR 400 Pro', 1]]],
        ];
        foreach ($history as $index => [$month, $user, $items]) {
            $date = Carbon::create($year, min($month, now()->month), 10 + $index)->setTime(10, 30);
            $this->createOrder('TP'.$date->format('ymd').str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT), $user, 'completed', 'cod', $items, $date);
        }

        $this->seedReviews($minhAnh, $hoangNam, $lan);
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: int}>  $lines  [loại, tên mặt hàng, số lượng]
     */
    private function createOrder(
        string $number,
        User $user,
        string $status,
        string $method,
        array $lines,
        Carbon $createdAt,
        ?string $voucher = null,
        string $note = '',
        ?string $paymentStatus = null,
    ): void {
        $items = array_map(function ($line) {
            [$type, $name, $quantity] = $line;
            $model = ($type === 'accessory' ? Accessory::class : Product::class)::where('name', $name)->firstOrFail();

            return ['model' => $model, 'type' => $type, 'quantity' => $quantity];
        }, $lines);

        $subtotal = array_sum(array_map(fn ($item) => $item['model']->price * $item['quantity'], $items));
        $province = str_contains($user->address, 'Hà Nội') ? 'Hà Nội' : (str_contains($user->address, 'Đà Nẵng') ? 'Đà Nẵng' : 'TP. Hồ Chí Minh');
        $shippingFee = $subtotal >= 10000000 ? 0 : ($province === 'TP. Hồ Chí Minh' ? 20000 : 30000);
        $discount = match ($voucher) {
            'TECH10' => min(intdiv($subtotal, 10), 1000000),
            'GIAM200K' => 200000,
            'FREESHIP' => min(30000, $shippingFee),
            default => 0,
        };

        $order = Order::create([
            'order_number' => $number,
            'user_id' => $user->id,
            'status' => $status,
            'subtotal' => $subtotal,
            'shipping_fee' => $shippingFee,
            'discount' => $discount,
            'total' => max(0, $subtotal + $shippingFee - $discount),
            'payment_method' => $method,
            'payment_status' => $paymentStatus ?? (in_array($status, ['delivered', 'completed'], true) ? 'paid' : 'pending'),
            'shipping_provider' => 'TechPhone Express',
            'tracking_number' => 'TPX'.substr(md5($number), 0, 10),
            'estimated_delivery' => $createdAt->copy()->addDays(2),
            'customer_name' => $user->full_name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'customer_address' => $user->address,
            'province' => $province,
            'ward' => 'Phường trung tâm',
            'note' => $note,
            'voucher_code' => $voucher,
            'voucher_usage_released' => $status === 'cancelled',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        foreach ($items as $item) {
            $order->items()->create([
                'type' => $item['type'],
                'product_id' => $item['type'] === 'product' ? $item['model']->id : null,
                'accessory_id' => $item['type'] === 'accessory' ? $item['model']->id : null,
                'name' => $item['model']->name,
                'image' => $item['model']->image,
                'price' => $item['model']->price,
                'quantity' => $item['quantity'],
                'total' => $item['model']->price * $item['quantity'],
            ]);
        }
    }

    private function seedReviews(User $minhAnh, User $hoangNam, User $lan): void
    {
        $product = fn (string $name) => Product::where('name', $name)->value('id');
        $accessory = fn (string $name) => Accessory::where('name', $name)->value('id');

        $reviews = [
            [$minhAnh, $product('iPhone 16 Pro Max'), null, 5, 'Sản phẩm chất lượng, giao hàng nhanh, đóng gói cẩn thận.', 'approved', true],
            [$minhAnh, null, null, 5, 'Nhân viên tư vấn nhiệt tình, chính sách bảo hành minh bạch.', 'approved', false],
            [$hoangNam, $product('Xiaomi 15 Ultra'), null, 4, 'Camera chụp đẹp, đang chờ quản trị viên duyệt.', 'pending', false],
            [$hoangNam, $product('vivo X200 Pro'), null, 2, 'Đánh giá bị từ chối dùng để kiểm thử chức năng kiểm duyệt.', 'rejected', false],
            [$minhAnh, null, $accessory('AirPods Pro 2 USB-C'), 5, 'Chống ồn rất tốt, kết nối nhanh với iPhone.', 'approved', false],
            [$lan, $product('Galaxy S25'), null, 4, 'Máy nhỏ gọn, pin dùng cả ngày, màn hình đẹp.', 'approved', true],
            [$lan, null, null, 4, 'Website dễ dùng, tra cứu đơn hàng tiện lợi.', 'approved', false],
        ];

        foreach ($reviews as [$user, $productId, $accessoryId, $rating, $comment, $status, $verified]) {
            Review::create([
                'user_id' => $user->id, 'user_name' => $user->full_name, 'product_id' => $productId, 'accessory_id' => $accessoryId,
                'rating' => $rating, 'comment' => $comment, 'images' => [], 'verified_purchase' => $verified, 'status' => $status,
            ]);
        }
    }
}
