# TechPhone – Website bán điện thoại & phụ kiện (Laravel)

Đồ án môn **Phát triển ứng dụng Web mã nguồn mở**. Đây là bản viết lại bằng **PHP / Laravel 13 + MySQL 8**
của dự án [TechPhone-Store](https://github.com/TrungNguyen410/TechPhone-Store) (bản gốc: React + Node.js/Express + MongoDB),
giữ nguyên toàn bộ nghiệp vụ và giao diện.

## Công nghệ

| Thành phần | Sử dụng |
|---|---|
| Ngôn ngữ / framework | PHP 8.4, Laravel 13 (MVC, Eloquent ORM, Blade, Form Request, Middleware, Rate Limiter) |
| CSDL | MySQL 8 (migration + seeder), phpMyAdmin để xem dữ liệu |
| Giao diện | Blade + Bootstrap 5 + CSS design token của bản gốc, JavaScript thuần (không cần build), Chart.js |
| Thanh toán | COD, chuyển khoản/MoMo (mã QR + đối soát thủ công), cổng **VNPay** (HMAC-SHA512, IPN) |
| Xác thực | Đăng nhập bằng số điện thoại, đăng ký & quên mật khẩu bằng **OTP SMS** (Twilio) |
| Môi trường | Docker Compose (PHP-Apache, MySQL, phpMyAdmin) |

## Chạy dự án

Yêu cầu: Docker Desktop.

```bash
docker compose up -d --build        # lần đầu sẽ tự composer install (mất vài phút)
docker exec -u www-data techphone-laravel-app php artisan migrate:fresh --seed
```

- Website: http://localhost:8000
- phpMyAdmin: http://localhost:8081 (CSDL `techphone`)
- MySQL từ máy host: `127.0.0.1:3307`, user `techphone` / `secret`

Tài khoản mẫu (mật khẩu `123456`):

| Vai trò | Số điện thoại | Ghi chú |
|---|---|---|
| Quản trị | 0900000000 | |
| Khách hàng | 0911111111 | Có đơn hàng mẫu |
| Khách hàng | 0922222222 | |
| Bị khóa | 0933333333 | Dùng để thử thông báo "Tài khoản đã bị khóa" |

Dữ liệu thử: đơn `TP260601` + SĐT `0911111111` để tra cứu; voucher `TECH10`, `GIAM200K`, `FREESHIP`
(`EXPIRED50`, `USEDUP`, `OFFLINE` là các mã lỗi để thử kiểm tra).

Khi chưa cấu hình Twilio, môi trường dev hiển thị luôn mã OTP trên màn hình. Khi chưa có mã VNPay sandbox
(`VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET` trong `.env`), phương thức VNPay tự ẩn ở trang thanh toán.

## Chức năng

**Khách hàng**
- Trang chủ: banner trượt, danh mục nổi bật, sản phẩm bán chạy, ưu đãi tốt nhất, phụ kiện nổi bật, gợi ý theo lịch sử xem.
- Danh sách điện thoại / phụ kiện: tìm kiếm, lọc (hãng, giá, RAM, bộ nhớ, pin, loại), sắp xếp, phân trang; gợi ý tìm kiếm tức thời ở header.
- Chi tiết sản phẩm: gallery ảnh, thông số, chọn số lượng, mua ngay, sản phẩm đã xem, phụ kiện mua kèm, sản phẩm tương tự.
- So sánh tối đa 4 điện thoại, danh sách yêu thích (lưu theo tài khoản hoặc phiên).
- Giỏ hàng (giá và tồn kho luôn lấy lại từ CSDL), mã giảm giá, thanh toán: phí ship theo tỉnh/thành, COD / chuyển khoản / MoMo / VNPay.
- Tài khoản: hồ sơ, lịch sử đơn, chi tiết đơn, hủy đơn, đặt lại, đổi mật khẩu; tra cứu đơn công khai (thông tin được che bớt).
- Đánh giá sản phẩm/phụ kiện/dịch vụ kèm ảnh (chờ duyệt, gắn nhãn "Đã mua hàng"), liên hệ, chính sách, sitemap.xml.

**Quản trị** (`/admin`)
- Tổng quan: thống kê, biểu đồ doanh thu theo tháng, phân bổ trạng thái đơn, đơn gần đây, top bán chạy.
- CRUD điện thoại, phụ kiện (tối đa 5 ảnh), danh mục, thương hiệu, banner, voucher.
- Đơn hàng: tìm/lọc, đổi trạng thái theo luồng, cập nhật vận đơn, đối soát chuyển khoản, lưu trữ.
- Khách hàng (số đơn, tổng chi tiêu, khóa/mở khóa), duyệt đánh giá, xử lý liên hệ, cài đặt cửa hàng.

## Quy tắc nghiệp vụ chính

- Luồng trạng thái đơn: `Chờ xác nhận → Đã xác nhận → Đang giao → Đã giao → Hoàn thành`; chỉ hủy được khi chưa giao và chưa thanh toán.
  Hủy đơn sẽ hoàn tồn kho và trả lại lượt dùng voucher.
- Tạo đơn trong một transaction có khóa dòng (`SELECT … FOR UPDATE`) và câu UPDATE có điều kiện, nên không bán vượt tồn kho
  và không dùng vượt số lượng voucher khi nhiều người đặt cùng lúc. Mỗi lần thanh toán có một `checkout_key` để bấm 2 lần không tạo 2 đơn.
- Phí ship: đơn từ 10 triệu miễn phí; TP.HCM 20.000đ; Hà Nội, Đà Nẵng 30.000đ; tỉnh khác 40.000đ.
- Mã đơn `TP + yymmdd + số thứ tự 4 chữ số`.
- VNPay: kiểm tra chữ ký HMAC-SHA512 và số tiền ở cả Return URL lẫn IPN, xử lý lặp lại an toàn.
- Bảo mật: CSRF, mật khẩu bcrypt, OTP lưu dạng băm (hết hạn sau 10 phút, sai tối đa 5 lần), giới hạn tần suất đăng nhập/gửi OTP/tra cứu,
  tài khoản bị khóa bị đăng xuất ngay, đổi/đặt lại mật khẩu sẽ đăng xuất các phiên khác.

## Cấu trúc mã nguồn

```
app/
  Http/Controllers/Shop    # trang khách hàng
  Http/Controllers/Auth    # đăng nhập, đăng ký OTP, quên mật khẩu
  Http/Controllers/Admin   # trang quản trị
  Http/Middleware          # phân quyền admin, chặn tài khoản bị khóa
  Models                   # Eloquent model + quan hệ
  Services                 # nghiệp vụ: OrderService, VoucherService, CartService, Payments/, Auth/OtpService...
database/migrations, seeders
resources/views            # Blade: layouts, shop, auth, admin, partials
public/css, public/js      # giao diện (CSS lấy từ bản React), JavaScript thuần
tests/                     # PHPUnit: Unit + Feature
docker/                    # Dockerfile, entrypoint, smoke-test.sh
```

## Kiểm thử

```bash
docker exec techphone-laravel-db mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS techphone_test"
docker exec techphone-laravel-app php artisan test       # PHPUnit (dùng CSDL techphone_test)
docker exec -u www-data techphone-laravel-app sh docker/smoke-test.sh # gọi thử mọi trang với 3 vai trò
```
