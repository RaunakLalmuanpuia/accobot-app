---
name: Tally Integration
description: Full Tally ERP integration built into accobot-app — architecture, file map, and key design decisions
type: project
---

Full Tally integration implemented on 2026-04-19.

**Why:** Tenant users need Accobot to sync with their Tally ERP without manual data entry.

**How to apply:** When working on Tally features, refer to docs/tally-integration.md for the full spec.

Key decisions:
- Accobot replicates cloud-tally.in connector API — zero outbound calls to Tally
- Per-tenant inbound_token (48-char) in Authorization header; no Sanctum
- AlterID-based skip logic; Action=Delete → is_active=false
- Ledger → Client/Vendor auto-mapping via deriveCategory()
- StockItem → Product auto-mapping
- Reports are insert-only (snapshot history preserved)
- 12 migrations, 11 models, 5 API controllers, 2 web controllers, 2 Vue pages
- Uses existing `integrations.view` / `integrations.manage` permissions
