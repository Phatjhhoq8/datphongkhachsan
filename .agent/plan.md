# Kế hoạch triển khai hệ thống đặt phòng

## Kiến trúc đã chốt

- Frontend: Vue 3 và Bootstrap.
- Backend nghiệp vụ: Laravel 12 REST API.
- Cơ sở dữ liệu duy nhất: MongoDB 8 chạy replica set `rs0`.
- Cache, queue và truyền outbox: Redis; Redis không phải nguồn dữ liệu nghiệp vụ.
- Realtime: Node.js và Socket.IO; Laravel/MongoDB vẫn là nguồn dữ liệu đúng.
- Ảnh và nội dung 3D: Object Storage hoặc file storage; MongoDB lưu metadata và đường dẫn.

## Nguyên tắc dữ liệu

- Toàn bộ người dùng, khách sạn, phòng, booking, giá, voucher, thanh toán, hóa đơn, review, chat và tracking lưu trong MongoDB.
- Mọi thao tác nhiều document quan trọng phải chạy trong MongoDB transaction.
- MongoDB bắt buộc là replica set trong local, test, staging và production.
- Chống overbooking bằng collection `room_nights` và unique index `(room_id, night)`.
- Booking lưu snapshot giá và `room_ids`; không tính lại hóa đơn từ bảng giá hiện tại.
- Redis hoặc Socket.IO mất kết nối không được làm mất hoặc thay đổi booking.

## Thứ tự triển khai

1. Hoàn thiện MongoDB replica set, index migration, seed và test isolation.
2. Hoàn thiện tài khoản, Sanctum token trên MongoDB và phân quyền.
3. Hoàn thiện catalog khách sạn, hạng phòng, phòng, tiện ích và media metadata.
4. Hoàn thiện tìm kiếm tồn phòng dựa trên `room_nights`.
5. Hoàn thiện booking transaction, idempotency và test cạnh tranh đồng thời.
6. Hoàn thiện voucher, dịch vụ, thanh toán, refund và hóa đơn.
7. Hoàn thiện check-in/check-out, sơ đồ phòng và outbox realtime.
8. Hoàn thiện wishlist, review, báo cáo, chat, tracking, 3D và tìm kiếm giọng nói.
9. Kiểm thử bảo mật, tải, backup/restore và triển khai production.

## Test-first bắt buộc

Mọi thay đổi hành vi phải theo thứ tự:

1. Viết test mô tả hành vi mong muốn.
2. Chạy và xác nhận test thất bại đúng lý do (RED).
3. Viết production code tối thiểu để test qua (GREEN).
4. Refactor trong khi toàn bộ test vẫn xanh.

Test transaction, unique index và concurrency phải chạy trên MongoDB replica set thật, không dùng SQLite, SQL hoặc mock repository.

## Tiêu chí hoàn thành

- Không còn dependency, service, cấu hình hoặc tài liệu MySQL/SQL Server.
- Migration MongoDB tạo đầy đủ unique, compound, partial và TTL index.
- Hai booking cạnh tranh cùng phòng/đêm chỉ có một booking thành công.
- Transaction lỗi không để lại booking, room-night, voucher, payment hoặc outbox dở dang.
- Tất cả backend test, realtime test và frontend build thành công.
- Docker Compose khởi động được toàn hệ thống chỉ với MongoDB, Redis và các service ứng dụng.
