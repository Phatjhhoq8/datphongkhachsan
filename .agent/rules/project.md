# Quy tắc dự án Đặt Phòng

## Ngôn ngữ

- LUÔN viết tiếng Việt đầy đủ dấu trong trao đổi, tài liệu, giao diện và thông báo.
- Giữ nguyên tên công nghệ, identifier, API, enum và mã lỗi khi dịch sẽ làm sai nghĩa.

## Test trước khi code

- LUÔN viết và chạy test trước khi viết production code.
- Phải xác nhận test RED thất bại đúng lý do, sau đó mới GREEN và REFACTOR.
- Bug fix phải có regression test tái hiện lỗi trước khi sửa.
- Nếu chưa thể chạy test trước, phải dừng production code và nêu rõ blocker.
- Không có ngoại lệ cho booking, inventory, tiền, auth, migration, transaction, payment, webhook và audit.
- Chỉ tài liệu, comment và chính tả không ảnh hưởng hành vi mới không cần test mới.

## MongoDB duy nhất

- Không sử dụng MySQL, SQL Server, PostgreSQL hoặc SQLite trong ứng dụng và test.
- Mọi model persistent phải là MongoDB document model.
- MongoDB phải chạy replica set; không chấp nhận standalone nếu có transaction.
- Dùng ObjectId/string cho ID; không ép ID sang integer.
- Dùng unique/compound/partial/TTL index để bảo vệ invariant phù hợp.
- Không dùng SQL join, foreign key, `lockForUpdate`, `whereRaw`, `whereColumn` hoặc schema inspection kiểu SQL.
- Không giả lập transaction/concurrency bằng mock; phải kiểm tra trên MongoDB thật.

## Booking và tồn phòng

- Khoảng lưu trú là nửa mở `[checkin, checkout)`.
- Mỗi phòng và đêm có đúng một document `room_nights`, bảo vệ bằng unique `(room_id, night)`.
- Tạo booking, claim room-night, voucher, lịch sử và outbox trong cùng transaction.
- Duplicate key room-night phải trả conflict, không được tạo booking một phần.
- Hủy booking phải giải phóng room-night trong cùng transaction.
- Event realtime chỉ phát sau khi dữ liệu đã commit; client phải refetch snapshot từ API.

## Tiền và thanh toán

- Không dùng float cho quyết định tiền; dùng integer VND hoặc Decimal128/string theo currency đã chốt.
- Booking và invoice phải snapshot mọi thành phần giá.
- Mọi create/capture/refund phải idempotent.
- Không đánh dấu thanh toán thành công từ redirect trình duyệt.
- Payment, refund và audit là append-only; không xóa lịch sử tài chính.

## Bảo mật

- Authorization luôn thực hiện ở server và giới hạn theo khách sạn.
- Không commit secret, token, credential hoặc dữ liệu khách hàng thật.
- Không ghi mật khẩu, token, thông tin thanh toán hoặc PII không cần thiết vào log.
- Validate toàn bộ input, rate-limit auth/payment/chat và giới hạn upload.

## Chất lượng

- Controller mỏng; logic transaction và invariant đặt trong service/action có test.
- API mới nằm dưới `/api/v1` và có response/error format nhất quán.
- Chạy lint, static analysis, test và build liên quan trước khi hoàn thành.
- Không skip test, giảm assertion hoặc sửa expected chỉ để test xanh.
