# Development Guide

_Last updated: 2026-10-06_

## 1. Local setup

| Item | Value |
|---|---|
| URL | Served by Laravel Herd at `https://ramcarspendflow.test/hub` (no `artisan serve`) |
| Database | MySQL `ramcarspendflow` |
| Queue / cache / session | Database drivers |
| Frontend | `npm run dev` (or `composer run dev`) |

```bash
composer install && npm install
php artisan migrate
php artisan db:seed                              # master data + generic demo data + roles/demo users + approval matrix
php artisan db:seed --class=DemoMasterDataSeeder # generic vendors, G/L, cost centers, categories, FX (re-runnable)
php artisan db:seed --class=RoleAndUserSeeder    # reset users & roles only (truncates!)
php artisan test --compact
vendor/bin/pint                                  # code style
```

Demo logins: see [Roles & users](02-roles-and-users.md#4-demo-logins-local--uat-only). The password is `TestPass123!`.

## 2. Code layout

| What | Where |
|---|---|
| Shared models | `app/Models/*` |
| RFP models | `app/Models/Rfp/*` (tables `rfp_*`, mapped with `#[Table]`) |
| P2P models | `app/Models/P2p/*` (tables `p2p_*`) |
| Enums (roles, statuses, types) | `app/Enums/*` |
| Admin-only setup screens | Filament clusters `app/Filament/Clusters/MasterData` and `.../RfpSettings` |
| Users / Roles screens | `app/Filament/Resources/Users`, `.../Roles` (navigation group "Settings") |
| Module screens | `app/Filament/Resources/Rfps/*` (RFP), P2P resources alongside when built |
| Shared Filament helpers | `app/Filament/Concerns/*` (e.g. `RestrictedToAdmins`) |
| Seeders | `database/seeders/*`, RFP ones in `database/seeders/Rfp/*`. Demo data is generic, not SAP (no SAP integration yet) |
| Tests | `tests/Feature/<Area>/*Test.php` (Pest) |

## 3. Conventions

- **Filament 5 / Livewire 4 / Laravel 13 APIs only.** The reference apps use Filament 3/4 and Livewire 3; do not copy their code.
- Create files with `php artisan make:*` (`make:filament-resource`, `make:model --factory`, `make:migration`, `make:test --pest`).
- **Migrations:** only add new migrations. Never edit one that has already run in any environment.
- **Models:** `#[Fillable]` / `#[Table]` attributes, a `casts()` method, typed relationships with generics in PHPDoc.
- **Roles:**
  - Always reference roles through `RoleName::X`, never as string literals.
  - To add a role: add an enum case. `RoleAndUserSeeder` then creates it and a demo user.
- **Approvers:** never hard-code an approval chain. Use the matrix (`ApprovalRule::findFor`).
- **Master data:**
  - No delete buttons; use the Active toggle.
  - Unique validation on SAP codes, scoped per company or type where SAP is.
- **Money:**
  - `decimal` columns only, never floats.
  - Compare approval thresholds on the **PHP** amount.
- **Long-running work goes on the queue:** SAP calls, emails, PDFs. Nothing synchronous in a request.
- **Transactions:** every status change runs in `DB::transaction` with a row lock on the record, to prevent double approval.
- **Security:**
  - No unauthenticated routes.
  - Email approve/reject links must be **signed URLs**, and the approver comes from the signed payload.
  - Uploads go to a **private** disk.
  - Never put secrets in `config/*.php`; read them from `env()` in config only.
- **Compact, Excel-like grids:** for line items use `Repeater::table([...])->compact()->extraAttributes(['class' => 'sf-sheet'])`. The `sf-sheet` styles live in `public/css/app/spreadsheet.css`. Cells match columns **by position**: put conditional fields last and add their `TableColumn`s from a `table(fn (Get $get) => [...])` closure (see the RFP Reimbursement columns).
- **Style:** run `vendor/bin/pint` before committing.

## 4. Knowledge Base articles

The in-app **Help → Knowledge Base** page (`App\Filament\Pages\KnowledgeBase`) is open to every user.

- **Add an article:** create `resources/knowledge-base/{slug}.md`. It appears automatically, ordered by `category` then `order`:
  ```markdown
  ---
  title: Article title
  summary: One line shown under the title
  category: Approvals
  order: 2
  ---
  ## First section
  ...
  ```
- **Write for end users**, in plain language. Each `##` heading becomes an "On this page" link.
- **Live blocks:** a paragraph containing only `{{block:name}}` renders `resources/views/filament/knowledge-base/blocks/{name}.blade.php`. Use blocks for anything that comes from settings or code (e.g. `rfp-approval-matrix` reads the matrix from the database), so articles never go stale.
- **Keep it current:** when a feature ships or changes, update its article as part of the definition of done.

## 5. Testing

- Pest feature tests with `RefreshDatabase` on in-memory SQLite (see `phpunit.xml`).
- **Approval logic:** test every status transition and both sides of every threshold.
- **Filament:** use `Livewire::test(Page::class)` with `fillForm` / `call('create')` / `assertHasFormErrors`. Use `Repeater::fake()` for repeaters.
- **Access:** for each new screen, test that the allowed role gets 200 and other roles get 403.

## 6. Definition of done (per feature)

1. Migrations, models, factories, seeders (if master or setup data).
2. Filament screens with correct access control.
3. Pest tests green: `php artisan test --compact`.
4. Pint clean.
5. Docs updated: roadmap status, data model, decisions/open questions, and the user-facing **Knowledge Base article** if behaviour changed.
