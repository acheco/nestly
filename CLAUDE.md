# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Nestly is a personal and family finance app: a Laravel 13 + Inertia + Vue 3 (TypeScript) monolith on PostgreSQL 18. There is no separate REST API. The domain core is complete; the UI is being built screen by screen.

## Commands

```bash
composer setup                 # install, .env, key, migrate, npm install, build
composer dev                   # run the local dev stack (php artisan dev)
php artisan db:seed            # local only: demo family team, seeded through the real actions
                               # (ana|luis|mariana@nestly.test / password)

composer test                  # Pint check + PHPStan + Pest
composer ci:check              # everything CI runs: frontend checks + composer test
composer lint                  # format PHP with Pint
composer types:check           # PHPStan (level 7)
npm run check                  # frontend lint + format check (vite-plus); check:fix to apply
npm run types:check            # vue-tsc

php artisan test tests/Feature/Transactions/CreateTransferActionTest.php   # one file
php artisan test --filter="part of the test name"                          # one test

php artisan nestly:reconcile [--fix] [--team=<slug>]   # report balance drift; --fix rewrites balances
```

Tests need a real PostgreSQL database named `nestly_testing` (see `phpunit.xml`). SQLite does not work: the schema uses exclusion constraints (`btree_gist`), `BOOL_AND` and generated range columns.

There are no frontend tests.

## Sources of truth

`specs/foundation/decisions.md` records business and technical decisions (D-xxx). **It and the code override `docs/` (PRD, TRD, ERD)** when they disagree (D-071). Check it before relying on the docs, which are long and partly stale. Stale sections get a note pointing to the decision; they are not rewritten.

`specs/constitution.md` summarizes the principles, non-negotiable rules, role permission matrix and quality criteria. It ranks below `decisions.md` and the code, and above `docs/`. This file is the operational guide and creates no business rules.

Some decisions describe the target and are not implemented yet (for example D-018, D-060). Check the code before assuming one is in place. Don't install TRD-listed dependencies (Pinia, zod, charts, PWA) unless a spec justifies them (D-072).

## Workflow

- **Features:** spec → plan → tasks → implementation, one task at a time, in `specs/NNN-name/`. Don't move to the next phase without my approval.
- **Small fixes:** go direct, no spec.
- If docs and code disagree and `decisions.md` doesn't resolve it, **stop and ask me**. Don't pick a side.
- Don't implement a business rule that isn't in `decisions.md` or an approved spec. Never edit `decisions.md` on your own: propose new decisions in its format (ID, context, decision, consequences) and wait for approval.
- Tests first. `composer ci:check` must pass before every commit that touches code. Docs-only commits skip it but must pass `npm run check`, which also formats Markdown (D-087).
- One commit per task, referencing the spec or decision: `feat(002): ...`, `fix(D-014): ...`.
- After each task, summarize what changed and why.
- Work not ready yet is marked with `->todo()` or `->skip('reason')` in Pest. Never leave CI red.
- Code, comments, and commits in English (D-085).

## Architecture

### Request flow

Controllers only orchestrate: Form Request validates → `Gate::authorize` checks the role → the request is mapped to a readonly DTO (`app/DTOs`) → an action (`app/Actions`) applies the domain rules. Seeders, jobs, and tests use the same actions, so domain rules go in the action, not the controller.

Keep the layers separate. Policies answer role and ownership questions only. Business rules such as "no private accounts in a personal team" or "only delete an account with no movements" are 422s raised in the action (D-014); the controller exposes the result to the UI through `can` flags.

Budget, Category, and RecurringExpense have policies, Form Requests and controller files, but no routes yet.

### Multi-tenancy

Team routes live under `/{current_team}` (bound by `slug`) behind `EnsureTeamMembership`, and `SetTeamUrlDefaults` fills the parameter in generated URLs. Every domain row has `team_id`.

Resolve every id from a request through the team: `$team->accounts()->findOrFail()`, or `->scopeBindings()` on the route. A resource from another team must be a **404, not a 403**, so its existence isn't confirmed.

### Money and balances

These rules are D-078. Each account has its own currency (D-084, implemented in spec `002-accounts`).

- Amounts are `decimal(14,2)`, handled as numeric strings with BCMath at `Money::SCALE`. Never use floats.
- `accounts.current_balance` is denormalized. Change it only through `Account::applyTransaction()` inside an action; reverting is applying `$type->opposite()`.
- Every write locks the affected accounts with `lockForUpdate()` inside `DB::transaction`. When locking several accounts, use one query ordered by `id` so crossed transfers can't deadlock. The test suite doesn't cover this: removing the ordering keeps it green and only fails under load.
- A transfer is two `transactions` rows, an `expense` on the source and an `income` on the destination, sharing a `transfer_group_id`. Change them only through the transfer actions; the single-transaction actions reject rows that belong to a transfer. Shared rules are in `app/Actions/Transactions/Support`.
- `BalanceReconciler` recomputes balances in SQL, deliberately independent of the actions' code, so don't reuse write-path logic in it. The weekly job only reports; fixing is the explicit `--fix` step (D-030).

### Private accounts

Private accounts are the main source of edge cases (D-011 to D-017, D-021, D-022):

- Their name is visible to the team, but their balance only to their owner, not even the team owner. This is why `AccountPolicy` splits `view` from `viewBalance`, and `AccountResource` nulls the balances.
- They only take transfers: team → private or private → team, never private → private.
- They can't exist in personal teams (`is_personal`).
- They are archived when their owner leaves the team or is removed (`RemoveTeamMemberAction`).

### Integrity in two layers

Form Requests validate input, and PostgreSQL enforces invariants with `CHECK` and exclusion constraints added through raw `DB::statement` in migrations. `bootstrap/app.php` maps constraint violations users can trigger to 422 field errors through `app/Support/ConstraintViolations.php`. A new user-triggerable constraint needs an entry in its `MAP`. Matching is by constraint name, because PostgreSQL localizes its messages.

### Frontend

- Pages are in `resources/js/pages`, and `Inertia::render('accounts/Index')` maps to `pages/accounts/Index.vue`. Shared props are in `HandleInertiaRequests`.
- `resources/js/actions`, `routes` and `wayfinder` are generated by Wayfinder and gitignored. Import typed routes from them instead of hardcoding URLs. The Vite plugin regenerates them, or run `php artisan wayfinder:generate`.
- `resources/js/components/ui` is shadcn-vue vendor code, excluded from lint and format.

## Testing conventions

- Everything is a Pest feature test with `RefreshDatabase`.
- `tests/Pest.php` has global helpers: DTO builders (`transactionData`, `transferData`, …), `teamUser`, `makeCategory`, `recordExpense`, `recordTransfer`, `transferLegs`, `driftBalance`, `countQueries`. `tests/Datasets/Roles.php` has the shared role datasets.
- Create movements through the real actions (`recordExpense`, `recordTransfer`) so balances stay coherent. Use `driftBalance` only to corrupt a balance on purpose.
- Error paths must assert that balances and rows were left untouched.
- Team-role lookups are memoized. Load `teamMemberships` before authorization in endpoints that check roles repeatedly, as `AccountController::index` does; `TeamRoleMemoizationTest` covers the memoization.
- Tests that guard security or money invariants (cross-team access, balances, locks) need a mutation check: temporarily break the guarded code, confirm the test fails, revert, and show an empty diff.
- Cross-team access tests must use the most privileged role (owner or admin) as the actor, so a role check can't hide a missing team filter.

## Conventions

- User-facing strings are currently hardcoded in Spanish and marked `TODO(i18n)`.
- Dates are `CarbonImmutable` globally. Transactions and transfers can't be dated in the future, where "today" is in the recording user's timezone (D-020).
