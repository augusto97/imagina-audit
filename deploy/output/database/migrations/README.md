# Migrations

Versioned SQL migrations for the Imagina Audit database. Each file is
applied once and tracked in the `schema_migrations` table.

## Filename convention

```
NNNN_short_description.sql        # UP migration (required)
NNNN_short_description.down.sql   # DOWN migration (optional, for rollback)
```

- `NNNN` is a zero-padded integer (recommend 4 digits). Order is strictly
  numeric, not lexicographic.
- `short_description` is `snake_case` and describes the change in a few
  words. Examples: `0001_initial`, `0002_audits_add_is_pinned`.

## Running

```bash
# From repo root
php backend/database/migrate.php status     # list pending vs applied
php backend/database/migrate.php up         # apply all pending
php backend/database/migrate.php rollback   # rollback last applied
php backend/database/migrate.php rollback 3 # rollback last 3
```

## Cross-driver SQL

Migrations run on both SQLite and MySQL. The Migrator preprocesses the
SQL to handle differences:

### Placeholders

| Placeholder        | MySQL                                                      | SQLite                          |
|--------------------|------------------------------------------------------------|---------------------------------|
| `{{NOW}}`          | `CURRENT_TIMESTAMP`                                        | `(datetime('now'))`             |
| `{{AUTO_PK}}`      | `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`               | `INTEGER PRIMARY KEY AUTOINCREMENT` |
| `{{BOOL}}`         | `TINYINT(1)`                                               | `INTEGER`                       |
| `{{JSON}}`         | `JSON`                                                     | `TEXT`                          |
| `{{INT}}`          | `INT`                                                      | `INTEGER`                       |
| `{{BIGINT}}`       | `BIGINT`                                                   | `INTEGER`                       |
| `{{TEXT_LONG}}`    | `MEDIUMTEXT`                                               | `TEXT`                          |
| `{{TABLE_OPTIONS}}`| ` ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` | (empty)              |

### Driver-conditional blocks

For SQL that can't share a syntax, use blocks:

```sql
--{mysql}
CREATE FULLTEXT INDEX idx_search ON articles(title, body);
--{/mysql}

--{sqlite}
-- SQLite uses FTS5 virtual tables instead.
CREATE VIRTUAL TABLE articles_fts USING fts5(title, body);
--{/sqlite}
```

Lines inside a block whose tag doesn't match the active driver are
stripped before execution.

## Example migration

```sql
-- 0042_add_users_table.sql

CREATE TABLE IF NOT EXISTS users (
    id          {{AUTO_PK}},
    email       VARCHAR(255) NOT NULL,
    is_admin    {{BOOL}} NOT NULL DEFAULT 0,
    metadata    {{JSON}},
    created_at  TIMESTAMP NOT NULL DEFAULT {{NOW}}
){{TABLE_OPTIONS}};

CREATE UNIQUE INDEX idx_users_email ON users(email);
```

```sql
-- 0042_add_users_table.down.sql

DROP TABLE IF EXISTS users;
```

## Rules (binding — see /P7_PRODUCTION_DATABASE.md §3)

1. **Once released, never edit a migration.** To fix something, add a new
   migration. Customers may have already applied the old one.
2. **Use `CREATE TABLE IF NOT EXISTS`** so migrations are idempotent
   against installs that legacy-bootstrapped the same table.
3. **No driver-specific SQL outside conditional blocks.** Either use
   placeholders or split with `--{mysql}/--{sqlite}`.
4. **Foreign keys always.** Both drivers run with FK enforcement on.
5. **Indexes belong in the same migration as the columns they cover.**
   Don't leave them for "later" — Phase 6 has a reviewer step.

## Transactions

- SQLite: each migration runs in a transaction. If any statement fails,
  the whole migration is rolled back and the version is NOT marked applied.
- MySQL: DDL auto-commits per statement. If one fails midway, the DB is
  partially migrated and the version is NOT marked applied. Fix the SQL
  and re-run — the migration restarts from scratch.
