# Nestly

Personal and family finance management: shared workspaces where a household
tracks accounts, transactions, transfers, budgets and recurring expenses, with
roles and per-member privacy.

> **Status: work in progress.** The domain core (transactions, transfers and
> balance reconciliation) is complete and tested. The Vue screens come next.
> See [Roadmap](#roadmap).

Nestly is a portfolio project. The focus is on getting the hard parts of a
money app right (correct balances, concurrency, multi-tenancy and privacy)
rather than on feature count.

## Highlights

- **Multi-tenant by team.** Every domain record carries `team_id`, and every
  id coming from a request is resolved through the team's relations
  (`$team->accounts()->findOrFail()`), never globally. A resource from another
  team is a 404, not a 403, so its existence is not confirmed.
- **Roles and privacy.** Owner, admin, member and viewer roles. Members can hold
  *private accounts* inside a family team: their name is visible to everyone,
  their balance only to their owner, they only accept transfers, and only the
  owner can move money out of them.
- **Money without floats.** Amounts are `decimal(14,2)` columns read as strings
  and handled with BCMath at an explicit scale.
- **Real-time balances, safe under concurrency.** Every write goes through an
  action that locks the affected rows (`SELECT … FOR UPDATE`) inside a database
  transaction. Transfers lock both accounts in a single query ordered by `id`,
  so two crossed transfers (A→B and B→A) cannot deadlock.
- **Two-row transfers.** A transfer is an `expense` row on the source and an
  `income` row on the destination, sharing a `transfer_group_id`. Each account's
  ledger stays self-contained, and edits revert the old effect before applying
  the new one.
- **Independent reconciliation.** A weekly job recomputes every balance in SQL,
  independently of the actions' code, so a bug in the write path shows up
  instead of agreeing with itself. It also detects broken transfer pairs. It
  only reports: fixing is an explicit step (`php artisan nestly:reconcile --fix`).
- **Integrity in two layers.** Form Requests validate input; PostgreSQL
  enforces invariants with `CHECK` constraints and an exclusion constraint that
  prevents overlapping budgets.
- **Tested deliberately.** Every error path asserts that balances and rows were
  left untouched, and tests are checked with manual mutation testing (break the
  code on purpose and confirm the right test fails).

## Tech stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3+ (BCMath) |
| Frontend | Inertia.js, Vue 3 (Composition API), TypeScript |
| UI | Tailwind CSS 4, shadcn-vue (Reka UI), Lucide |
| Typed routes | Laravel Wayfinder |
| Database | PostgreSQL 16 |
| Testing | Pest 4 |
| Quality | Laravel Pint, PHPStan (Larastan), vue-tsc |
| CI | GitHub Actions (PHP 8.3 + PostgreSQL 16) |

A Laravel monolith with Inertia: no separate REST API in the MVP. A
Sanctum-authenticated API for a React Native (Expo) client is planned after
the web app is complete.

## Getting started

### Requirements

- PHP 8.3+ with the `bcmath` and `pdo_pgsql` extensions
- Composer 2
- Node.js 22+
- PostgreSQL 16 with the `btree_gist` extension available (it ships with
  PostgreSQL's contrib modules)

SQLite is **not** supported: the schema relies on PostgreSQL features
(exclusion constraints, `BOOL_AND`, generated range columns).

### Setup

```bash
git clone https://github.com/acheco/nestly.git
cd nestly
cp .env.example .env
```

Point `.env` at PostgreSQL:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nestly
DB_USERNAME=postgres
DB_PASSWORD=
```

Then install, migrate and build:

```bash
composer setup
```

### Demo data

```bash
php artisan db:seed
```

In the `local` environment this seeds a demo family team through the same
actions the app uses, so balances are consistent. Users: `ana@nestly.test`,
`luis@nestly.test` and `mariana@nestly.test`, all with password `password`.

### Run

```bash
composer dev
```

## Useful commands

| Command | What it does |
|---|---|
| `composer test` | Pint check, PHPStan and the Pest suite |
| `composer ci:check` | Everything CI runs, frontend checks included |
| `composer lint` | Format PHP with Pint |
| `php artisan nestly:reconcile` | Report balance drifts and broken transfers; exits with 1 if anything is wrong |
| `php artisan nestly:reconcile --fix` | Rewrite drifted balances from the ledger (broken transfers are only reported) |
| `php artisan nestly:reconcile --team=<slug>` | Limit the check to one team |

The test suite runs against a separate PostgreSQL database named
`nestly_testing` (see `phpunit.xml`); create it before running the tests.

## Project structure

```text
app/
  Actions/        Use cases (CreateTransferAction, BalanceReconciler, …)
  DTOs/           Typed, readonly inputs and outputs of the actions
  Enums/          TransactionType, AccountVisibility, TeamRole, …
  Http/           Thin controllers and Form Requests
  Jobs/           Queued and scheduled jobs
  Policies/       Role checks per team
  Support/        Money (BCMath scale and limits)
database/         Migrations (with CHECK and exclusion constraints), seeders
resources/js/     Vue pages, layouts and components (Inertia)
tests/Feature/    Action, HTTP and command tests
```

Controllers only orchestrate: they validate with a Form Request, authorize the
role with a policy and delegate to an action. Actions hold the domain rules, so
the seeder, jobs and the future mobile API go through the same checks.

## Roadmap

| Phase | Scope | Status |
|---|---|---|
| 0 | Project setup, CI, static analysis | Done |
| 1 | Transactions and transfers core, balance reconciliation | Done |
| 2 | Cross-cutting authorization (policies, role matrix) | Next |
| 3 | Accounts: first Vue screens, i18n | Planned |
| 4 | Transactions UI | Planned |
| 5 | Categories | Planned |
| 6 | Budgets | Planned |
| 7 | Recurring expenses | Planned |
| 8 | Dashboard | Planned |
| 9 | MVP wrap-up: audit log, E2E tests, deployment | Planned |

After the MVP: savings pots and MFA, then the mobile API and a React Native
client.

## Author

Built by Anthony ([@acheco](https://github.com/acheco)).
