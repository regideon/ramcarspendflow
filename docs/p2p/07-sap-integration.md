# 7. SAP Integration

_Last updated: 2026-10-10 · Sources: `../p2p/docs/03-sap-integration.md`, `../p2p/docs/07-jobs-sync.md`, `../p2p/docs/reference/sap-mapping.md`_

> **SAP is the source of truth.** POs/GRs are read-only in the portal; every submission
> starts by choosing synced GRs. Golden rule: **Read = OData (GET); Write = parked
> invoice only.**

**This pass scope (decision #1/#5):** build against **generic demo data**. The SAP
layer below is documented for the build but **not in scope** until the checker/park
blocker clears. Services are stubbed behind interfaces so we can swap in real SAP later.

## 1. Connection & config

`config/p2p.php` (env-driven; never `env()` outside config): `SAP_BASE_URL`,
`SAP_CLIENT`, `SAP_USERNAME`, `SAP_PASSWORD`, invoice endpoint + credentials.
Read service base: `P2pSapBaseService` (injects `$format=json` + `sap-client`, Basic
auth, normalises to `['success','data','status']`, logs).

## 2. Read — OData v2 CDS (GET) to port

| CDS view | SAP table | Used for |
|---|---|---|
| `ZCDS_MM_EKKO` | EKKO | PO header (vendor, company, currency, BEDAT/AEDAT) |
| `ZCDS_MM_EKPO` | EKPO | PO items (qty, text, price) |
| `ZCDS_MM_EKBE` | EKBE | PO/GR history by `CPUDT`; reversal `BWART 102`; down-payment `VGABE` |
| `ZCDS_MM_EKET` | EKET | Delivery schedule (`WEMNG` delivered qty) |
| `ZCDS_MM_MSEG` | MSEG | Material docs = GRs (`SHKZG ne 'H'`), `LGORT` storage location |
| `ZCDS_FI_ACDOCA` | ACDOCA | FI `BELNR` + clearing `AUGBL` |
| `ZCDS_FI_T001` | T001 | Company master + `LAND1` |
| `ZCDS_FI_LFBW` | LFBW | Vendor WHT assignment |
| `ZCDS_FI_T059Z` | T059Z | WHT rate (`QSATZ`) |

## 3. Write — invoice park

| Item | Legacy |
|---|---|
| RFC | `ZMM_FM_P2P_INCOMINGINVOICE`, `ACTION = PARK` |
| Transport | SOAP (raw cURL, XML by string interpolation) |
| Response | `INVOICEDOCNUMBER`, `FISCALYEAR`, `MESSAGE` |
| Audit | every call → `p2p_sap_api_logs` (passwords redacted) |

**Planned (plan 261001):** asynchronous **AL11 file interface** — portal writes a CSV,
pushes over SFTP keyed `{VENDOR-CODE}-{COMPANY-CODE}-{GRC-NUMBER}`; SAP polls; status
advances `21 Pending Post → 13 Parked → 14 Posted → 6 Completed`.

## 4. Sync design (must fix legacy bloat)

Legacy problems: per-vendor fan-out (~7k jobs), per-item MSEG/EKET fan-out (30-min
jobs), `tries=1`, shared worker, no global cap.

Rebuild requirements:

- **Batch** OData reads (one CDS call with joins instead of per-item MSEG/EKET).
- **Cap/reduce fan-out**; skip vendors with no activity; `--all-vendors` single query for base loads.
- **Retry/backoff** and a **circuit breaker**; separate worker/queue for ACDOCA.
- Atomic per-vendor dedup (`Cache::add()`), keyed `p2p-po-sync:pending:{vendor}`.
- Two discovery sources unioned (EKBE history + EKKO header); bail only if both fail.
- Staging → working: mirror rows in `p2p_sap_*`, then refresh derived GR state.

## 5. Withholding-tax resolution chain

`LFBW (vendor, BUKRS, WITHT) → WT_WITHCD → T001 (BUKRS → LAND1) → T059Z (WITHT, WITHCD, LAND1 → QSATZ)`.
Falls back to the statutory table (`02/19/44` = 5%, `04/28/41` = 2%, `09/42` = 1%, `53` = 10%), cached 24h.

## 6. Security fixes to apply (do not port legacy)

| Legacy issue | Fix |
|---|---|
| TLS verification disabled (`verify => false`) | Verify TLS |
| XML built by string interpolation | Use the HTTP client + escaped/validated payloads |
| Unauthenticated routes (`/db/fresh`, `/insert-*`, `/purchase-orders/*`, `routes/tax.php`) | None exist; all routes authenticated/signed |
| Hardcoded BCC | Config-driven, no secrets in code |
| `canAccessPanel()` returns `true` for all | Panel access respects active users/roles |
| Attachments on the `public` disk | Private disk |

Full legacy limitation list: `../p2p/docs/06-limitations.md`.
