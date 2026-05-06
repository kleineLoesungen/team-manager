---
quick_id: 260506-jhk
description: remove member filter from list detail page and rename back-link to Zurück zur Übersicht
date: 2026-05-06
---

# Quick Task 260506-jhk

## Tasks

### Task 1: Remove member filter from coordinator list detail

**File:** src/templates/coordinator/list_detail.php

- Remove `<details id="member-filter-details" open>` block (member filter UI)
- Remove JS IIFE (updateTable, setAllMembers, change listeners)
- Remove `id="member-list-table"` from table
- Remove `data-member-id` from tbody `<tr>` tags
- Remove `data-col-id` / `data-col-type` from tbody `<td>` and tfoot `<td>` tags
- Rename "Zurück zu Listen" → "Zurück zur Übersicht" on back-link
