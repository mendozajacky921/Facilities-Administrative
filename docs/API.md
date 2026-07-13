# Routing / "API" Conventions

This project has no framework and (so far) no JSON API — pages are
server-rendered PHP through a single front controller.

## Front controller
All module pages are reached through `index.php?page={key}`.
Valid keys, their entry files, and their nav labels are all defined in
one place — `app/config/routes.php` (see Medium fix note below):

| `?page=` | Renders |
|---|---|
| `dashboard` (default) | `modules/dashboard/index.php` |
| `reservation` | `modules/reservation/index.php` |
| `visitor` | `modules/visitor/index.php` |
| `documents` | `modules/documents/index.php` |
| `retention` | `modules/retention/index.php` |
| `legal` | `modules/legal/index.php` |
| `contracts` | `modules/contracts/index.php` |

Anything not in that whitelist gets a 404, rendered by `index.php`
itself (and it now checks the target file actually exists with
`is_file()` before requiring it — see Low fix note below).

`dashboard.php` at the project root is a thin redirect to
`index.php?page=dashboard`, kept as a conventional "post-login landing"
URL the auth team's login flow can point to.

## Adding a new route
1. Add the module folder under `modules/` with its own `index.php`.
2. Add one entry to `app/config/routes.php`:
   `'key' => ['file' => 'modules/.../index.php', 'label' => 'Nav Label']`.
   That's it — `T8_PAGES` (`app/config/constants.php`) and the sidebar
   nav (`templates/sidebar.php`) both derive from this file now, so
   there's nothing else to keep in sync (see Medium fix note below).
3. If the page needs a role restriction, call `t8_require_role([...])`
   at the top of the module's `index.php` (see `app/includes/permissions.php`).

## Fix notes (code review)
- **Medium**: `routes.php`, `constants.php`'s `T8_PAGES`, and
  `sidebar.php`'s `$navItems` used to be three hand-maintained lists of
  the same 7 pages that could silently drift. `routes.php` is now the
  single source of truth (file + label per key); the other two derive
  from it.
- **Low**: `index.php` used to `require` the resolved module file
  without checking it existed, so a typo in `routes.php` (or a
  moved/renamed module file) became an uncaught fatal error instead of
  a normal 404.

## Future: JSON endpoints
If a module ends up needing AJAX (e.g. a calendar widget fetching
reservations), the convention will be a sibling `modules/{name}/ajax.php`
that sets `header('Content-Type: application/json')` and returns early —
no separate `/api` tree unless the project outgrows this. Not needed as
of Milestone 0.
