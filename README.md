# Bảo Châu Hotel — WordPress

Dự án WordPress đầy đủ, chạy trực tiếp từ thư mục này.

- `wp-admin/`, `wp-includes/`: WordPress core.
- `wp-content/themes/baochau-hotel/`: theme Bảo Châu đang sử dụng.
- `wp-content/plugins/baochau-booking/`: plugin đặt phòng, cọc 30%, quản lý tồn và hết hạn giữ chỗ.
- `wp-config.php`: cấu hình local, không đưa vào Git.
- `.local/mysql/`: database local hiện có, giữ nguyên.
- `.local/admin-access.txt`: tài khoản quản trị local hiện có.

## Chạy trên máy này

Tại thư mục dự án:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File start-local.ps1
```

- Website: http://127.0.0.1:8088
- Quản trị: http://127.0.0.1:8088/wp-admin/
- PHP dùng XAMPP; MariaDB riêng cổng 3307. Không cần chép lại theme/plugin sau mỗi lần sửa.
- Script dùng router local để chặn truy cập `.local/`, `.git/` và các file phụ. Không chạy PHP server từ gốc dự án mà bỏ router.

## Chỉnh nội dung

- **Giao diện → Tùy biến**: logo, banner, giới thiệu, tiện ích, liên hệ và nhãn demo.
- **Bảo Châu → Hạng phòng**: số lượng, sức chứa, giá, ngày đóng bán, mô tả và ảnh.
- **Bảo Châu → Cấu hình**: ngân hàng, liên hệ lễ tân, thời gian giữ chỗ và email.
- **Bảo Châu → Đơn đặt phòng**: xem đơn, xác nhận đã nhận đủ cọc hoặc hủy đơn.

Hiện vẫn là dữ liệu demo, chưa mở giữ chỗ thật. Cần thay nội dung, nhập ngân hàng và kiểm tra SMTP/cron trước khi vận hành. Các kiểm thử trước đây đạt 43 mục; kiểm tra trực quan desktop/mobile chưa hoàn tất.

## Các file đã dọn

File ZIP, test, tài liệu bàn giao chi tiết, công cụ dựng dự án và bản sao theme/plugin đã chuyển vào `.local/backup-cleanup/`. Đây là bản dự phòng có thể khôi phục, không tham gia chạy website. Không đưa `.local/`, `.git/` hoặc cấu hình database local lên hosting.

Nếu triển khai hosting, tạo cấu hình database riêng, bỏ/thay URL local `WP_HOME` và `WP_SITEURL`, dùng HTTPS và thiết lập cron mỗi phút cho `wp-cron.php` vì cấu hình local đang tắt WP-Cron theo lượt truy cập. Hướng dẫn cPanel, SMTP và cache chi tiết còn trong `.local/backup-cleanup/README.md`.
