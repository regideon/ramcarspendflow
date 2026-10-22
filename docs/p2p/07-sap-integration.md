# 7. SAP Integration

_Last updated: 2026-10-10 · Sources: `../p2p/docs/03-sap-integration.md`, `../p2p/docs/07-jobs-sync.md`, `../p2p/docs/reference/sap-mapping.md`_

> **SAP is the source of truth.** POs/GRs are read-only in the portal; every submission
> starts by choosing synced GRs. Golden rule: **Read = OData (GET); Write = parked
> invoice only.**

**Implementation status (2026-10-22):** the OData reader, SOAP parking and ACDOCA
poll are ported and sit behind `SAP_ENABLED` (default `false`). With the flag off
the app runs on demo data through the null source/gateway. The write side is
**SOAP** (`ZMM_FM_P2P_INCOMINGINVOICE`, `ACTION=PARK`); **AL11 is not used** and
remains only a future option.

## 1. Connection & config

`config/p2p.php` (env-driven; never `env()` outside config). The `sap` block holds
`SAP_ENABLED`, `SAP_BASE_URL`, `SAP_CLIENT`, `SAP_USERNAME`, `SAP_PASSWORD`, the
invoice endpoint + credentials, `SAP_VERIFY_TLS`, `SAP_TIMEOUT`,
`SAP_LOG_ODATA` and `SAP_ACDOCA_POLL_LIMIT`. `SAP_ENABLED=false` binds the null
source/gateway and makes no network calls.

Transport: `P2pSapClient` (injects `$format=json` + `sap-client`, Basic auth,
normalises to `['success','data','status']`, logs, retries OData GETs with
backoff; SOAP POSTs are never retried).

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

| Item | Implementation |
|---|---|
| RFC | `ZMM_FM_P2P_INCOMINGINVOICE`, `ACTION = PARK` |
| Transport | SOAP over the Laravel HTTP client; XML built with `DOMDocument` (escaped) |
| Response | `INVOICEDOCNUMBER`, `FISCALYEAR`, `MESSAGE`; SOAP faults are surfaced |
| Audit | every call → `p2p_sap_api_logs` (passwords/authorization redacted) |
| Status flow | `4 SubmittedToChecker → 21 PendingPost → 13 InvoiceParked → 14 InvoicePosted → 6 Completed` |

The supervisor's approval moves the invoice to **21 PendingPost** before the SOAP
call. A successful call with an `INVOICEDOCNUMBER` moves it to **13**; a failure
stays at **21** with `sap_park_error` set and a **Retry SAP parking** action.
ACDOCA advances `13 → 14` on `BELNR` and `14 → 6` on `AUGBL`, one step per poll.

**AL11 is not the current path.** The asynchronous AL11 file interface (plan 261001)
remains a future option only; P2P uses SOAP while SAP confirms the transport.

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

**Implemented:** `ODataSapPurchaseOrderSource` does the two-source discovery and
`P2pSapPurchaseOrderReader` fetches MSEG/EKET once per PO (no per-item fan-out).
`P2pPurchaseOrderSyncService` guards in-flow GR states, and
`P2pSapReversalResolver` ports the EKBE `BWART 101/102` reversal rule. `tries` is
still 1 on the sync job (legacy parity); move it to 3 with backoff when the
production SAP endpoint is confirmed. ACDOCA runs every five minutes via
`p2p:poll-sap-invoice-status` (batch 50).

## 5. Withholding-tax resolution chain

`LFBW (vendor, BUKRS, WITHT) → WT_WITHCD → T001 (BUKRS → LAND1) → T059Z (WITHT, WITHCD, LAND1 → QSATZ)`.
Falls back to the statutory table (`02/19/44` = 5%, `04/28/41` = 2%, `09/42` = 1%, `53` = 10%), cached 24h.

## 6. Security fixes to apply (do not port legacy)

| Legacy issue | Fix |
|---|---|
| TLS verification disabled (`verify => false`) | Verify TLS (`SAP_VERIFY_TLS=true` by default) |
| XML built by string interpolation | Use `DOMDocument` + escaped/validated payloads |
| Unauthenticated routes (`/db/fresh`, `/insert-*`, `/purchase-orders/*`, `routes/tax.php`) | None exist; all routes authenticated/signed |
| Hardcoded BCC | Config-driven, no secrets in code |
| `canAccessPanel()` returns `true` for all | Panel access respects active users/roles |
| Attachments on the `public` disk | Private disk |

Full legacy limitation list: `../p2p/docs/06-limitations.md`.
