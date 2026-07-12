# Routing / "API" Conventions

This project has no framework and (so far) no JSON API — pages are
server-rendered PHP through a single front controller.

## Front controller
All module pages are reached through `index.php?page={key}`.
Valid keys are whitelisted in `app/config/routes.php`:

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
itself.

`dashboard.php` at the project root is a thin redirect to
`index.php?page=dashboard`, kept as a conventional "post-login landing"
URL the auth team's login flow can point to.

## Adding a new route
1. Add the module folder under `modules/` with its own `index.php`.
2. Add the `key => 'modules/.../index.php'` entry to `app/config/routes.php`.
3. Add the nav link in `templates/sidebar.php`.
4. If the page needs a role restriction, call `t8_require_role([...])`
   at the top of the module's `index.php` (see `app/includes/permissions.php`).

## Future: JSON endpoints
If a module ends up needing AJAX (e.g. a calendar widget fetching
reservations), the convention will be a sibling `modules/{name}/ajax.php`
that sets `header('Content-Type: application/json')` and returns early —
no separate `/api` tree unless the project outgrows this. Not needed as
of Milestone 0.
