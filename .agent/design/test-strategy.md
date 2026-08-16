# Chiến lược test MongoDB

## Chu trình

`RED -> GREEN -> REFACTOR` là bắt buộc cho mọi production behavior.

## Các lớp test

- Unit: money, ngày lưu trú, pricing, voucher, state machine.
- Feature: API, validation, ownership, RBAC và response contract.
- Integration: MongoDB transaction, index, Sanctum token, outbox và Redis.
- Concurrency: nhiều process/session cạnh tranh room-night, voucher và payment.
- E2E: tìm phòng, booking, thanh toán, check-in, check-out và review.

## Yêu cầu môi trường

- Test dùng database có prefix `datphong_test` và tự từ chối xóa database khác.
- MongoDB test phải chạy replica set `rs0` thật.
- Không dùng SQLite, SQL, in-memory database hoặc mocked repository cho persistence test.
- Không bọc toàn test trong transaction nếu cần nhiều process quan sát cùng dữ liệu.

## Test bắt buộc cho booking

- Hai request cùng phòng và đêm: chỉ một thành công.
- Khoảng ngày liền kề: cả hai thành công.
- Transaction lỗi: booking, room-night và outbox đều rollback.
- Cùng idempotency key: chỉ một booking.
- Hủy booking: room-night được giải phóng.
- Booking và maintenance cạnh tranh: chỉ một allocation thành công.

## Cổng CI

- Format, lint và static analysis.
- Toàn bộ unit/feature/integration test.
- MongoDB index migration từ database trống.
- Concurrency suite trên replica set.
- Frontend production build và realtime test.
- Secret scan và dependency scan.
