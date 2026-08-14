# DatPhong

Website đặt phòng khách sạn với giao diện tham khảo trải nghiệm của các nền tảng OTA, không phải bản sao thương hiệu. Hệ thống sử dụng Laravel 12 làm REST API, Vue 3 làm giao diện, MySQL 8 lưu dữ liệu chính, Redis phục vụ cache/queue/outbox và Node.js + Socket.io cập nhật sơ đồ phòng theo thời gian thực.

## Khởi chạy bằng Docker

Yêu cầu Docker Desktop có Docker Compose v2. Từ thư mục gốc dự án, chạy:

```bash
docker compose up --build
```

Trên Windows có thể chạy `run.bat`; script sẽ kiểm tra Docker trước khi gọi cùng lệnh trên. Lần đầu khởi động, backend tự chờ MySQL, chạy migration, seed dữ liệu rồi mở API.

Các địa chỉ mặc định:

| Thành phần | Địa chỉ |
| --- | --- |
| Frontend Vue 3 | http://localhost:3000 |
| Backend Laravel 12 | http://localhost:8000 |
| API v1 | http://localhost:8000/api/v1 |
| Backend health | http://localhost:8000/up |
| Realtime health | http://localhost:3001/health |
| MySQL 8 trên máy host | `localhost:3306` |

Nếu cổng `3306` đã được sử dụng, tạo file `.env` từ `.env.example` và đổi `MYSQL_HOST_PORT`, ví dụ `MYSQL_HOST_PORT=3307`. Backend trong Docker vẫn kết nối nội bộ tới MySQL qua cổng `3306`.

## Cấu hình

Các giá trị development mặc định nằm trong `docker-compose.yml`. Có thể tạo `.env` ở thư mục gốc dựa trên `.env.example` để ghi đè chúng. Frontend dùng:

```dotenv
VITE_API_BASE_URL=http://localhost:8000/api/v1
VITE_SOCKET_URL=http://localhost:3001
```

Đây là URL dành cho trình duyệt trên máy host, không đổi thành hostname service `backend`. Không dùng các mật khẩu development mẫu trong môi trường triển khai thật.

Volume MySQL được tạo với tên cố định `datphong_v2_mysql`; cấu hình không tham chiếu volume cũ `datphong_hotelio-mysql`.

## Dữ liệu demo

Seeder được chạy tự động khi backend khởi động và có thể chạy lặp lại an toàn. Hệ thống tạo khách sạn An Nhiên Đà Lạt với 4 hạng phòng, 20 phòng vật lý, tiện ích, dịch vụ, voucher và hình ảnh được bảo toàn từ dữ liệu cũ. Khách có thể đặt phòng không cần tài khoản; người dùng đăng nhập có lịch sử, wishlist và review.

Tài khoản nhân viên development:

| Vai trò | Email | Mật khẩu |
| --- | --- | --- |
| Super admin | `admin@staygo.local` | `Admin123!` |
| Lễ tân | `reception@staygo.local` | `Reception123!` |
| Kế toán | `accountant@staygo.local` | `Account123!` |

Các mật khẩu này chỉ dành cho môi trường local và phải được thay thế khi triển khai thật.

## Chức năng chính

- Tìm kiếm theo ngày, khách, hạng phòng, hoàn hủy, số sao và giá; tồn phòng được tính theo booking trùng ngày.
- Quote và booking transactional, khóa tồn phòng, idempotency, dịch vụ bổ sung và voucher.
- Thanh toán giả lập Card, PayPal, VietQR; lễ tân có thể ghi nhận tiền mặt tại quầy.
- Sanctum Bearer auth, OTP đặt lại mật khẩu demo, RBAC và giới hạn dữ liệu theo khách sạn.
- Admin CRUD, booking tại quầy, check-in/out, dọn phòng, hóa đơn, review moderation và analytics.
- Socket.io nhận domain events từ transactional outbox qua Redis; polling 10 giây là fallback cho sơ đồ phòng.

## Lệnh thường dùng

Xem trạng thái và log:

```bash
docker compose ps
docker compose logs -f backend frontend realtime outbox-publisher queue-worker
```

Chạy test backend và kiểm tra build frontend khi hệ thống đang chạy:

```bash
docker compose exec backend php artisan test
docker compose exec frontend npm run build
docker compose exec realtime npm test
```

Dừng dịch vụ nhưng giữ dữ liệu:

```bash
docker compose down
```

### Reset database

Reset sẽ xóa toàn bộ dữ liệu và chỉ nên dùng khi chủ động cần làm sạch môi trường development. Không dùng lệnh này như cách khởi động thông thường:

```bash
docker compose exec backend php artisan migrate:fresh --seed --force
```

Muốn xóa cả volume sau khi đã dừng hệ thống, chạy riêng `docker volume rm datphong_v2_mysql`. Hãy kiểm tra tên volume và sao lưu dữ liệu cần thiết trước khi thực hiện.

## Cấu trúc

```text
DatPhong/
|-- backend/                 Laravel 12 REST API
|   |-- app/                 Models, services, controllers
|   |-- database/            Migrations, factories, seeders
|   |-- routes/              Định tuyến web/API
|   `-- docker/              Entrypoint của backend
|-- frontend/                Vue 3 + Vite
|   |-- public/              Tài nguyên tĩnh
|   `-- src/                 Giao diện ứng dụng
|-- realtime/                Node.js + Socket.io Redis subscriber
|-- docker-compose.yml       MySQL, Redis, backend, workers, realtime, frontend
|-- .env.example             Cấu hình Docker mẫu
`-- run.bat                  Khởi chạy nhanh trên Windows
```

## Trạng thái tích hợp

Card, PayPal và VietQR hiện là sandbox giả lập, không kết nối payment provider và không xử lý tiền thật. Backend không nhận hoặc lưu CVC/số thẻ đầy đủ. Các client secret và webhook không được cấu hình trong repository cho tới khi provider thật được triển khai và kiểm thử.
