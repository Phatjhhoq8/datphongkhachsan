# DatPhong

Website đặt phòng khách sạn với giao diện tham khảo trải nghiệm của các nền tảng OTA, không phải bản sao thương hiệu. Hệ thống sử dụng Laravel 12 làm REST API, Vue 3 làm giao diện, MySQL làm cơ sở dữ liệu nghiệp vụ chính (thông tin khách hàng, sơ đồ phòng, hóa đơn, lịch đặt phòng, v.v.), MongoDB 8 (chạy replica set `rs0`) lưu lịch sử chat và logs/tracking, Redis phục vụ cache/queue/outbox, còn Node.js + Socket.io cập nhật sơ đồ phòng và chat theo thời gian thực. Ảnh phòng được lưu trên file storage; MongoDB/MySQL chỉ lưu metadata và đường dẫn tham chiếu. Virtual Tour 3D được để dành cho giai đoạn mở rộng.

## Khởi chạy bằng Docker

Yêu cầu Docker Desktop có Docker Compose v2. Từ thư mục gốc dự án, chạy:

```bash
docker compose up --build
```

Trên Windows có thể chạy `run.bat`; script sẽ kiểm tra Docker trước khi gọi cùng lệnh trên. Lần đầu khởi động, dịch vụ `mongo-init` tự khởi tạo replica set `rs0`; backend chờ MongoDB có primary, tạo index, seed dữ liệu rồi mở API. Scheduler tự giải phóng booking online quá hạn giữ phòng.

Các địa chỉ mặc định:

| Thành phần | Địa chỉ |
| --- | --- |
| Frontend Vue 3 | http://localhost:3000 |
| Backend Laravel 12 | http://localhost:8000 |
| API v1 | http://localhost:8000/api/v1 |
| Backend health | http://localhost:8000/up |
| Realtime health | http://localhost:3001/health |
| MongoDB 8 trên máy host | `localhost:27017` |
| MySQL 8.0 trên máy host | `localhost:3306` |

Nếu cổng `27017` đã được sử dụng, tạo file `.env` từ `.env.example` và đổi `MONGODB_HOST_PORT`, ví dụ `MONGODB_HOST_PORT=27018`. Backend trong Docker vẫn kết nối tới `mongodb:27017` bằng URI `mongodb://mongodb:27017/datphong?replicaSet=rs0`. Redis chỉ được các service trong Docker sử dụng qua `redis:6379`, không công khai cổng ra máy host.

## Cấu hình

Các giá trị development mặc định nằm trong `docker-compose.yml`. Có thể tạo `.env` ở thư mục gốc dựa trên `.env.example` để ghi đè chúng. Frontend dùng:

```dotenv
VITE_API_BASE_URL=http://localhost:8000/api/v1
VITE_SOCKET_URL=http://localhost:3001
```

Đây là URL dành cho trình duyệt trên máy host, không đổi thành hostname service `backend`. Không dùng các mật khẩu development mẫu trong môi trường triển khai thật.

MongoDB lưu dữ liệu bền vững trong volume `datphong_v2_mongodb`; ảnh upload lưu trong `datphong_v2_public_storage`. Redis chỉ giữ cache, queue và outbox, không phải nguồn dữ liệu nghiệp vụ. Không lưu nội dung nhị phân trong MongoDB hoặc Redis.

Google/Facebook OAuth mặc định bị tắt. Provider chỉ được bật khi khai báo đủ `CLIENT_ID`, `CLIENT_SECRET` và `REDIRECT_URI` tương ứng trong `.env`; hệ thống không chứa credential mẫu.

## Dữ liệu động

Seeder không tạo dữ liệu nghiệp vụ cứng như khách sạn, khu vực, hạng phòng, phòng vật lý, dịch vụ hoặc voucher. Các dữ liệu này nằm trong MongoDB và được quản lý qua Admin/API; trang chủ, tìm kiếm, điểm đến, voucher và phòng đều đọc từ API. Nếu trước đó đã seed dữ liệu mẫu, dữ liệu vẫn nằm trong volume MongoDB và có thể sửa/xóa trực tiếp qua Admin/API mà không cần sửa source code.

Endpoint điểm đến lấy động từ khách sạn đang hoạt động:

```bash
GET http://localhost:8000/api/v1/destinations
```

Seeder chỉ có thể tạo tài khoản super admin ban đầu nếu khai báo biến môi trường, ví dụ:

```dotenv
SEED_ADMIN_EMAIL=admin@example.test
SEED_ADMIN_PASSWORD=change-me
SEED_ADMIN_NAME=System Admin
```

Không commit dữ liệu vận hành hoặc mật khẩu thật vào repository. Muốn nạp lại seeder tối thiểu khi hệ thống đang chạy, dùng `docker compose exec backend php artisan db:seed --force`.

### Tài khoản mặc định hệ thống

Sau khi cơ sở dữ liệu được khởi tạo, hệ thống sẽ có sẵn các tài khoản demo sau để kiểm nghiệm phân quyền (RBAC) với các mật khẩu tương ứng:

| Vai trò | Email | Mật khẩu | Ghi chú |
| --- | --- | --- | --- |
| Super Admin | `admin@gmail.com` | `admin123` | Quyền tối cao, quản trị toàn hệ thống |
| Quản lý (Manager) | `manager@gmail.com` | `manager123` | Thuộc khách sạn "An Nhiên Đà Lạt Hotel", quản lý cấu hình khách sạn |
| Lễ tân (Receptionist) | `receptionist@gmail.com` | `receptionist123` | Thuộc khách sạn "An Nhiên Đà Lạt Hotel", quản lý check-in/out, sơ đồ phòng |
| Kế toán (Accountant) | `accountant@gmail.com` | `accountant123` | Thuộc khách sạn "An Nhiên Đà Lạt Hotel", quản lý hóa đơn, doanh thu |
| Khách hàng (Customer) | `customer@gmail.com` | `customer123` | Khách hàng thông thường đặt phòng online |

## Chức năng chính

- Tìm kiếm theo ngày, khách, hạng phòng, hoàn hủy, số sao và giá; tồn phòng được tính theo booking trùng ngày.
- Quote và booking transactional, khóa tồn phòng, idempotency, dịch vụ bổ sung và voucher.
- Booking online giữ phòng 15 phút; scheduler tự hết hạn và giải phóng tồn phòng/voucher nếu chưa thanh toán mock.
- Checkout 12:00, trừ hao trả trễ 30 phút và dọn phòng 150 phút; phòng kế tiếp sẵn sàng mặc định lúc 15:00.
- Chính sách hủy được snapshot khi đặt: miễn phí trước hạn, tính phí trễ hoặc không hoàn hủy, kèm refund mô phỏng.
- Thanh toán giả lập Card, PayPal, VietQR; lễ tân có thể ghi nhận tiền mặt tại quầy.
- Sanctum Bearer auth, OTP đặt lại mật khẩu demo, OAuth tùy chọn, cập nhật hồ sơ, RBAC và giới hạn dữ liệu theo khách sạn.
- Admin CRUD, upload ảnh, booking tại quầy, check-in/out, dọn phòng, hóa đơn, review moderation và analytics.
- Tìm kiếm giọng nói `vi-VN` mức demo và tracking hành vi không lưu audio/PII thô.
- Chat khách/nhân viên lưu lịch sử trong MongoDB; Socket.io nhận domain events từ transactional outbox qua Redis và có polling fallback.

## Lệnh thường dùng

Xem trạng thái và log:

```bash
docker compose ps
docker compose logs -f mongodb mongo-init redis backend frontend realtime outbox-publisher queue-worker scheduler
```

Chạy test backend và kiểm tra build frontend khi hệ thống đang chạy:

```bash
docker compose exec backend php artisan test
docker compose exec backend vendor/bin/pint --test
docker compose exec frontend npm run smoke
docker compose exec frontend npm run build
docker compose exec realtime npm test
```

Dừng dịch vụ nhưng giữ dữ liệu:

```bash
docker compose down
```

### Reset MongoDB

Reset sẽ xóa toàn bộ dữ liệu và chỉ nên dùng khi chủ động cần làm sạch môi trường development. Không dùng lệnh này như cách khởi động thông thường:

```bash
docker compose exec mongodb mongosh --quiet --eval "db.getSiblingDB('datphong').dropDatabase()"
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed --force
```

Nếu đã đổi `MONGODB_DATABASE`, thay `datphong` trong lệnh trên bằng tên database tương ứng. Muốn xóa cả dữ liệu và ảnh upload sau khi dừng hệ thống, xóa riêng hai volume `datphong_v2_mongodb` và `datphong_v2_public_storage`; lần khởi động sau, `mongo-init` sẽ tạo lại replica set `rs0`. Hãy kiểm tra tên volume và sao lưu dữ liệu cần thiết trước khi thực hiện.

## Cấu trúc

```text
DatPhong/
|-- backend/                 Laravel 12 REST API
|   |-- app/                 Models, services, controllers
|   |-- database/            MongoDB index migrations, factories, seeders
|   |-- routes/              Định tuyến web/API
|   |-- storage/app/public/  File storage local cho ảnh upload
|   `-- docker/              Entrypoint của backend
|-- frontend/                Vue 3 + Vite
|   |-- public/              Tài nguyên tĩnh
|   `-- src/                 Giao diện ứng dụng
|-- realtime/                Node.js + Socket.io Redis subscriber
|-- docker-compose.yml       MongoDB rs0, Redis, backend, scheduler, realtime, frontend
|-- .env.example             Cấu hình Docker mẫu
`-- run.bat                  Khởi chạy nhanh trên Windows
```

## Trạng thái tích hợp

Card, PayPal và VietQR hiện là sandbox giả lập, không kết nối payment provider và không xử lý tiền thật. Backend không nhận hoặc lưu CVC/số thẻ đầy đủ. Các client secret và webhook không được cấu hình trong repository cho tới khi provider thật được triển khai và kiểm thử.
