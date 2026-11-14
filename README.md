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

## Bảng giá khách sạn (bản thử local)

- Trang `/phong-nghi/` và `/dat-phong/` có bảng giá ngày đêm, nghỉ giờ và tính thử theo số đêm.
- 5 loại phòng theo bảng giá khách sạn được tạo với số lượng 0; nhập số lượng thực tế tại **Bảo Châu → Hạng phòng** trước khi mở bán. Không dùng sức chứa làm số lượng phòng.
- Nhận phòng 13:00, trả phòng 12:00. Nghỉ giờ liên hệ lễ tân, chưa nhận đặt giờ online.
- Chính sách cọc 30% giữ theo cấu hình nghiệp vụ hiện có, cần khách sạn xác nhận trước vận hành.
- Hạng demo cũ được lưu trữ qua `bch_retired_rooms`; đơn cũ và dữ liệu hạng cũ được giữ nguyên.
- Dữ liệu local đã được sao lưu ở `.local/before-tariffs.sql` trước khi áp dụng. Script `scripts/apply-hotel-tariffs.php` chỉ chạy CLI, có đánh dấu để tránh chạy lặp; không tự chạy khi truy cập website.
- Ảnh, diện tích và tiện nghi của các loại phòng mới chưa được xác nhận.

## Nâng hạng miễn phí khi hết phòng

- Tồn đã xác nhận: 4 phòng 1 giường đôi và 6 phòng 2 giường đôi. Ba hạng còn lại vẫn chưa mở bán.
- **Bảo Châu → Cấu hình → Cho phép nâng hạng miễn phí khi hết phòng** để bật/tắt. Local đã bật tính năng; trạng thái nhận giữ chỗ thật vẫn theo cấu hình thanh toán hiện có.
- Chỉ đề xuất cặp 1 giường đôi → 2 giường đôi khi hạng gốc hết và hạng thay thế còn suốt kỳ ở, không đóng bán. Khách phải tích đồng ý trước khi giữ chỗ. Giá từng đêm và sức chứa áp dụng theo hạng gốc.
- `room_id` / `room_name` lưu phòng thực tế, dùng để tính tồn; `requested_room_id` / `requested_room_name` lưu loại khách chọn. `upgrade_accepted` lưu việc đồng ý. Đơn cũ giữ nguyên.
- Kiểm tra tồn, giá, cấu hình nâng hạng và sự đồng ý lại trong giao dịch giữ chỗ; thay đổi phương án phải kiểm tra lại, không tự đổi phòng âm thầm.
- `scripts/configure-room-upgrades.php` cấu hình cặp phòng và tồn một lần sau khi sao lưu; bản sao trước thay đổi ở `.local/before-upgrades.sql`.
- Chạy kiểm tra bằng `.local/runtime/php83/php.exe scripts/test-room-upgrades.php`. Kiểm tra dùng bảng tạm riêng trong kết nối, không tạo đơn thật hay gửi email.


## Cập nhật tồn 10 phòng

Theo yêu cầu mới, cả 5 loại phòng đều có 2 phòng (10 phòng đang kinh doanh). Cấu hình này thay thế số lượng 4/6 và số lượng 0 trước đây; các hạng demo đã lưu trữ không thuộc tổng này. Script `scripts/configure-ten-rooms.php` kiểm tra đơn hiện có trước khi đổi tồn.

Trạng thái hiển thị theo kỳ ở được chọn: xanh = còn X/2 phòng, đỏ = hết phòng, vàng = tạm ngừng bán. Chưa chọn ngày thì nhắc chọn ngày. Kết quả giữ các hạng hết phòng để khách nhìn thấy, khóa nút đặt khi không có phương án thay thế. Nâng hạng miễn phí vẫn giữ nguyên quy tắc và yêu cầu đồng ý.


## Đặt trực tiếp tại quầy

Trong Bảo Châu → Đơn đặt phòng, chọn **Tạo đơn tại quầy**. Email không bắt buộc. Kiểm tra phòng/giá rồi xác nhận số tiền thực thu trước khi lưu. Mặc định khách đến ở ngay (ngày nhận phải là hôm nay), thu đủ tiền và ghi trạng thái Đã nhận phòng; không ghi cọc giả cho khách trả toàn bộ tại quầy. Có lựa chọn đặt trước: chưa thu tiền (chờ cọc có hạn), hoặc đã thu đúng cọc 30%.

Đơn lưu nguồn Tại quầy, người tạo và thời điểm; dùng chung tồn và khóa giao dịch với website. Tạo đơn tại quầy yêu cầu quyền quản lý đặt phòng, không phụ thuộc nút mở nhận đặt online. Bấm lưu lại cùng yêu cầu không tạo thêm đơn. Nâng hạng hiện áp dụng theo cặp đã cấu hình, không tự đổi tùy ý giữa mọi loại phòng.


## Nghỉ giờ tại quầy

Bảo Châu → Tạo đơn tại quầy → Nghỉ giờ. Nhập giờ nhận và trả dự kiến theo giờ Việt Nam. Giá cơ bản là giá 2 giờ đầu. Sau đó tự cộng 30.000đ/giờ, giờ lẻ làm tròn lên. Ví dụ 2h30 tính 3 giờ; 3h01 tính 4 giờ. Không dùng ô phụ thu nhập tay. Giá 2 giờ đầu có thể chỉnh trong Hạng phòng; 0 là ngừng bán nghỉ giờ.

Đơn lưu loại lưu trú, giờ bắt đầu/kết thúc và bảng tính giá tại thời điểm đặt. Đơn ngày đêm cũ dùng mốc 13:00–12:00. Tồn chung tính mức sử dụng đồng thời theo khoảng thời gian [nhận, trả), kể cả qua nửa đêm; đơn giờ không chặn nguyên ngày. Phụ thu không thay đổi giờ nhận/trả đã nhập. Bản này tính và thu theo giờ trả dự kiến nhập khi tạo đơn; chưa có thao tác gia hạn/thu thêm tiền khi khách ở quá giờ. Cần cập nhật quy trình gia hạn trước khi vận hành trường hợp đó.
