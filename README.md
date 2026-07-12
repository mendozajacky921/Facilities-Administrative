# RAM YUM — Facilities & Administrative Management (Team 8)

Facilities & Administrative Management subsystem for the multi-team
capstone project. Covers: Facilities Reservation, Visitor Management,
Document Management/Archiving, Records Retention & Compliance, Legal
Management, and Contract Management — as one unified system with a
single dashboard and entry point (not six separate mini-apps).

**Stack:** HTML, CSS, vanilla JavaScript, PHP 8.2+, MySQL 8+. No
framework — routing is a small hand-rolled front controller
(`index.php?page=...`), see `docs/API.md`.

## Requirements
- PHP 8.2+
- MySQL 8+
- Git

## Setup

1. Clone the repo and `cd` into it.
2. Copy the env file and adjust credentials if needed:
   ```bash
   cp .env.example .env
   ```
3. Create the database and import the schema (Team 8's tables only —
   see `docs/Database.md` for why the shared-table section exists):
   ```bash
   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS capstone_shared_db"
   mysql -u root -p capstone_shared_db < database/schema.sql
   mysql -u root -p capstone_shared_db < database/seed.sql   # optional
   ```
4. Start PHP's built-in dev server from the project root:
   ```bash
   php -S localhost:8000
   ```
5. Visit `http://localhost:8000/index.php?page=dashboard` (or just
   `http://localhost:8000/dashboard.php`, which redirects there).

## Auth note
Login/logout/session/RBAC is owned by another team and is meant to be
system-wide. Until it's integrated, Team 8 built a real **temporary**
login (`login.php` / `logout.php`) against the shared `users` table —
see `docs/Auth.md` for the full contract, seeded demo accounts, and
how to swap it out later. Default seeded login:
`dev.tester@example.local` / `Password123!`.

## Docs
- `docs/Milestones.md` — milestone checklist
- `docs/Database.md` — schema/table ownership notes
- `docs/API.md` — routing conventions
- `docs/Auth.md` — temporary auth system, demo accounts, swap-out plan
- `docs/ERD.pdf` — entity-relationship diagram

## Coding standard
- One responsibility per controller/module file
- Prepared statements via PDO everywhere (no string-concatenated SQL)
- Reusable templates (`templates/header.php`, `sidebar.php`,
  `navbar.php`, `footer.php`)
- CSS classes prefixed `.t8-` to avoid collisions with other teams'
  stylesheets if pages ever get embedded together
- No duplicated logic — shared helpers live in `app/includes/helpers.php`
