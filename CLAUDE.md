# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A multi-tenant alumni employment-tracking web app for Thai vocational colleges
(ระบบติดตามศิษย์เก่า). Plain PHP + MySQL, no framework, no dependency manager,
no build step. All user-facing strings are Thai.

`README.md` (Thai) is the operator-facing manual — installation, migration
authoring, CSV import format, and the cross-environment compatibility table.

## Running it

There is no build, no linter, no test suite, and no `composer.json` / `package.json`.
Deploy = copy files to the document root. On this machine that is XAMPP, so the app
serves at `http://localhost/vec.alts`.

Everything operational is done through the browser:

| Task | Where |
| --- | --- |
| First install / re-install / reset admin password / seed demo data | `install.php` |
| Apply or roll back migrations | `index.php?r=admin/migrations` (centraladmin only), or install.php's admin tools |
| Inspect the PHP/MySQL versions actually in use | `index.php?r=centraladmin/settings` |

`install.php` is re-entrant: once installed it demands the central admin password
first. If that is impossible (lost password, broken DB credentials), create an empty
`config/install.unlock` to bypass, then delete it.

Syntax-check a file after editing:

```bash
php -l app/Repository.php
```

Debugging: set `'debug' => true` in `config/config.php` to surface errors in the
browser. Otherwise read `storage/logs/app-YYYY-MM.log`, written by `app_log()`.
`config/config.php` is gitignored — `config/config.sample.php` documents its shape.

## The hard constraint: PHP 5.4

The production host is CentOS 7 / PHP 5.4; development is PHP 8 on XAMPP. **One
codebase must parse and run on both.** This is not aspirational — PHP 5.5+ syntax
breaks production at deploy time, after passing locally.

Never introduce: short array syntax `[]`, `??`, `?->`, `::class`, scalar or return
type declarations, namespaces, keyed `list()` destructuring, variadics, or `finally`.
Use `array()`, `isset()` ternaries, and `arr($array, $key, $default)` from
`app/helpers.php`.

Missing standard-library functions are polyfilled in `app/compat.php`
(`password_hash`, `hash_equals`, `array_column`, `random_bytes`, `str_contains`,
`mb_*`). Add to that file rather than degrading a call site.

## Architecture

```
index.php          front controller: route table -> controller method
install.php        standalone installer + admin toolbox (defines VEC_INSTALLER)
app/bootstrap.php  wires $config, $pdo, $auth, $repo, $view
```

`app/bootstrap.php` is the only assembly point; it defines `VEC_ROOT`, `VEC_APP`,
`VEC_VERSION` and either builds the services or redirects to `install.php` when the
config says the app is not installed.

**There is no autoloader.** Core classes are `require`d in `bootstrap.php`;
controllers are `require`d at the top of `index.php`. A new class needs an explicit
require.

### Adding a route

Four edits:

1. Method on a `Controller` subclass in `app/controllers/`.
2. Entry in the `$routes` array in `index.php` — `'exec/years' => array('ExecController', 'years')`.
3. View template under `views/`.
4. If it belongs in the sidebar, an entry in `app_menu()` in `app/helpers.php`.

Routing is query-string based (`index.php?r=exec/years`) because mod_rewrite is not
guaranteed on the production box. `.htaccess` layers pretty URLs on top where it
exists; both forms land in the same place via `PATH_INFO`. **Always build links with
`url($route, $params)`** — never hand-write a path.

### Layers

- **`app/Repository.php`** — every SQL statement in the application lives here, one
  method per query. Controllers must not write SQL. `{p}` inside a query string is
  substituted with the configured table prefix; use `run()` / `one()` / `all()` /
  `scalar()`, all of which prepare and bind.
- **`app/Controller.php`** — base class holding `$auth`, `$repo`, `$view`, `$config`,
  `$route`. Provides `render()` (signed-in shell), `renderPublic()`, `renderBlank()`,
  `csvDownload()` (BOM-prefixed for Excel), and `environment()`.
- **`app/View.php`** — plain PHP templates, no engine and no compile cache (the
  production box has no writable cache dir). `$this` inside a template is the View,
  so `$this->partial('layout/flash')` works.
- **`app/Auth.php`** — session auth for two account kinds: staff (`users` table,
  email + password) and alumni (`alumni` table, student code + national ID, the ID
  stored only as a bcrypt hash). Roles: `alumni`, `advisor`, `exec`, `schooladmin`,
  `centraladmin`.

### Per-request obligations

Every controller method starts with `$this->auth->require_role(...)`. Every POST
handler calls `csrf_verify()` first, and the matching form emits `csrf_field()`.
Every value interpolated into a template goes through `e()`. Consequential actions
call `$this->repo->audit($action, $target, $detail, $this->actor())`.

Multi-tenancy is enforced by scoping queries with `$this->auth->schoolId()` — never
by trusting an id from the request. `schoolId()` returns `null` for the central
admin, who belongs to no institution.

## Database

### Migrations

Files in `migrations/` named `NNNN_description.php`, each returning
`array('name' => ..., 'up' => function (Schema $s) {...}, 'down' => ...)`, ordered by
the numeric prefix. `app/Migrator.php` records each applied migration with a batch
number; rollback reverses the newest batch.

MySQL DDL is not transactional, so each migration is recorded individually and the
runner stops at the first failure, reporting which file it stalled on. Write `up` and
`down` so a re-run is harmless.

`app/Schema.php` is the DDL helper handed to each migration. It applies the table
prefix, engine, charset and collation, and its `addColumn` / `dropColumn` /
`addIndex` / `dropIndex` check existence first (MariaDB has `IF NOT EXISTS` for these;
MySQL does not). Existence checks go through `information_schema`, never
`SHOW ... LIKE ?` — native prepared statements reject placeholders there.

### Schema conventions (portability across MySQL 5.5–8 and MariaDB 10)

- **No `ENUM`.** Statuses are `VARCHAR` validated in PHP — see `employment_statuses()`
  and `staff_roles()` in `app/helpers.php`. Adding a status is a helpers.php edit, not
  a migration.
- **No `TIMESTAMP` / `CURRENT_TIMESTAMP`.** `DATETIME` columns written by the app,
  because MySQL 5.5 allows only one auto-timestamp column per table.
- **Indexed `VARCHAR` stays at or below 191 chars** so utf8mb4 fits InnoDB's old
  767-byte prefix limit.
- **No foreign key constraints.** Referential integrity is the application's job.
- `app/Database.php` detects utf8mb4-vs-utf8, picks a collation that actually exists
  on the server, and pins `sql_mode` to `NO_ENGINE_SUBSTITUTION` on every connect so
  STRICT mode and `ONLY_FULL_GROUP_BY` do not make dev and production disagree.

### Response charset

`vec_send_charset()` (`app/config_io.php`) sends
`Content-Type: text/html; charset=UTF-8` before any output — called from
`app/bootstrap.php` and, separately, from `install.php`, which does not go through
the bootstrap. It must stay ahead of the first `echo`.

Do not drop it and rely on `<meta charset="utf-8">`: the HTTP header wins over the
meta tag, and neither environment declares UTF-8 unprompted. PHP 5.4 still defaults
`default_charset` to ISO-8859-1, and the production host stamps a legacy Thai
charset on responses, which rendered every Thai page as windows-874 mojibake. The
`php_value default_charset` line in `.htaccess` does not help — it is guarded by
`<IfModule mod_php5.c>` and silently does nothing when PHP runs under CGI/FastCGI.

A handler that sets its own `Content-Type` (`csvDownload()`, `vec_fatal()`) must
spell out UTF-8 too.

### Core tables

`schools`, `departments`, `users`, `alumni`, `alumni_status`, `settings`, `audit_log`
(all prefixed, default `va_`). `alumni_status` holds one row per alumnus per
`survey_year`, unique on `(alumni_id, survey_year)` — that is what makes year-on-year
comparison a plain `GROUP BY` rather than a history walk. Drafts are `is_draft = 1`
with a null `submitted_at`.

## Domain conventions

- Years are **Thai Buddhist years** throughout the UI and stored as such.
  `current_academic_year()` rolls over in May; `to_thai_year()` converts. The active
  survey year comes from `$repo->surveyYear()` (the `survey_year` setting, falling
  back to the current academic year).
- Dates render through `thai_date()`.
- Salary reaches reports as `salary_band` (`Repository::salaryBand()`), so aggregate
  screens never expose an individual figure.
- CSV exports carry a UTF-8 BOM so Excel on Windows reads Thai correctly.

## Front end

Hand-written `assets/css/app.css` (CSS custom-property tokens, light/dark via
`html[data-theme]`, theme set before first paint in `views/layout/head.php`) and
`assets/js/app.js`. No bundler, no framework. Both are cache-busted with
`?v=VEC_VERSION`, so bump `VEC_VERSION` in `app/bootstrap.php` when shipping asset
changes.

Colour tokens and layout come from `project/alts.dc.html`, the design canvas this UI
was built from. It is a design artefact, not application code — do not wire it into
the app, but treat it as the reference when adding screens.
