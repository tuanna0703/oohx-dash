# Project Rules

## Architecture
- Controller chỉ nhận request và trả response
- Business logic nằm ở Service/Action
- Không query DB trực tiếp trong controller nếu không cần
- Multi-tenant: mọi query phải scope theo tenant

## Security
- Mọi input phải validate
- Không dùng raw SQL nếu không thực sự cần
- Không commit secret
- Upload file phải check mime/type/size
- Permission phải kiểm tra qua policy/gate/service

## Testing
- Mọi thay đổi logic phải kèm test
- Bug fix phải có regression test
- Không kết thúc task nếu test liên quan chưa pass

## Change Rules
- Không đổi API public nếu không có yêu cầu
- Không refactor lan man ngoài phạm vi task
- Khi xong phải output:
  - files changed
  - tests added/updated
  - risks remaining