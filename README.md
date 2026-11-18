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
| Thanh toán | COD, chuyển khoản **VietQR theo mã đơn + tự động xác nhận qua webhook SePay**, MoMo, cổng **VNPay** (HMAC-SHA512, IPN) |
| Xác thực | Đăng nhập bằng số điện thoại, đăng ký & quên mật khẩu bằng **OTP SMS** (Twilio), **Google/Facebook** (Firebase Authentication) |
| Chatbot AI | **Google Gemini** (function calling, 7 công cụ lấy dữ liệu thật), stream SSE, chế độ offline khi không có API key |
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

Khách `0911111111` đã có **địa chỉ nhận hàng lưu sẵn** và **voucher tặng riêng** `TRIANMA2026` (Tài khoản → Voucher của tôi);
khách `0922222222` chưa lưu địa chỉ (đơn đầu tiên sẽ tự lưu).

### Cấu hình tùy chọn (`.env`)

| Biến | Tác dụng khi điền | Khi để trống |
|---|---|---|
| `GEMINI_API_KEY` (lấy miễn phí tại aistudio.google.com/apikey), `GEMINI_MODEL`, `GEMINI_FALLBACK_MODEL` | Chatbot trả lời bằng Gemini, tự gọi công cụ lấy dữ liệu cửa hàng; hết hạn mức thì tự chuyển model dự phòng/offline | Chatbot chạy **chế độ offline** (trả lời theo luật trên dữ liệu thật) |
| `SEPAY_WEBHOOK_API_KEY` | Tạo webhook SePay trỏ tới `{APP_URL}/payment/sepay/webhook`, kiểu xác thực API Key: tiền về là đơn tự chuyển "Đã thanh toán" | Nhân viên đối soát chuyển khoản thủ công |
| `FIREBASE_API_KEY`, `FIREBASE_AUTH_DOMAIN`, `FIREBASE_PROJECT_ID`, `FIREBASE_APP_ID` | Hiện nút đăng nhập Google/Facebook (bật 2 nhà cung cấp trong Firebase → Authentication → Sign-in method, thêm tên miền vào Authorized domains) | Nút tự ẩn |

Thử webhook SePay không cần tài khoản thật (thay mã đơn và số tiền):

```bash
curl -X POST http://localhost:8000/payment/sepay/webhook -H "Authorization: Apikey <SEPAY_WEBHOOK_API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{"id":1001,"gateway":"Vietcombank","content":"<MA_DON> thanh toan","transferType":"in","transferAmount":<TONG_TIEN>}'
```

## Chức năng

**Khách hàng**
- Trang chủ: banner trượt, danh mục nổi bật, sản phẩm bán chạy, ưu đãi tốt nhất, phụ kiện nổi bật, gợi ý theo lịch sử xem.
- Danh sách điện thoại / phụ kiện: tìm kiếm, lọc (hãng, giá, RAM, bộ nhớ, pin, loại), sắp xếp, phân trang; gợi ý tìm kiếm tức thời ở header.
- Chi tiết sản phẩm: gallery ảnh, thông số, chọn số lượng, mua ngay, sản phẩm đã xem, phụ kiện mua kèm, sản phẩm tương tự.
- So sánh tối đa 4 điện thoại, danh sách yêu thích (lưu theo tài khoản hoặc phiên).
- Giỏ hàng (giá và tồn kho luôn lấy lại từ CSDL), mã giảm giá, thanh toán: phí ship theo tỉnh/thành, COD / chuyển khoản / MoMo / VNPay.
- **Địa chỉ nhận hàng lưu sẵn**: đơn đầu tiên tự lưu; lần sau hiện thẻ địa chỉ, không phải nhập lại ("Giao đến địa chỉ khác" + tick
  "Lưu làm địa chỉ mặc định"). Địa chỉ theo đơn vị hành chính mới (tỉnh/thành + phường/xã, bỏ quận/huyện), gợi ý 3.321 phường/xã.
- **Chuyển khoản tự động**: đặt hàng xong mới quét VietQR với nội dung là mã đơn; trang đơn hàng tự báo "Đã nhận thanh toán" khi SePay gửi webhook.
- Tài khoản: hồ sơ, địa chỉ nhận hàng, lịch sử đơn, chi tiết đơn, **voucher của tôi**, đặt lại, đổi mật khẩu; chỉ tự hủy được đơn
  "Chờ xác nhận"; tra cứu đơn công khai (thông tin được che bớt).
- **Chatbot AI "Tư vấn AI"** trên mọi trang: tư vấn theo ngân sách/hãng/nhu cầu (nhớ tiêu chí trong cuộc trò chuyện), so sánh máy,
  tìm phụ kiện, tra cứu đơn (mã đơn + SĐT, giới hạn 10 lần/15 phút), khuyến mãi, chính sách (kho kiến thức); thẻ sản phẩm thêm
  vào giỏ ngay; đánh giá 👍/👎; trả lời dạng stream; giới hạn 20 tin/phút/IP, tối đa 1000 ký tự.
- Đăng nhập/đăng ký bằng **Google hoặc Facebook** (không gộp vào tài khoản có sẵn cùng email).
- Đánh giá sản phẩm/phụ kiện/dịch vụ kèm ảnh (chờ duyệt, gắn nhãn "Đã mua hàng"), liên hệ, chính sách, sitemap.xml.

**Quản trị** (`/admin`)
- Tổng quan: thống kê; **3 biểu đồ doanh thu dạng wireframe** theo năm (5 năm), theo tháng và theo ngày (tháng/năm hiện tại
  chỉ vẽ tới hôm nay); phân bổ trạng thái đơn; **top 5 khách hàng** chi tiêu nhiều nhất + **tặng voucher riêng** dùng 1 lần;
  **cảnh báo tồn kho** (còn ≤ 10, cả điện thoại và phụ kiện); top bán chạy gồm cả phụ kiện; đơn gần đây.
- CRUD điện thoại, phụ kiện (tối đa 5 ảnh), danh mục, thương hiệu, banner, voucher. Mọi ô ảnh đều **tải lên từ máy tính**
  (bấm chọn hoặc kéo thả, xem trước) hoặc dùng URL ảnh; danh sách sản phẩm có nhãn "Sắp hết / Hết hàng".
- **Chatbot AI**: trạng thái Gemini/offline, thống kê (phiên, tỉ lệ hài lòng, thời gian phản hồi, công cụ hay dùng), câu hỏi gần đây,
  cấu hình lời chào/câu hỏi gợi ý/bật-tắt, xem lại hội thoại; **kho kiến thức** để "dạy" chatbot chính sách cửa hàng.
- Đơn hàng: tìm/lọc, đổi trạng thái theo luồng, cập nhật vận đơn, đối soát chuyển khoản, lưu trữ.
- Khách hàng (số đơn, tổng chi tiêu, khóa/mở khóa), duyệt đánh giá, xử lý liên hệ, cài đặt cửa hàng.

## Quy tắc nghiệp vụ chính

- Luồng trạng thái đơn: `Chờ xác nhận → Đã xác nhận → Đang giao → Đã giao → Hoàn thành`; chỉ hủy được khi chưa giao và chưa thanh toán
  (khách chỉ tự hủy đơn "Chờ xác nhận", quản trị viên hủy được cả đơn đã xác nhận). Hủy đơn sẽ hoàn tồn kho và trả lại lượt dùng voucher.
- Webhook SePay: xác thực header `Apikey`, mỗi giao dịch chỉ xử lý một lần, cộng dồn các lần chuyển thiếu, đơn đã hủy mà tiền vẫn về
  thì chuyển "Cần hoàn tiền", tiền về thêm sau khi đã thanh toán được ghi chú để hoàn.
- Voucher tặng riêng: người khác nhập mã sẽ thấy "Mã giảm giá không hợp lệ", chatbot không bao giờ liệt kê mã riêng.
- Đăng nhập Google/Facebook: máy chủ tự kiểm tra chữ ký RS256 của Firebase ID token bằng khóa công khai của Google (không cần thư viện).
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
  Services                 # nghiệp vụ: OrderService, VoucherService, CartService, Payments/ (VNPay, SePay),
                           #   Auth/ (OTP, Firebase), Chat/ (ChatService, ChatTools, GeminiClient)
  Support                  # BudgetParser/CustomerNeeds (chatbot), WardDirectory (phường/xã), ShippingAddress...
database/migrations, seeders
resources/views            # Blade: layouts, shop, auth, admin, partials
public/css, public/js      # giao diện (CSS lấy từ bản React), JavaScript thuần (chat-widget.js, admin-dashboard.js...)
public/data/vn-wards.json  # phường/xã của 34 tỉnh/thành (nguồn provinces.open-api.vn v2)
tests/                     # PHPUnit: Unit + Feature
docker/                    # Dockerfile, entrypoint, smoke-test.sh
```

## Kiểm thử

```bash
docker exec techphone-laravel-db mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS techphone_test"
docker exec techphone-laravel-app php artisan test       # PHPUnit (dùng CSDL techphone_test)
docker exec -u www-data techphone-laravel-app sh docker/smoke-test.sh # gọi thử mọi trang với 3 vai trò
```
