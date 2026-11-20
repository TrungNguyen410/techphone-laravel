<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Accessory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\ShippingAddress;
use App\Support\VietnamesePhone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrderService
{
    public function __construct(
        private readonly ShippingService $shipping,
        private readonly VoucherService $vouchers,
    ) {}

    /**
     * Tạo đơn hàng trong một transaction: kiểm tra tồn kho, giữ voucher, trừ kho, sinh mã đơn.
     * Cùng một $checkoutKey gửi lại (bấm 2 lần, F5) sẽ trả về đơn đã tạo thay vì tạo đơn mới.
     *
     * Đơn đầu tiên tự lưu địa chỉ nhận hàng vào tài khoản; các đơn sau chỉ lưu khi khách chọn
     * "Lưu làm địa chỉ mặc định" (save_address = true).
     *
     * @param  array{items: array<int, array{type: string, id: int, quantity: int}>, customer: array<string, string>,
     *     payment_method?: string, voucher_code?: ?string, note?: ?string, save_address?: bool}  $data
     */
    public function create(array $data, ?User $user, ?string $checkoutKey = null): Order
    {
        $order = $this->placeOrder($data, $user, $checkoutKey);
        $this->rememberShippingAddress($user, $order, (bool) ($data['save_address'] ?? false));

        return $order;
    }

    /**
     * Lưu thông tin người nhận của đơn vào tài khoản để lần sau không phải nhập lại.
     * Đơn đã tạo xong nên lỗi ở bước tiện ích này không được làm hỏng việc đặt hàng.
     */
    public function rememberShippingAddress(?User $user, Order $order, bool $requested): void
    {
        if (! $user || (! $requested && $user->savedShippingAddress())) {
            return;
        }

        $address = ShippingAddress::from([
            'full_name' => $order->customer_name,
            'email' => $order->customer_email,
            'phone' => $order->customer_phone,
            'address' => $order->customer_address,
            'province' => $order->province,
            'ward' => $order->ward,
        ]);
        if (! ShippingAddress::isComplete($address)) {
            return;
        }

        try {
            $user->forceFill(['shipping_address' => $address])->save();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function placeOrder(array $data, ?User $user, ?string $checkoutKey): Order
    {
        $customer = $this->canonicalCustomer($data['customer']);
        $paymentMethod = $data['payment_method'] ?? 'cod';
        $fingerprint = $this->fingerprint($data, $customer, $paymentMethod);
        $idempotencyKey = $this->idempotencyKey($checkoutKey, $user, $customer);

        if ($idempotencyKey && $existing = $this->findByIdempotencyKey($idempotencyKey, $fingerprint)) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($data, $user, $customer, $paymentMethod, $fingerprint, $idempotencyKey) {
                $items = $this->resolveItems($this->groupItems($data['items'] ?? []));
                if ($items === []) {
                    throw new BusinessException('Đơn hàng phải có ít nhất một sản phẩm');
                }

                $subtotal = array_sum(array_map(fn ($item) => $item['price'] * $item['quantity'], $items));
                $quote = $this->shipping->quote($customer['province'], $subtotal);
                $voucher = ! empty($data['voucher_code']) ? $this->vouchers->reserve($data['voucher_code'], $subtotal, $user) : null;
                $discount = $voucher?->discountFor($subtotal, $quote['fee']) ?? 0;

                $this->decrementInventory($items);
                $orderNumber = $this->nextOrderNumber();

                $order = Order::create([
                    'order_number' => $orderNumber,
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $fingerprint,
                    'user_id' => $user?->id,
                    'status' => 'pending',
                    'subtotal' => $subtotal,
                    'shipping_fee' => $quote['fee'],
                    'discount' => $discount,
                    'total' => max(0, $subtotal + $quote['fee'] - $discount),
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'pending',
                    'shipping_provider' => ShippingService::PROVIDER,
                    // Lấy từ mã đơn (duy nhất) nên hai đơn tạo cùng lúc không bao giờ trùng mã vận đơn.
                    'tracking_number' => 'TPX'.substr($orderNumber, 2),
                    'estimated_delivery' => now()->addDays($quote['days']),
                    'customer_name' => $customer['full_name'],
                    'customer_email' => $customer['email'],
                    'customer_phone' => $customer['phone'],
                    'customer_address' => $customer['address'],
                    'province' => $customer['province'],
                    'district' => $customer['district'],
                    'ward' => $customer['ward'],
                    'note' => trim((string) ($data['note'] ?? '')),
                    'voucher_code' => $voucher?->code,
                ]);

                $order->items()->createMany(array_map(fn ($item) => [
                    'type' => $item['type'],
                    'product_id' => $item['type'] === 'product' ? $item['id'] : null,
                    'accessory_id' => $item['type'] === 'accessory' ? $item['id'] : null,
                    'name' => $item['name'],
                    'image' => $item['image'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'total' => $item['price'] * $item['quantity'],
                ], $items));

                return $order->load('items');
            });
        } catch (UniqueConstraintViolationException $exception) {
            // Hai request cùng khóa chạy song song: request thua trả về đơn của request thắng.
            if ($idempotencyKey && $existing = $this->findByIdempotencyKey($idempotencyKey, $fingerprint)) {
                return $existing;
            }

            throw $exception;
        }
    }

    /**
     * Hủy đơn, hoàn tồn kho và trả lượt dùng voucher.
     * Khách chỉ tự hủy được khi đơn còn "Chờ xác nhận"; quản trị viên hủy được cả đơn đã xác nhận.
     */
    public function cancel(Order $order, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $actor) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($actor && ! $actor->isAdmin() && $order->user_id !== $actor->id) {
                throw new BusinessException('Bạn không có quyền truy cập đơn hàng này', 403);
            }
            if ($order->status === 'cancelled') {
                return $order;
            }
            if ($order->isPaymentSettled()) {
                throw new BusinessException('Không thể tự động hủy đơn hàng đã thanh toán');
            }
            if (! in_array($order->status, ['pending', 'confirmed'], true)) {
                throw new BusinessException('Đơn hàng không còn có thể hủy');
            }
            if ($actor && ! $actor->isAdmin() && $order->status !== 'pending') {
                throw new BusinessException('Đơn hàng đã được cửa hàng xác nhận nên bạn không thể tự hủy. Vui lòng liên hệ cửa hàng để được hỗ trợ.');
            }

            $order->update(['status' => 'cancelled']);
            $this->restoreInventory($order);

            if ($order->voucher_code && ! $order->voucher_usage_released) {
                $this->vouchers->release($order->voucher_code);
                $order->update(['voucher_usage_released' => true]);
            }

            return $order->fresh('items');
        });
    }

    public function updateStatus(Order $order, string $status): Order
    {
        if (! array_key_exists($status, Order::STATUSES)) {
            throw new BusinessException('Trạng thái đơn hàng không hợp lệ');
        }
        if ($order->status === $status) {
            return $order;
        }
        if (! in_array($status, $order->nextStatuses(), true)) {
            throw new BusinessException(sprintf(
                'Không thể đổi trạng thái đơn hàng từ %s sang %s',
                mb_strtolower($order->statusLabel()),
                mb_strtolower(Order::statusLabelOf($status)),
            ));
        }
        if ($status === 'cancelled') {
            return $this->cancel($order);
        }

        $order->update(['status' => $status]);

        return $order;
    }

    /** @param array{shipping_provider?: ?string, tracking_number?: ?string, estimated_delivery?: ?string} $data */
    public function updateShipping(Order $order, array $data): Order
    {
        $order->update([
            'shipping_provider' => $data['shipping_provider'] ?? '',
            'tracking_number' => $data['tracking_number'] ?? '',
            'estimated_delivery' => $data['estimated_delivery'] ?? null,
        ]);

        return $order;
    }

    /**
     * Sửa thông tin người nhận và ghi chú (chỉ những trường an toàn: không đổi giá, phí ship hay sản phẩm).
     *
     * @param  array{full_name: string, phone: string, email?: ?string, address: string, province: string, ward: string, order_note?: ?string}  $data
     */
    public function updateCustomer(Order $order, array $data): Order
    {
        if (! $order->canEditReceiver()) {
            throw new BusinessException('Chỉ sửa được thông tin người nhận khi đơn đang chờ xác nhận hoặc đã xác nhận (chưa giao cho vận chuyển)');
        }

        $customer = $this->canonicalCustomer([...$data, 'district' => '']);

        // Phí ship đã chốt theo tỉnh lúc đặt: không cho đổi sang tỉnh có mức phí khác (tổng tiền và đối soát sẽ sai).
        if ($customer['province'] !== $order->province
            && $this->shipping->quote($customer['province'], $order->subtotal)['fee'] !== $order->shipping_fee) {
            throw new BusinessException(sprintf(
                'Đổi sang %s làm thay đổi phí vận chuyển (%s → %s). Hãy hủy đơn và để khách đặt lại với địa chỉ mới.',
                $customer['province'],
                money($order->shipping_fee),
                money($this->shipping->quote($customer['province'], $order->subtotal)['fee']),
            ));
        }

        $order->update([
            'customer_name' => $customer['full_name'],
            'customer_email' => $customer['email'],
            'customer_phone' => $customer['phone'],
            'customer_address' => $customer['address'],
            'province' => $customer['province'],
            // Địa chỉ theo đơn vị hành chính mới: bỏ quận/huyện cũ khi sửa.
            'district' => '',
            'ward' => $customer['ward'],
            'note' => trim((string) ($data['order_note'] ?? '')),
        ]);

        return $order;
    }

    /**
     * Lưu trữ (xóa mềm) đơn: đơn chưa giao sẽ được hủy trước.
     */
    public function archive(Order $order): void
    {
        if (in_array($order->status, ['pending', 'confirmed'], true)) {
            $order = $this->cancel($order);
        }
        if (! in_array($order->status, ['cancelled', 'completed'], true)) {
            throw new BusinessException('Chỉ có thể lưu trữ đơn hàng đã hủy hoặc đã hoàn thành');
        }

        $order->delete();
    }

    public function lookup(string $orderNumber, string $phone): Order
    {
        $phone = VietnamesePhone::normalize($phone) ?? throw new BusinessException('Số điện thoại người nhận không hợp lệ');

        return Order::with('items')
            ->where('order_number', strtoupper(trim($orderNumber)))
            ->where('customer_phone', $phone)
            ->first() ?? throw new BusinessException('Không tìm thấy đơn hàng phù hợp', 404);
    }

    /** @param array<string, ?string> $customer */
    private function canonicalCustomer(array $customer): array
    {
        $phone = VietnamesePhone::normalize($customer['phone'] ?? '')
            ?? throw new BusinessException('Số điện thoại người nhận không hợp lệ');

        return [
            'full_name' => trim((string) ($customer['full_name'] ?? '')),
            'email' => mb_strtolower(trim((string) ($customer['email'] ?? ''))),
            'phone' => $phone,
            'address' => trim((string) ($customer['address'] ?? '')),
            'province' => trim((string) ($customer['province'] ?? '')),
            'district' => trim((string) ($customer['district'] ?? '')),
            'ward' => trim((string) ($customer['ward'] ?? '')),
        ];
    }

    private function idempotencyKey(?string $checkoutKey, ?User $user, array $customer): ?string
    {
        $key = substr(trim((string) $checkoutKey), 0, 120);
        if ($key === '') {
            return null;
        }

        $principal = $user ? 'user:'.$user->id : 'guest:'.$customer['email'].':'.$customer['phone'];

        return hash('sha256', $principal.':'.$key);
    }

    /** Dấu vân tay nội dung đơn, để phát hiện cùng khóa nhưng nội dung khác. */
    private function fingerprint(array $data, array $customer, string $paymentMethod): string
    {
        $items = $this->groupItems($data['items'] ?? []);
        usort($items, fn ($a, $b) => strcmp("{$a['type']}:{$a['id']}", "{$b['type']}:{$b['id']}"));

        return hash('sha256', json_encode([
            'items' => $items,
            'customer' => $customer,
            'payment_method' => $paymentMethod,
            'voucher_code' => strtoupper(trim((string) ($data['voucher_code'] ?? ''))),
            'note' => trim((string) ($data['note'] ?? '')),
        ]));
    }

    private function findByIdempotencyKey(string $key, string $fingerprint): ?Order
    {
        $existing = Order::with('items')->where('idempotency_key', $key)->first();

        if ($existing && $existing->request_fingerprint !== $fingerprint) {
            throw new BusinessException('Yêu cầu thanh toán này đã được dùng cho một nội dung đơn hàng khác', 409);
        }

        return $existing;
    }

    /**
     * Gộp các dòng trùng mặt hàng.
     *
     * @return array<int, array{type: string, id: int, quantity: int}>
     */
    private function groupItems(array $items): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $type = ($item['type'] ?? 'product') === 'accessory' ? 'accessory' : 'product';
            $key = $type.'-'.(int) $item['id'];
            $grouped[$key] = [
                'type' => $type,
                'id' => (int) $item['id'],
                'quantity' => ($grouped[$key]['quantity'] ?? 0) + max(1, (int) $item['quantity']),
            ];
        }

        return array_values($grouped);
    }

    /**
     * Đọc giá và tồn kho hiện tại (khóa dòng) để tạo snapshot cho đơn.
     */
    private function resolveItems(array $items): array
    {
        $resolved = [];
        foreach ($items as $item) {
            $model = $item['type'] === 'accessory' ? Accessory::class : Product::class;
            $catalogItem = $model::whereKey($item['id'])->lockForUpdate()->first();
            $label = $item['type'] === 'accessory' ? 'Phụ kiện' : 'Sản phẩm';

            if (! $catalogItem || $catalogItem->status !== 'active') {
                throw new BusinessException("{$label} hiện không khả dụng");
            }
            if ($catalogItem->stock < $item['quantity']) {
                throw new BusinessException("{$catalogItem->name} không đủ số lượng tồn kho");
            }

            $resolved[] = $item + [
                'name' => $catalogItem->name,
                'image' => $catalogItem->image,
                'price' => $catalogItem->price,
            ];
        }

        return $resolved;
    }

    private function decrementInventory(array $items): void
    {
        foreach ($items as $item) {
            $model = $item['type'] === 'accessory' ? Accessory::class : Product::class;
            $updated = $model::whereKey($item['id'])
                ->where('status', 'active')
                ->where('stock', '>=', $item['quantity'])
                ->decrement('stock', $item['quantity'], ['sold' => DB::raw('sold + '.(int) $item['quantity'])]);

            if ($updated === 0) {
                throw new BusinessException("{$item['name']} không đủ số lượng tồn kho");
            }
        }
    }

    private function restoreInventory(Order $order): void
    {
        foreach ($order->items as $item) {
            $model = $item->type === 'accessory' ? Accessory::class : Product::class;
            $quantity = (int) $item->quantity;
            $model::withTrashed()->whereKey($item->catalogId())->increment('stock', $quantity, [
                'sold' => DB::raw("CASE WHEN sold > {$quantity} THEN sold - {$quantity} ELSE 0 END"),
            ]);
        }
    }

    /** Mã đơn dạng TP + yymmdd + số thứ tự trong ngày (4 chữ số). */
    private function nextOrderNumber(): string
    {
        $datePart = now()->format('ymd');

        DB::table('order_counters')->insertOrIgnore(['date_part' => $datePart, 'value' => 0]);
        $counter = DB::table('order_counters')->where('date_part', $datePart)->lockForUpdate()->first();
        DB::table('order_counters')->where('date_part', $datePart)->update(['value' => $counter->value + 1]);

        return 'TP'.$datePart.str_pad((string) ($counter->value + 1), 4, '0', STR_PAD_LEFT);
    }
}
