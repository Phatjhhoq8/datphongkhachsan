
# Đề tài: Xây dựng website đặt phòng khách sạn

Dưới đây là toàn bộ nội dung yêu cầu và đặc tả của đề tài được trích xuất đầy đủ từ các tài liệu hình ảnh trong thư mục `yeucau/`.

---

## 1. Mục tiêu đề tài

* **Đối với khách hàng:** Mang lại trải nghiệm tìm kiếm và đặt phòng trực quan, chân thực nhờ công nghệ xem phòng 3D (Virtual Tour), tìm kiếm giọng nói và quy trình thanh toán, đặt cọc quốc tế tiện lợi.
* **Đối với nhà quản lý:** Cung cấp công cụ quản trị khách sạn mạnh mẽ, tự động hóa quy trình check-in/check-out, và phân tích sâu dữ liệu vận hành (công suất phòng, hành vi khách hàng) để tối ưu giá phòng theo mùa.
* **Đối với kỹ thuật:** Làm chủ kiến trúc hệ thống xử lý song song (Real-time booking để tránh trùng lịch phòng) và vận dụng kiến trúc lưu trữ phân tách theo mục đích sử dụng.

---

## 2. Các tính năng chính

### 2.1. Người dùng (Khách đặt phòng)

* **Tài khoản & Bảo mật:**
  * Đăng nhập bằng tài khoản và mật khẩu thông thường (Email & Mật khẩu).
  * Đăng nhập bằng tài khoản bên ngoài (Facebook, Google OAuth).
  * Đăng ký tài khoản (Xác thực thông thường hoặc liên kết Facebook, Gmail).
  * Cấp lại mật khẩu.
* **Trải nghiệm tìm kiếm & Chọn phòng thông minh:**
  * Xem chi tiết phòng với hình ảnh thực tế.
  * Tìm kiếm bằng giọng nói (Ví dụ: *"Tìm phòng Deluxe cho 2 người ngày mai"*).
  * Tìm kiếm, lọc phòng theo: Ngày nhận/trả phòng, địa điểm/chi nhánh, mức giá, hạng phòng (Standard, Deluxe, Suite), tiện ích (Wifi, hồ bơi, ăn sáng).
* **Đặt phòng & Thanh toán:**
  * Chọn dịch vụ đi kèm (Đưa đón sân bay, thuê xe, spa...).
  * Thanh toán đặt cọc hoặc trả thẳng qua PayPal.
  * Quản lý lịch sử đặt phòng (Booking History).
  * Theo dõi trạng thái đặt phòng (Chờ xác nhận, Đã đặt cọc, Đã check-in, Đã trả phòng).
  * Hủy lịch đặt phòng (Theo chính sách hoàn hủy của khách sạn).
* **Tương tác & Ưu đãi:**
  * Đánh giá, chấm điểm dịch vụ phòng (Rating & Review sau khi check-out).
  * Lưu phòng yêu thích (Wishlist).
  * Nhận mã giảm giá, khuyến mãi (Voucher theo mùa).

### 2.2. Quản lý (Admin / Quản lý khách sạn)

* **Quản trị hệ thống:**
  * Đăng nhập, phân quyền (Quản lý tổng, Lễ tân, Kế toán).
  * Quản lý thông tin khách sạn/chi nhánh, danh mục hạng phòng và quản lý từng phòng cụ thể (Sơ đồ phòng).
  * Quản lý trạng thái phòng theo thời gian thực (Trống, Đang ở, Đang dọn dẹp, Đang bảo trì).
  * Quản lý tài khoản người dùng và thông tin khách hàng.
  * Quản lý đơn đặt phòng (Booking) và lịch check-in/check-out.
* **Hệ thống báo cáo & Phân tích thông minh (Analytics):**
  * Thống kê doanh thu, lợi nhuận (theo ngày, tháng, năm / theo chi nhánh hoặc hạng phòng).
  * Thống kê lịch sử giao dịch, hóa đơn.
  * Thống kê thời gian sử dụng/lướt xem trang web của khách hàng.
  * Thống kê công suất sử dụng phòng (Hạng phòng nào được đặt nhiều nhất, tỷ lệ lấp đầy phòng).
  * Thông báo/Cảnh báo hạng phòng ít tương tác/ít được đặt trong tháng để điều chỉnh giá.
  * Thống kê tỉ lệ quay lại đặt phòng của khách hàng cũ (Loyalty).
  * Thống kê độ hài lòng của khách hàng dựa trên điểm đánh giá phòng và dịch vụ.

### 2.3. Các tính năng mở rộng (Phát triển sau)

* **Không gian phòng 3D (Virtual Tour):** Xem chi tiết phòng và tương tác trực quan trong không gian 3D sử dụng Three.js hoặc A-Frame.
* **Chatbox tư vấn thời gian thực:** Chatbox hỗ trợ khách đặt phòng trên Frontend và trang quản trị trung tâm của nhân viên/admin trên Backend để tư vấn trực tiếp (Node.js + Socket.io + MongoDB).

---

## 3. Công nghệ sử dụng

Kiến trúc giữ nguyên sự mạnh mẽ của đề tài trước, áp dụng chuẩn xác vào bài toán đặt phòng:

* **Ngôn ngữ:** PHP, HTML, Javascript.
* **Frontend (Giao diện):**
  * **Vue.js:** Xử lý mượt mà việc chọn ngày, chọn phòng trực quan không load lại trang.
  * **Bootstrap 4 (hoặc 5).**
  * **Three.js hoặc A-Frame:** Để render không gian phòng 3D (Virtual Tour).
* **Backend & Real-time:**
  * **Laravel (PHP):** Làm API chính xử lý logic đặt phòng, tính toán giá, quản lý hóa đơn và tích hợp PayPal.
  * **Node.js:** Xử lý Chatbox và đồng bộ Sơ đồ phòng theo thời gian thực (để khi phòng vừa có người đặt, lập tức khóa phòng đó lại trên màn hình của khách khác, tránh lỗi Overbooking).
* **Cơ sở dữ liệu (Database):**
  * **MySQL:** Lưu trữ các dữ liệu quan trọng cần tính nhất quán cao: Thông tin khách hàng, Sơ đồ phòng, Hóa đơn thanh toán, Lịch đặt phòng, Vouchers, Transactions, v.v.
  * **MongoDB:** Lưu trữ dữ liệu chat (Conversations, Messages) và dữ liệu tracking/logs hành vi người dùng (ActivityEvents). Chạy replica set `rs0` để phục vụ các tính năng MongoDB.
  * **Redis:** Phục vụ cache, queue và outbox; không lưu dữ liệu nghiệp vụ làm nguồn dữ liệu chính.
  * **Object/File Storage:** Lưu tệp ảnh thực tế và nội dung 3D (Virtual Tour); MongoDB chỉ lưu metadata và đường dẫn tham chiếu tới tệp.

---

## 4. Tài liệu & Trang web tham khảo cấu hình

Theo yêu cầu trực tiếp từ bạn, hệ thống sẽ được xây dựng tham khảo cấu hình và giao diện từ Traveloka:
* **Trang chủ đặt phòng (Giao diện tham khảo):** [Traveloka Việt Nam - Khách sạn](https://www.traveloka.com/vi-vn/hotel)
* **Giao diện & Cấu trúc URL tìm kiếm mẫu:** [Traveloka Search Spec URL](https://www.traveloka.com/vi-vn/hotel/search?spec=14-08-2026.15-08-2026.1.1.HOTEL_GEO.10010169.%C4%90%C3%A0%20L%E1%BA%A1t.2)
