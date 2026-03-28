Implement this feature using Filament-native patterns only.

Constraints:
- Do not use standalone custom Livewire pages.
- Do not create custom Blade admin layouts.
- Use existing Filament Resource/Page/Form/Table/Infolist/Action patterns.
- If the feature belongs to an entity, place it inside that entity’s Resource.
- Reuse Filament Sections, Grid, Tabs, Actions, Table filters, Header actions, Relation managers where possible.
- Before coding, briefly state which Filament primitives you will use.
- If you think custom Livewire is needed, stop and explain why Filament-native options are insufficient.

## Admin / Publisher Sync — Shared Abstract (Base) Rule

This project has two Filament panels: **admin** (`App\Filament\Resources\`) and **publisher** (`App\Filament\Publisher\Resources\`).

**Rule: every Resource and Page that exists in both panels MUST share logic via a Base abstract class.**

### Folder conventions
| Layer | Location |
|---|---|
| Base Resources | `app/Filament/Shared/Resources/Base{Entity}Resource.php` |
| Base Pages | `app/Filament/Shared/Pages/Base{Action}{Entity}.php` (e.g. `BaseViewNetwork`) |
| Admin concrete | `app/Filament/Resources/{Entity}Resource/Pages/{Action}{Entity}.php` |
| Publisher concrete | `app/Filament/Publisher/Resources/{Entity}Resource/Pages/{Action}{Entity}.php` |

### When creating a new Resource feature (form, table, infolist, view page, etc.)

1. **Check if a Base class already exists** in `app/Filament/Shared/`. If yes, put shared logic there.
2. **If no Base class exists**, create one:
   - For Resources → extend `Resource`, name it `Base{Entity}Resource`.
   - For Pages (List/Create/Edit/View) → extend the Filament page class, name it `Base{Action}{Entity}`.
3. **Both admin and publisher concrete classes MUST extend the Base class.** Neither panel class should duplicate form schemas, table columns, filters, or view page rendering logic.
4. **Hook methods for panel-specific differences** — use `protected` hook methods with sensible admin defaults, then override only in the publisher class:
   - Route/URL hooks: `screenViewUrl(Screen $screen): string`, `siteViewUrl(Site $site): string`
   - Permission hooks: `canViewAny()`, etc. (admin returns `true`, publisher calls `TenantPermission::check(...)`)
   - Scope hooks: `getEloquentQuery()` (admin returns unscoped, publisher filters by `owner_id`)
5. **RelationManagers** — if admin and publisher RelationManagers are identical except for action URLs, extract shared column/filter definitions into a `protected static function baseColumns(): array` and `protected static function baseFilters(): array` on a shared trait or base RelationManager; concrete classes override only the action URLs.

### Checklist before finishing any Filament feature

- [ ] Is the form schema defined in the Base Resource (not duplicated in each panel)?
- [ ] Is the table schema (columns + filters) defined in the Base Resource?
- [ ] Is the View/List/Create/Edit page logic in a Base Page class?
- [ ] Do both admin and publisher concrete classes extend the Base?
- [ ] Are panel-specific differences (routes, permissions, scoping) isolated to hook methods?
- [ ] Are RelationManagers using publisher-specific routes in the publisher panel?
- [ ] Has `getRelations()` (not `getRelationManagers()`) been used on the Resource class?

### Example pattern (ViewPage)

```php
// app/Filament/Shared/Pages/BaseViewNetwork.php
abstract class BaseViewNetwork extends ViewRecord
{
    // All shared view logic, mount(), getView(), getHeaderActions()

    // Hook — override in publisher to use publisher panel routes
    protected function screenViewUrl(Screen $screen): string
    {
        return \App\Filament\Resources\ScreenResource::getUrl(‘view’, [‘record’ => $screen->id]);
    }
}

// app/Filament/Resources/NetworkResource/Pages/ViewNetwork.php
class ViewNetwork extends BaseViewNetwork
{
    protected static string $resource = NetworkResource::class;
    // nothing else — admin default is correct
}

// app/Filament/Publisher/Resources/NetworkResource/Pages/ViewNetwork.php
class ViewNetwork extends BaseViewNetwork
{
    protected static string $resource = NetworkResource::class;

    protected function screenViewUrl(Screen $screen): string
    {
        return \App\Filament\Publisher\Resources\ScreenResource::getUrl(‘view’, [‘record’ => $screen->id]);
    }
}
```

### Anti-patterns to refuse

- Duplicating `form()`, `table()`, or `infolist()` definitions in both admin and publisher classes.
- Implementing a full View page with `infolist()` in publisher while admin uses a custom Blade template — they must use the same rendering path.
- Adding a RelationManager only to admin `getRelations()` without adding it (or a publisher equivalent) to publisher `getRelations()`.
- Using `getRelationManagers()` on a Resource class (wrong Filament 3 API — always use `getRelations()`).
