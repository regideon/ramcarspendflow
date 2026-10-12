# Epic 5 — Other Components

**Labels:** `P2P` · **Phase:** 5 · **Depends on:** Epics 3–4

Security hardening, Knowledge Base, exports, SAP go-live and demo/UAT. Several items
are **deferred** with the checker/park blocker (B1) or SAP integration.

| ID | Title | Labels | Depends on |
|---|---|---|---|
| P2P-5.1 | Security hardening pass | P2P | 4.4 |
| P2P-5.2 | Knowledge Base articles (P2P) | P2P | 3.x |
| P2P-5.3 | SAP go-live (OData + AL11 write) | P2P | B1, C2 |
| P2P-5.4 | ACDOCA status advancement | P2P | 5.3 |
| P2P-5.5 | Mancom demo runbook | P2P | 1.9, 3.x |
| P2P-5.6 | UAT data + smoke tests | P2P | 5.5 |

---

### P2P-5.1 — Security hardening
- **Scope:** TLS verification, escaped payloads, signed email actions, private uploads, no unauthenticated routes, config-only secrets.
- **Acceptance:** checklist from [../07-sap-integration.md §6](../07-sap-integration.md#6-security-fixes-to-apply-do-not-port-legacy) all satisfied.

### P2P-5.2 — Knowledge Base articles
- **Scope:** add `resources/knowledge-base/p2p-*.md` (vendor submission, escalation reasons, AP review) with live blocks for the reason list.
- **Acceptance:** articles render, ordered, no stale hardcoded values.

### P2P-5.3 — SAP go-live
- **Scope:** real OData reads + AL11 file write interface (plan 261001); swap stub services.
- **Acceptance:** round-trip park → poll status on a UAT client.

### P2P-5.4 — ACDOCA status advancement
- **Scope:** poll BELNR/AUGBL → `14` → `6`; own queue, batched.
- **Acceptance:** status chain tested; no 10-per-run ceiling.

### P2P-5.5 — Mancom demo runbook
- **Scope:** seed + step-by-step script for 2026-10-13 ([../10-demo-data-plan.md](../10-demo-data-plan.md)).
- **Acceptance:** demo runs on a clean database.

### P2P-5.6 — UAT data + smoke tests
- **Scope:** UAT dataset and release smoke checks (mirrors `../p2p/docs/qa/261007-release1-smoke.md`).
- **Acceptance:** smoke checklist passes.
