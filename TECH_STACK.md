# Ramcar Spend Flow — Tech Stack

> Roadmap, flows, roles and conventions are documented in [docs/](docs/README.md).

Ramcar Spend Flow is one Laravel app that **replaces two legacy systems**:

- **P2P**: Procure-to-Pay. Vendors (external users) submit invoices against POs and GRs from SAP.
- **RFP Portal**: Request for Payment. Internal employees submit payment requests.

Both legacy codebases (`../p2p` and `../rfp-portal`) are **read-only references**. Nothing gets copied into this app as-is. Each module is redeveloped here on the stack below.

---

## 1. Core stack (installed)

| Layer | Package | Installed version | Notes |
|---|---|---|---|
| Language | PHP | 8.5.x runtime (composer `^8.3`) | Served by Laravel Herd |
| Framework | `laravel/framework` | **13.34** | |
| Admin / UI | `filament/filament` | **5.9** | Single panel `hub` at `/hub` (`HubPanelProvider`), with database notifications |
| Reactive UI | `livewire/livewire` | **4.4** | Comes with Filament 5; use it for custom pages (for example, the side-by-side invoice viewer) |
| CSS | `tailwindcss` + `@tailwindcss/vite` | **4.x** | CSS-first config (no `tailwind.config.js`) |
| Bundler | `vite` + `laravel-vite-plugin` | **8.x** / **3.x** | |
| Database | MySQL | 9.x (local) | `DB_CONNECTION=mysql` |
| Queue / cache / session | Database drivers | — | `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database` |
| Node | Node.js | 26.x | Used only for builds |

## 2. Supporting packages (installed)

| Package | Version | Purpose in Spend Flow |
|---|---|---|
| `spatie/laravel-permission` | 8.3 | Roles and permissions, **shared across P2P and RFP** (one `users` and `roles` set) |
| `spatie/laravel-activitylog` | 5.1 | Audit trail for model changes (sits alongside the module-specific approval histories) |
| `stechstudio/filament-impersonate` | 5.6 | Admin "log in as" for support and UAT |
| `laravel/pulse` | 1.8 | Runtime monitoring for slow queries, jobs and requests |
| `hammadzafar05/mobile-bottom-nav` | 1.4 | Mobile navigation for the Filament panel |

## 3. Dev tooling (installed)

| Package | Version | Purpose |
|---|---|---|
| `pestphp/pest` + `pest-plugin-laravel` | 5.2 / 5.0 | **Test framework** (the reference apps use PHPUnit; all new tests here are Pest) |
| `laravel/boost` | 2.10 | AI agent guidelines and the MCP server (`AGENTS.md`, `CLAUDE.md`, `boost.json`) |
| `laravel/pint` | 1.32 | Code style. Run `vendor/bin/pint --dirty` before committing |
| `laravel/pail` | 1.2 | Log tailing |
| `laravel/pao` | 1.1 | |
| `barryvdh/laravel-debugbar` | 4.4 | Local debugging |
| `beyondcode/laravel-query-detector` | 2.3 | Catches N+1 queries |
| `mockery/mockery`, `fakerphp/faker`, `nunomaduro/collision` | — | Testing support |

## 4. Packages expected later (add per module, after approval)

None of these are installed yet. Each one is added only when its module needs it.

| Need | Candidate | Used by legacy | Module |
|---|---|---|---|
| PDF generation (RFP form, escalation PDFs) | `barryvdh/laravel-dompdf` | RFP, P2P | RFP, P2P |
| Attachments (invoices, supporting docs) | `spatie/laravel-medialibrary` + `filament/spatie-laravel-media-library-plugin` | RFP | Both |
| Excel import/export | `maatwebsite/excel`, or Filament's built-in Importer/Exporter (preferred) | RFP | Both |
| File storage | `league/flysystem-aws-s3-v3` / `league/flysystem-sftp-v3` | P2P / RFP | Both |
| SAP integration | Laravel `Http` client (OData read). The write side is moving to an **AL11 file interface** (see P2P plan 261001) | P2P | P2P |

## 5. Reference projects (do not edit)

| | P2P (`../p2p`) | RFP Portal (`../rfp-portal`) | **Spend Flow** |
|---|---|---|---|
| Laravel | 12 | 12 | **13** |
| Filament | 4 | 3 | **5** |
| Livewire | 3 (with Filament 4) | 3 | **4** |
| Tailwind | 4 | 3 (+ Bootstrap/Sass) | **4** |
| Tests | PHPUnit | PHPUnit | **Pest** |
| Users | External vendors + AP staff | Internal employees + approvers + Accounting | **Both, in one user base** |

**Porting rule:** treat the reference code as a **specification of behavior**, especially the approval flows and status transitions. Do not use it as code to copy. The Filament 3 and 4 APIs (forms, tables, actions, schemas) differ from Filament 5, and Livewire 3 differs from Livewire 4. Always check against the installed version.

## 6. Architecture conventions

- **One app, one panel (`hub`), two domains.** Code is grouped by module:
  - `App\Models\Rfp\*`, `App\Models\P2p\*`
  - `App\Filament\Resources\Rfp\*`, `App\Filament\Resources\P2p\*`
  - Services and enums are grouped the same way. Shared code (users, roles, approval engine, notifications) lives at the top level.
- **Shared tables:** `users`, `roles`/`permissions`, `notifications`, `activity_log`, `jobs`, `cache`, and any organization master data both systems need (for example, `companies`).
- **Tables that are not shared:** each module keeps its own transactional tables (for example, `rfp_*` and `p2p_*` tables). P2P serves external vendors and RFP serves internal employees, so their data is never merged.
- **Approval process is strict.** Status flows must match the approved flow documents exactly. Table design is free to improve on the legacy schemas (normalization, enums for statuses, a shared approval-history pattern).
- **Separate approval screens per system.** Accounting users who act in both systems (AP Processor, AP Supervisor, AP Manager, Treasury) get one RFP approvals resource and one P2P approvals resource. Each shows a navigation count badge of items waiting for them.
- **P2P UX benchmark:** [Medius](https://medius.com/). The vendor's uploaded invoice appears **side by side** with the PO and GR data.
- **Slow work goes on the queue.** SAP calls, emails and PDF generation run as queued jobs or queued mail, never synchronously in a request. The legacy P2P sends mail and SOAP calls synchronously; do not carry that over.

## 7. Delivery approach

The work is delivered **module by module**, starting with **RFP**, then P2P. Each module ships with its migrations, Filament resources, approval flow, and Pest feature tests that cover every status transition.
