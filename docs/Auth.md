# Auth (Temporary)

Authentication/RBAC is owned by another team and is meant to be
**system-wide** — one login serving all 10 teams' subsystems. That
module hasn't been integrated yet, so Team 8 built a minimal
**temporary** stand-in to unblock development and demoing.

## What exists
- `login.php` — email/password form. Verifies against the shared
  `users` table (`password_hash`, bcrypt via `password_hash()` /
  `password_verify()`), looks up the user's role from
  `user_roles`/`roles`, and sets a real PHP session.
- `logout.php` — destroys the session, clears the cookie, redirects
  to `login.php`.
- `app/includes/auth_check.php` — guards every page behind the front
  controller. If there's no session, redirects to `login.php`.

## Session contract
This is the part that matters for a clean swap later — every module
reads these, nothing reads `$_SESSION` directly outside `auth_check.php`:

| Key | Type | Source |
|---|---|---|
| `user_id` | int | `users.id` |
| `full_name` | string | `users.full_name` |
| `role` | string | `roles.role_name` (first role found for the user) |
| `department_id` | int\|null | `users.department_id` |

## Security notes (temporary ≠ sloppy)
- Passwords are bcrypt-hashed (`password_hash()`), never stored/compared
  in plaintext.
- Login form is CSRF-protected (`t8_csrf_field()` / `t8_csrf_verify()`
  in `app/includes/helpers.php` — reusable for any future POST form).
- Login errors are intentionally vague ("Invalid email or password")
  so the form doesn't leak which emails exist.
- `session_regenerate_id(true)` on successful login to prevent session
  fixation.

## Dev convenience
`AUTH_DEV_BYPASS=true` in `.env` (local env only) skips the login form
and auto-signs in as the seeded "Dev Tester" (admin role). Off by
default — flip it on only for quick throwaway testing.

## Seeded demo accounts (`database/seed.sql`)
All seeded accounts share the password `Password123!`:

| Email | Role |
|---|---|
| `dev.tester@example.local` | admin |
| `facilities@example.local` | facilities_staff |
| `frontdesk@example.local` | front_desk |
| `legal@example.local` | legal_officer |

## Swapping in the real system
When the owning team's auth module is ready:
1. Delete `login.php` and `logout.php`.
2. Point their login flow's redirect at whatever sets the session keys
   listed above (or adjust `auth_check.php` to read theirs directly).
3. Remove the `AUTH_DEV_BYPASS` block and env var.
4. Nothing else changes — every module already reads
   `t8_current_user_id()` / `t8_current_role()` / `t8_current_user_name()`
   from `auth_check.php`, not `$_SESSION` directly.
