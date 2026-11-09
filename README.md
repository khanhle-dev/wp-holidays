# WP Holidays Hero

Plugin WordPress tự hiển thị hero section theo ngày lễ, có sẵn 30 ngày lễ Việt Nam. Ngày âm lịch được đổi sang dương lịch tự động mỗi năm theo múi giờ Việt Nam.

Yêu cầu: WordPress 6.1 trở lên, PHP 7.4 trở lên.

## Cài đặt

Chép thư mục `wp-holidays` vào `wp-content/plugins/` rồi kích hoạt. Danh sách ngày lễ được tạo ngay khi kích hoạt.

Mặc định, hero tự chèn ở đầu trang chủ vào đúng dịp lễ. Đổi tại **Hero ngày lễ → Lịch và cài đặt**.

## Đặt hero ở vị trí khác

Chọn **Tắt** ở mục tự chèn, sau đó dùng một trong hai cách:

- Block **Hero ngày lễ** trong trình soạn thảo.
- Shortcode:

```
[holiday_hero]
[holiday_hero holiday="trung-thu"]
[holiday_hero]Nội dung hiện khi không có ngày lễ[/holiday_hero]
```

Theme cần gọi `wp_body_open()` thì tính năng tự chèn mới hoạt động. Theme cũ không gọi hàm này có thể dùng `<?php echo do_shortcode( '[holiday_hero]' ); ?>`.

## Quản lý ngày lễ

Mỗi ngày lễ có ngày (dương hoặc âm), số ngày hiện trước và sau, độ ưu tiên, nội dung, nút bấm, mẫu giao diện, hiệu ứng, màu sắc và ảnh nền.

- **Bật hoặc tắt:** Đã xuất bản là bật, Bản nháp là tắt.
- **Trùng thời gian:** độ ưu tiên cao hơn được hiển thị, sau đó đến lễ gần ngày diễn ra hơn.
- **Biến trong nội dung:** `{year}` là năm dương lịch, `{lunar_year}` là can chi, ví dụ Bính Ngọ.
- **Xem trước:** dùng liên kết Xem trước trong danh sách, chỉ người có quyền sửa mới thấy.

Các lễ kỷ niệm mang tính ngành hoặc chính trị và ngày 27/7 được tạo ở dạng Bản nháp, hãy xuất bản nếu muốn dùng.

## Bộ nhớ đệm

Hero được chọn phía server theo ngày của site (Cài đặt → Tổng quan → Múi giờ). Nếu dùng plugin hoặc CDN cache trang, hãy đặt thời gian cache không quá một ngày, hoặc xóa cache lúc 00:00, để hero đổi đúng ngày.

## Tùy biến cho lập trình viên

| Filter | Công dụng |
| --- | --- |
| `wph_default_holidays` | Thêm hoặc bớt ngày lễ tạo sẵn |
| `wph_hero_html` | Sửa HTML của hero |
| `wph_today` | Đổi ngày hiện tại, tiện cho kiểm thử |

Gỡ cài đặt plugin sẽ xóa toàn bộ ngày lễ và cài đặt của plugin.
