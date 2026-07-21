# Facility Management + Reservation Workflow — Implementation Notes

All files below are ready to paste directly into `team8-facilities-admin/`
at the matching path, overwriting the current version of each file.

## Revision note (post code-review)
Two fixes went in after a senior-level review pass, before this was handed off:
1. **`app/includes/helpers.php`** — `redirect()` now clears any active output buffer before sending the `Location` header. `index.php` wraps the whole response in `ob_start()`/`ob_end_flush()` so modules can redirect after a POST even though `header.php`/`navbar.php` already echoed HTML — without this fix, every redirect would ship a 302 with a malformed half-rendered HTML body attached (harmless to browsers, which ignore redirect bodies, but incorrect). **This file must be included in the merge** — it wasn't in the first pass.
2. **`modules/reservation/index.php`** — Administrator-created reservations are auto-approved and never pass through Pending Approvals, which was the only place the double-booking conflict check ran. Fixed: the conflict check now also runs at admin-create time; if there's an overlap, the reservation is still created and approved (per the "warn, don't block" rule), but the success flash becomes a warning: *"Reservation created and approved, but it overlaps with another approved reservation for this facility."*

## New files
- `modules/facilities/index.php` — Admin-only Facility Management (list, add, edit, archive, reactivate).
- `app/includes/helpers.php` — shared helpers, unchanged except the `redirect()` fix above.
- `database/migrations/2026_07_17_add_facility_management_fields.sql` — run this if you're updating an existing local database instead of re-importing `schema.sql` from scratch.

## Modified files
- `database/schema.sql` — `team8_facilities` now has `description TEXT NULL` and `status ENUM('active','archived') DEFAULT 'active'`; added `idx_team8_facilities_status`. Nothing else in the file changed.
- `modules/reservation/index.php` — full implementation, replacing the old stub: create/edit/cancel for Facilities Staff (own reservation, while Pending), Approve/Reject + all-reservations view for Admin, single-step approval logging into `team8_reservation_approvals`, a double-booking warning badge in Pending Approvals, and the admin-create conflict warning above.
- `app/config/routes.php` — added the `facilities` route (`roles => ['admin']`) and documented the new optional `roles` key. Every other route is unchanged and still open to any authenticated user.
- `templates/sidebar.php` — hides any nav link whose route declares `roles` the current user doesn't have. Purely additive; existing routes with no `roles` key render exactly as before.
- `index.php` (front controller) — two changes: (1) wrapped the whole response in `ob_start()`/`ob_end_flush()` so a module can `redirect()` after a POST even though `header.php`/`navbar.php` already echoed HTML by the time the module runs — this is what makes the create/edit/approve/archive forms below work at all; (2) enforces a route's `roles` restriction (if any) as a second layer behind the sidebar, using the existing `t8_require_role()`.

## Business rules implemented
- Facilities: add/edit/archive/reactivate entirely through the UI, admin-only, no hard deletes.
- Reservation dropdown only ever shows `status = 'active'` facilities.
- Zero active facilities: Admin sees an "Add Facility" button; Facilities Staff sees an informational message and no form.
- Facilities Staff: create requests (`status = 'pending'`), edit/cancel their own reservation only while still Pending, view their own history.
- Administrator: sees all reservations, Approve/Reject any Pending request, can also create a reservation directly — which is auto-approved, logged as a single decided approval step, and checked for conflicts the same as any other approval.
- Approvals are written to `team8_reservation_approvals` as one row per decision (`step_order = 1`), per your call to stay aligned with the existing schema.
- Pending Approvals view flags (does not block) any request whose facility/time range overlaps an already-approved reservation. Same check now also covers admin-direct creation.

## Open scope questions from the code review (not yet decided)
These didn't block this handoff but are worth a decision before the next round:
1. No calendar view was built — only tabular list views (My Reservations / All Reservations / Pending Approvals). The original module stub mentioned a "reservation calendar" as in-scope.
2. Facilities Staff currently has no visibility into other people's approved bookings when choosing a time — they request blind, and Admin catches conflicts after the fact.
3. I added `capacity >= 1` as a required validation rule on the facility form; wasn't explicitly requested (schema default is `0`).
4. Archiving a facility with active/future approved reservations gives no warning to the Admin.

## Before you deploy
1. Import/alter the database first — either re-run `schema.sql` on a fresh database, or run the new migration against an existing one.
2. Nothing in `public/css/*.css` or `public/js/*.js` changed — all new UI reuses existing `.t8-` classes (badges, cards, tables, buttons, forms, and `.t8-alert-warning` for the new conflict flash) already defined.
3. Quick smoke test once deployed:
   - Log in as `facilities@example.local` (facilities_staff) → confirm no "Facility Management" link in the sidebar, and that hitting `?page=facilities` directly returns a 403 (not a blank/broken page).
   - Log in as `dev.tester@example.local` (admin) → add a facility, confirm it appears in the reservation dropdown, archive it, confirm it disappears from the dropdown but any of its past reservations still display correctly.
   - Submit a reservation as facilities_staff, then approve it as admin, then confirm a row appears in `team8_reservation_approvals`.
   - Create two overlapping requests for the same facility and confirm the second one shows the double-booking warning in Pending Approvals.
   - As admin, directly create a reservation that overlaps an already-approved one and confirm you get the new warning flash instead of a silent success.
   - Curl-check a redirect (e.g. after archiving a facility) and confirm the response body is empty/minimal, not a stray partial HTML page.

## Not touched (intentionally)
- `docs/MEMORY.md` — per your standing rule, this stays untouched until you give the separate go-ahead.
- `docs/Milestones.md`, `docs/Database.md`, `docs/API.md` — I've left these as-is for now; happy to update them to reflect the new module/route/schema once you've had a chance to test, if you'd like.
- Equipment management (`team8_equipment`, `team8_reservation_equipment`) — untouched and unused, per your explicit scope call.

