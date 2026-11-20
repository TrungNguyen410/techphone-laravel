<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PolicyController extends Controller
{
    public const POLICIES = [
        'warranty' => [
            'eyebrow' => 'Hậu mãi',
            'title' => 'Chính sách bảo hành',
            'intro' => 'Thời hạn bảo hành cụ thể được ghi trên trang sản phẩm, đơn hàng hoặc phiếu bảo hành đi kèm.',
            'sections' => [
                ['Phạm vi áp dụng', 'Điện thoại và phụ kiện mua tại TechPhone, còn thông tin nhận diện đơn hàng và thuộc thời hạn bảo hành công bố cho từng sản phẩm.'],
                ['Cách yêu cầu hỗ trợ', 'Chuẩn bị mã đơn hàng, mô tả lỗi và hình ảnh nếu có; liên hệ hotline, email hoặc mang sản phẩm đến cửa hàng để kiểm tra.'],
                ['Kết quả xử lý', 'TechPhone kiểm tra tình trạng thực tế trước khi xác định sửa chữa, đổi sản phẩm hoặc chuyển trung tâm bảo hành của hãng.'],
            ],
        ],
        'returns' => [
            'eyebrow' => 'Mua sắm an tâm',
            'title' => 'Chính sách đổi trả',
            'intro' => 'Website đang áp dụng mốc tham chiếu 30 ngày cho sản phẩm đáp ứng điều kiện; quyết định cuối cùng dựa trên kiểm tra thực tế.',
            'sections' => [
                ['Điều kiện tiếp nhận', 'Sản phẩm đúng đơn hàng, còn phụ kiện đi kèm và không có hư hỏng do sử dụng sai hướng dẫn, va đập hoặc vào nước.'],
                ['Sản phẩm lỗi kỹ thuật', 'TechPhone tiếp nhận kiểm tra và áp dụng phương án đổi, sửa hoặc bảo hành theo kết luận kỹ thuật và chính sách của hãng.'],
                ['Hoàn tiền', 'Phương thức và thời gian hoàn tiền phụ thuộc kênh thanh toán ban đầu và kết quả đối soát giao dịch.'],
            ],
        ],
        'shipping' => [
            'eyebrow' => 'Giao nhận',
            'title' => 'Chính sách vận chuyển',
            'intro' => 'TechPhone giao hàng toàn quốc. Phí và ngày dự kiến được tính lại trên server theo tỉnh/thành và giá trị đơn.',
            'sections' => [
                ['Phí giao hàng', 'Đơn từ 10.000.000đ được miễn phí. Các mức phí còn lại hiển thị tại checkout trước khi khách xác nhận đơn.'],
                ['Theo dõi đơn', 'Mã vận đơn, đơn vị vận chuyển và ngày giao dự kiến xuất hiện trong trang tra cứu ngay khi nhân viên cập nhật.'],
                ['Khi nhận hàng', 'Kiểm tra tình trạng kiện hàng và thông tin người nhận trước khi ký nhận; liên hệ TechPhone ngay nếu bao bì có dấu hiệu bất thường.'],
            ],
        ],
        'payment' => [
            'eyebrow' => 'Thanh toán',
            'title' => 'Chính sách thanh toán',
            'intro' => 'Khách có thể chọn COD, chuyển khoản VietQR theo mã đơn hoặc cổng VNPay khi merchant đã được cấu hình.',
            'sections' => [
                ['Chuyển khoản VietQR', 'Sau khi đặt hàng, khách quét mã VietQR với nội dung là mã đơn hàng. TechPhone không thu thập hoặc lưu thông tin thẻ.'],
                ['Xác nhận tự động', 'Đơn chuyển sang đã thanh toán khi server nhận webhook SePay hợp lệ và số tiền chuyển đủ so với tổng đơn.'],
                ['Thanh toán online', 'Thông tin thẻ được nhập trên cổng VNPay. Đơn chỉ chuyển sang đã thanh toán sau khi server nhận thông báo có chữ ký hợp lệ và số tiền khớp.'],
                ['Đối soát thủ công', 'Nếu giao dịch không tự khớp (ghi sai nội dung, chuyển thiếu), đơn ở trạng thái chờ cho tới khi nhân viên đối soát.'],
            ],
        ],
        // Facebook Login (Meta) yêu cầu đường dẫn chính sách quyền riêng tư và hướng dẫn xóa dữ liệu: /privacy và /xoa-du-lieu.
        'privacy' => [
            'route' => 'privacy',
            'eyebrow' => 'Quyền riêng tư',
            'title' => 'Chính sách quyền riêng tư',
            'intro' => 'Trang này cho biết TechPhone thu thập dữ liệu gì khi bạn mua hàng hoặc đăng nhập, dùng vào việc gì và bạn có những quyền gì với dữ liệu đó.',
            'sections' => [
                ['Dữ liệu chúng tôi thu thập', 'Khi đăng ký bằng số điện thoại: họ tên, số điện thoại và mật khẩu (lưu dạng mã hóa). Khi đăng nhập bằng Google hoặc Facebook: họ tên, địa chỉ email và ảnh đại diện mà Google/Facebook cho phép chia sẻ (quyền email và public_profile). Khi đặt hàng: tên, số điện thoại, địa chỉ nhận hàng và nội dung đơn hàng. Khi trò chuyện với trợ lý AI: nội dung tin nhắn bạn gửi.'],
                ['Mục đích sử dụng', 'Tạo và đăng nhập tài khoản, xử lý và giao đơn hàng, xác nhận thanh toán, trả lời câu hỏi của bạn và cải thiện tư vấn của trợ lý AI. Chúng tôi không bán dữ liệu cá nhân và không dùng dữ liệu Facebook/Google để quảng cáo.'],
                ['Bên thứ ba', 'Đăng nhập Google/Facebook chạy qua Firebase Authentication (Google). Trợ lý AI gửi nội dung câu hỏi tới Google Gemini để soạn câu trả lời. Thông tin giao hàng được chia sẻ với đơn vị vận chuyển; thanh toán online đi qua cổng thanh toán (VNPay, ngân hàng). Mỗi bên chỉ nhận phần dữ liệu cần cho việc của họ.'],
                ['Lưu trữ và bảo vệ', 'Dữ liệu được lưu trên máy chủ cơ sở dữ liệu của cửa hàng, truy cập qua kết nối mã hóa. Mật khẩu được băm, không ai đọc được. Dữ liệu đơn hàng được giữ trong thời gian cần cho bảo hành, đối soát và nghĩa vụ kế toán.'],
                ['Quyền của bạn', 'Bạn có thể xem và sửa thông tin trong trang Tài khoản, và yêu cầu xóa tài khoản cùng dữ liệu cá nhân theo hướng dẫn ở trang Xóa dữ liệu người dùng.'],
            ],
        ],
        'data-deletion' => [
            'route' => 'data-deletion',
            'eyebrow' => 'Quyền riêng tư',
            'title' => 'Hướng dẫn xóa dữ liệu người dùng',
            'intro' => 'Bạn có thể yêu cầu xóa tài khoản TechPhone và dữ liệu cá nhân bất cứ lúc nào, kể cả khi bạn đăng nhập bằng Facebook hoặc Google.',
            'sections' => [
                ['Cách gửi yêu cầu', 'Gửi yêu cầu qua trang Liên hệ hoặc email/hotline của cửa hàng ở cuối trang, với tiêu đề "Yêu cầu xóa dữ liệu". Ghi rõ số điện thoại hoặc email của tài khoản và cách bạn đăng nhập (số điện thoại, Google hay Facebook).'],
                ['Chúng tôi xử lý thế nào', 'Sau khi xác minh bạn là chủ tài khoản, chúng tôi xóa tài khoản, họ tên, số điện thoại, email, địa chỉ đã lưu, danh sách yêu thích, lịch sử trò chuyện và voucher chưa dùng trong vòng 30 ngày, rồi báo lại cho bạn. Đơn hàng và đánh giá đã phát sinh chỉ được giữ ở dạng ẩn danh để phục vụ chứng từ kế toán. Nếu còn đơn đang xử lý, việc xóa được thực hiện sau khi đơn hoàn tất hoặc được hủy.'],
                ['Gỡ quyền của ứng dụng trên Facebook', 'Trên Facebook, vào Cài đặt và quyền riêng tư → Cài đặt → Ứng dụng và trang web, chọn TechPhone rồi bấm Gỡ. Facebook sẽ ngừng chia sẻ dữ liệu với chúng tôi; để xóa dữ liệu đã lưu tại cửa hàng, hãy gửi yêu cầu như trên.'],
                ['Gỡ quyền trên Google', 'Vào myaccount.google.com → Bảo mật → Ứng dụng và dịch vụ của bên thứ ba, chọn TechPhone rồi xóa quyền truy cập.'],
            ],
        ],
    ];

    /** Đường dẫn của một trang chính sách (quyền riêng tư và xóa dữ liệu có đường dẫn riêng). */
    public static function url(string $type): string
    {
        $route = self::POLICIES[$type]['route'] ?? null;

        return $route ? route($route) : route('policies.show', $type);
    }

    public function __invoke(string $type): View
    {
        abort_unless(isset(self::POLICIES[$type]), 404);

        return view('shop.policy', ['type' => $type, 'policy' => self::POLICIES[$type], 'policies' => self::POLICIES]);
    }

    public function privacy(): View
    {
        return $this('privacy');
    }

    public function dataDeletion(): View
    {
        return $this('data-deletion');
    }
}
