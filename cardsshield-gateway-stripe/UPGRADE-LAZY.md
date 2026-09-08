# Lazy Stripe — đổi tên và tương thích

Mã gateway chính: `m-lazy-gateway-stripe.php`. Trang quản trị:
`m-lazy-stripe-options.php`. Gateway ID mới: `lazy_stripe`.
Giao diện quản trị dùng cùng bố cục và màu với plugin PayPal.

## Cập nhật plugin

Thay toàn bộ thư mục plugin, giữ các file loader `m-ecom-*.php` đi kèm.
Website đang kích hoạt file cũ vẫn nạp mã mới qua loader, không phải kích hoạt lại.
Website cài mới chọn **CardsShield Gateway Stripe**, không chọn mục **legacy loader**.
Hai đường dẫn dùng cùng implementation; không đăng ký thêm một gateway thanh toán.

Khi plugin được nạp, cấu hình và thứ tự gateway cũ được chuyển sang tên mới.
Chỉ chuyển các khóa thuộc Stripe và giá trị enum intent, không thay nội dung người dùng.
Không ghi đè cấu hình Lazy đã tồn tại. Endpoint token/secret mang tên `OPT_CS_STRIPE_*`
không đổi.

Lần chạy `init` đầu tiên sao chép metadata Stripe của cả CPT (`postmeta`) và HPOS
(`wc_orders_meta`) sang khóa Lazy. Các hàng cũ được giữ nguyên; giá trị Lazy đã có
được ưu tiên. Migration có khóa chống chạy đồng thời, có thể chạy lại khi bị gián đoạn
và làm mới cache metadata. Stripe tạm không xuất hiện trong danh sách phương thức
khả dụng nếu migration chưa hoàn tất; trang quản trị hiển thị lỗi để xử lý quyền DB.

Đơn lịch sử có payment method `mecom_stripe` được ánh xạ sang gateway mới khi
WooCommerce đọc để các thao tác capture, cancel và refund tiếp tục hoạt động.
ID lịch sử trong DB được giữ nguyên. Phiên checkout cũ được chuyển khi WooCommerce
khởi tạo session/cart. Lịch cron cũ được chuyển sang hook mới và loại bỏ lịch trùng.

## Shield cũ và mới

Trong **WooCommerce → Settings → Payments → CardsShield Gateway Stripe** có mục
**Shield protocol**:

- **Lazy**: gửi tên lệnh `lazy-stripe-*`. Mặc định cho cài đặt mới.
- **Legacy**: gửi tên lệnh cũ tới shield chưa nâng cấp. Mặc định khi có cấu hình Mecom cũ.

Chuyển sang Lazy sau khi shield hỗ trợ tên lệnh mới.
Mỗi thao tác chỉ gửi **một** lệnh, không thử lại thanh toán bằng giao thức khác.
Iframe chấp nhận thông điệp cũ/mới từ đúng cửa sổ và origin shield, nhận biết giao thức
của iframe và phản hồi bằng cùng giao thức.

Callback, AJAX quản trị, action capture/cancel và class cũ có alias trong
`legacy-compat.php`. Các tên Mecom chỉ còn trong lớp tương thích, loader và kiểm thử.
Không xóa các file tương thích khi cập nhật website đang dùng bản cũ.

## Kiểm tra

```sh
php -d extension=pdo_sqlite test/legacy-compat.php
php -d extension=pdo_sqlite test/legacy-compat.php --canonical
node test/legacy-compat.test.js
```

Bộ kiểm tra dùng SQLite trong bộ nhớ cho câu SQL sao chép metadata và các stub
WordPress/WooCommerce cho bootstrap, options, session, cron và hooks.
Cần kiểm tra thêm thanh toán sandbox trên môi trường WordPress/WooCommerce thực tế
trước khi dùng bản cập nhật cho giao dịch thật.
