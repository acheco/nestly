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
- They are archived when their owner leaves the team or is removed (`RemoveTeamMemberAction`, D-016), or is demoted to viewer (`ChangeTeamMemberRoleAction`, D-017). The owner still sees their balance and history while a member of the team.

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

## Laravel Boost

The Laravel Boost block at the end of this file is generated by `boost:update` (it runs after `composer update`). Don't edit it by hand, and don't write its opening tag anywhere else in this file: Boost replaces everything from the first occurrence. What Boost composes is configured in `config/boost.php` and `boost.json`. Where the block disagrees with this file, the constitution or `decisions.md`, those win. In particular:

- Tests first. Run `composer ci:check` yourself before every commit that touches code; don't ask me to run the suite. It runs `pint --test`; fix style with `composer lint`.
- Testing follows "Testing conventions" above (error paths, mutation checks, cross-team actors), not only "meaningful regression coverage". The block mentions a `testing-best-practices` skill: it is excluded on purpose.
- Inline comments that explain a rule or cite a decision (D-xxx) are intended. Keep them.
- Specs, plans, tasks and proposed decisions are part of the workflow, not unrequested docs.
- There is no REST API: ignore the API Resources and versioning guidance. `app/Http/Resources` only shapes Inertia props.
- Business rules live in `decisions.md`. Don't use `.ai/rules` or `record-rule` (disabled in `config/boost.php`).
- Database: read with `database-schema` and `database-query`. Never write data outside the actions (D-078): the MCP `tinker` tool is disabled, and don't use `php artisan tinker` to write either.
- Demo data only through `DemoSeeder` and the real actions; no per-model seeders.
- The installed skills (Fortify, Inertia Vue, Wayfinder, Tailwind, infer-conventions) are generic. When they disagree with the request flow (Form Request → Gate → DTO → action) or D-014, the project wins.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
