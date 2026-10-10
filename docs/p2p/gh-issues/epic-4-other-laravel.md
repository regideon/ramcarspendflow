# Epic 4 — Other Laravel Components

**Labels:** `P2P` · **Phase:** 4 · **Depends on:** Epic 2

Policies, traits, support classes, routes/controllers
([../05-services.md §5.6](../05-services.md#56-support-classes--traits)).

| ID | Title | Labels | Depends on |
|---|---|---|---|
| P2P-4.1 | Policies | P2P | 1.7 |
| P2P-4.2 | Traits | P2P | 1.7 |
| P2P-4.3 | Support classes | P2P | 1.7 |
| P2P-4.4 | Attachment controller + routes | P2P | 3.7 |
| P2P-4.5 | Activity log integration | P2P, RFP | 1.7 |

---

### P2P-4.1 — Policies
- **Scope:** `P2pInvoicePolicy`, `P2pGoodReceiptPolicy` — vendor scoping, AP/Procurement/WHS access; enforce server-side.
- **Acceptance:** unauthorized actions blocked in tests (not just UI).

### P2P-4.2 — Traits
- **Scope:** `ScopesToVendor`, `ScopesToMaterialType`, `HasGoodsReceiptRedirect`.
- **Acceptance:** used by list queries; vendor scoping tested.

### P2P-4.3 — Support classes
- **Scope:** `ActiveCompany`, `WarehouseClassification`, `ThreeWayMatchResult`, `MaterialTypeFilter`, `GoodReceiptActionLabel`.
- **Acceptance:** unit-tested.

### P2P-4.4 — Attachment controller + routes
- **Scope:** serve invoice PDFs from a **private** disk via an authenticated, authorized controller; no public disk.
- **Acceptance:** unauthenticated/unauthorized access denied; no debug routes ported.

### P2P-4.5 — Activity log integration
- **Labels:** `P2P`, `RFP`
- **Scope:** `spatie/laravel-activitylog` on P2P models alongside module histories.
- **Acceptance:** model changes logged; secrets never logged.
