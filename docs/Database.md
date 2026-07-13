# Database

## Ownership & conventions
- The database is **shared across all 10 capstone teams**. Team 8 never
  drops or restructures shared core tables (`users`, `roles`,
  `departments`, `user_roles`, `notifications`, `audit_logs`) — they're
  included in `database/schema.sql` only as `CREATE TABLE IF NOT EXISTS`
  placeholders so this repo can run standalone during local development.
  Before final integration, point the foreign keys at the real shared
  tables and drop the placeholder section.

- Every Team 8 table is prefixed `team8_` to avoid collisions with the
  other 9 teams' tables in the same database.

- Standard audit fields on Team 8 tables: `created_at`, `updated_at`,
  and (where meaningful) `deleted_at` for soft deletes.

- **`database/*.sql` is never meant to be served over HTTP.** Fixed
  (Critical, code review): the README instructs dropping this repo
  straight into `htdocs/`, and with no server-level deny rule,
  `database/seed.sql` (bcrypt hashes + emails of every seeded account)
  and `database/schema.sql` were directly downloadable. Both the root
  `.htaccess` and `database/.htaccess` now deny this tree at the
  web-server level. Don't rely on this alone in production — treat
  `.sql` files as secrets regardless.

- **DB credentials live in `app/config/database.php`, not a `.env`
  file.** Same protection applies: the root `.htaccess` and
  `app/.htaccess` deny direct HTTP access to the whole `app/` tree.
  Edit the `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS` constants at the
  top of that file for your local MySQL setup.
  
- **`audit_logs` is now written to**, not just defined in the schema.
  `app/includes/audit.php`'s `t8_audit_log()` currently logs: login,
  logout, and 403 (role-denied) events. More entity types (reservations,
  documents, etc.) should log through the same helper as those modules
  land, so Milestone 2's "Recent Activity" feed has real data to read.

## Modules → tables
| Module | Tables |
|---|---|
| Facilities Reservation | `team8_facilities`, `team8_equipment`, `team8_reservations`, `team8_reservation_equipment`, `team8_reservation_approvals` |
| Visitor Management | `team8_visitors`, `team8_visits` |
| Document Management | `team8_document_categories`, `team8_documents`, `team8_document_versions` |
| Records Retention & Compliance | `team8_retention_schedules`, `team8_records`, `team8_compliance_checks` |
| Legal Management | `team8_legal_cases`, `team8_legal_documents` |
| Contract Management | `team8_contracts`, `team8_parties`, `team8_contract_parties`, `team8_contract_documents`, `team8_contract_obligations` |

## Notable design decisions
- `team8_legal_cases.contract_id` has a circular dependency with
  `team8_contracts` (a case can reference a contract, contracts don't
  reference cases back) — resolved with a deferred `ALTER TABLE ...
  ADD CONSTRAINT` at the end of `schema.sql`, after both tables exist.
- `team8_documents.file_path` always points at the **current** version;
  `team8_document_versions.file_path` stores each historical version's
  own file.
- `team8_visits.status` tracks `expected | checked_in | checked_out |
  denied`.
- `team8_compliance_checks` is a separate audit table from
  `team8_retention_schedules` / `team8_records` — retention is *when*
  something expires, compliance checks are periodic *reviews* of a
  record's state.

## Setup
```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql   # optional local test data
```

Migrations for schema changes after initial setup go in
`database/migrations/` (currently empty — nothing has needed a diff yet).
