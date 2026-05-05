---
phase: quick
plan: 260505-rpx
type: execute
wave: 1
depends_on: []
files_modified:
  - src/templates/coordinator/stats.php
autonomous: true
requirements: []
---

<objective>
Preserve scroll position on the coordinator stats page when a filter dropdown triggers a GET form submit.
On submit: save window.scrollY to sessionStorage. On load: restore and clear.
</objective>

<tasks>
<task type="auto">
  <name>Task 1: Add scroll-restore script to coordinator stats template</name>
  <files>src/templates/coordinator/stats.php</files>
  <action>
  Append a small vanilla JS IIFE at end of template:
  - On load: read sessionStorage key, scrollTo it, remove key
  - On DOMContentLoaded: attach submit listener to all form[method="get"] that saves window.scrollY
  </action>
</task>
</tasks>
