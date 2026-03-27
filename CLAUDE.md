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

# Filament Implementation Rules

## Core rule
This project uses Filament as the admin UI system.
All admin dashboard pages must use Filament-native architecture and UI primitives.

## Mandatory requirements
- Use Filament Resource, Page, Form, Table, Action, Infolist, Widget, RelationManager where appropriate.
- Reuse Filament layout, form schema, table schema, header actions, bulk actions, filters, tabs, sections, grids, fieldsets, placeholders, stats widgets.
- Follow existing Filament folder conventions and naming conventions already present in the repo.
- Prefer:
  - Resource pages for CRUD flows
  - ManageRelatedRecords / RelationManagers for child data
  - Form Actions / Table Actions / Header Actions for operations
  - Infolists for view/detail screens
  - Widgets for dashboard summaries
- When a page belongs to an entity, implement it inside that entity’s Filament Resource if possible.

## Strict prohibitions
- Do NOT generate standalone custom Livewire components for admin CRUD pages.
- Do NOT generate custom Blade admin pages unless there is no Filament-native way.
- Do NOT create a separate Livewire page just to render forms/tables that Filament already supports.
- Do NOT introduce custom Tailwind admin UI when Filament components already solve the problem.
- Do NOT bypass Filament forms/tables with hand-written HTML forms.
- Do NOT use custom page layouts for admin unless explicitly approved.

## Decision policy
Before writing code, evaluate in this order:
1. Can this be solved with existing Resource pages?
2. Can this be solved with a custom page inside a Filament Resource?
3. Can this be solved with Actions, RelationManagers, Widgets, or Infolists?
4. Only if all above fail, propose a minimal custom Filament page.
5. Standalone custom Livewire page is last resort and must be explicitly justified.

## Output format required from Claude
For every admin feature request, respond with:
1. Recommended Filament-native structure
2. Files to create/update
3. Why this should be Resource/Page/Form/Table based
4. Full code changes

## Refactor policy
If existing code uses custom Livewire pages for admin flows, refactor them into:
- Resource pages
- Resource custom pages
- Form schemas
- Table schemas
- Infolists
- Actions
while preserving business logic.

## Review checklist
Before finishing, verify:
- Is this page rendered through Filament?
- Are forms built with Filament Forms?
- Are listings built with Filament Tables?
- Are actions implemented with Filament Actions?
- Does the file live in the correct Filament folder?
- Did we avoid custom Livewire unless absolutely necessary?  