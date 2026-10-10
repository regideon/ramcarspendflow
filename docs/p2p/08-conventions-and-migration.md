# 8. Conventions & Migration

_Last updated: 2026-10-10_

How legacy P2P code is reworked into Ramcar Spend Flow conventions. The reference is a
**specification of behaviour**, never code to copy (Filament 3/4 ≠ 5, Livewire 3 ≠ 4).

## 8.1 Version gap

| | Legacy P2P | Spend Flow |
|---|---|---|
| Laravel | 12 | **13** |
| Filament | 4 | **5** |
| Livewire | 3 | **4** |
| Tailwind | 4 | 4 |
| Tests | PHPUnit | **Pest** |
| Queue lanes | `user-sync`/`daily` | same intent via named queues |

## 8.2 Naming & structure

- Module tables: `p2p_*` prefix (see `02-data-model.md`).
- Module code grouped: `App\Models\P2p\*`, `App\Filament\Resources\P2p\*`, `App\Services\P2p\*`.
- Filament resource folders follow the existing convention (`{Resource}.php`, `Pages/`, `Schemas/`, `Tables/`).
- Shared code (users, roles, notifications, approval engine) stays at the top level.

## 8.3 Rules

- **Migrations: new files only.** Never edit a migration that may have run anywhere.
- **Models:** `#[Fillable]` / `#[Table]`, a `casts()` method, typed relationships with PHPDoc generics.
- **Enums over lookups:** statuses/fixed lists are `app/Enums` backed enums, referenced by key.
- **Roles:** always `RoleName::X`; adding a role = adding an enum case (`RoleAndUserSeeder` creates it).
- **Money:** `decimal` only; compare on the PHP amount.
- **Transactions:** every status change runs in `DB::transaction` with a row lock.
- **Queues:** SAP calls, mail and PDFs are queued — nothing synchronous in a request.
- **Security:** no unauthenticated routes; email actions use signed URLs; uploads on a private disk; secrets only via `config()`.
- **Style:** `vendor/bin/pint --dirty --format agent`.

## 8.4 Porting checklist (per legacy class)

1. Read the legacy class + its tests to capture behaviour.
2. Write the Pest test for the behaviour first (against new tables/enums).
3. Re-implement on Filament 5 / Livewire 4 / Laravel 13 APIs.
4. Replace magic status numbers with the `P2pStatus` enum.
5. Move I/O (SAP, mail) behind a service interface; queue side effects.
6. Run the narrowest tests, then `vendor/bin/pint --dirty`.

## 8.5 What is explicitly not ported

Unsafe/debug routes, `PublicBulkGoodsReceipt`, `TransactionResource`, `point_of_contacts`,
sync email/SOAP, hard deletes, TLS-off HTTP, string-built XML, and the two-table status
drift (`01-scope-and-parity.md §1.1`).

## 8.6 Testing conventions for P2P

- Pest feature tests under `tests/Feature/P2p/<Area>/`; add `pest()->use(RefreshDatabase::class);` **per file** (not global).
- Test **every status transition** and both sides of tax thresholds.
- Filament: `Livewire::test(Page::class)` with `fillForm` / `call('create')` / `assertHasFormErrors`; `Repeater::fake()` for repeaters.
- Access: each screen returns 200 for the allowed role and 403 for others.
- Single test: `php artisan test tests/Feature/P2p/...` or `--filter=`.
