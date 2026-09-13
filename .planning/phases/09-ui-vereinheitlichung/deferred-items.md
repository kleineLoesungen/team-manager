
## 09-06: ticker_detail.php JS confirm on delete

**File:** src/templates/member/ticker_detail.php
**Line:** ~147
**Issue:** `onclick="return confirm('Nachricht löschen?')"` violates UI-BASELINE "kein JS-Confirm"
**Why deferred:** Proper fix requires new confirmation page (route + handler + template) — architectural change
**Scope:** Member ticker message delete
**Discovered:** 2026-09-13 during 09-06 migration
