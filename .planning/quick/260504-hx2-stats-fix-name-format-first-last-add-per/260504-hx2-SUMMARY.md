# Quick Task 260504-hx2 Summary

**Task:** stats: fix name format (first+last), add percentage to ranking, fix PDO bind param error on filter

**Date:** 2026-05-04  
**Commit:** fb095e9

## What was done

Three changes to the stats feature:

1. **Name format fixed (stats.php lines 74 + 177):** Both the member stats table and ranking table now display names as "Vorname Nachname" instead of "Nachname, Vorname".

2. **PDO bind param error fixed (stats_handler.php):** The old code used `$sum_all_date_sql` with `cells.id IS NULL` baked into the date condition. This caused a CROSS JOIN artifact to appear in the CASE WHEN inline condition. The fix renames the variable to `$sum_all_date_inner`, removes the `cells.id IS NULL` guard (handled directly in the CASE WHEN outer branch instead), and corrects the `$ranking_params` initialization: date params are now merged twice (once for `sum_all`, once for `count_all`) before the two `team_id` params, matching the actual SQL parameter placeholders.

3. **Percentage column added to ranking table:** Each time-window cell now shows the raw value plus a grey percentage in parentheses. For boolean columns: percentage of lists where value was true (val/count_of_lists_with_entry). For number columns: player's share of the column total across all players. New SQL columns `count_all`, `count_4w`, `count_4_8w`, `count_8_12w` count non-NULL cell entries per window. PHP computes `$col_totals` (sum per column per window) after reshaping, and the template uses it for the number percentage. Tiebreaker sort also updated to first_name-first.

## Files modified

- `src/coach/stats_handler.php`
- `src/templates/coach/stats.php`
