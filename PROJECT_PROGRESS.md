# OpsFlow AI: Project Progress

Use this file to restore context at the start of a new session.

## Status

| Phase | Scope | State |
| --- | --- | --- |
| 1 | Foundation and core backend | **Complete** (2026-11-02) |
| 2 | AI analysis, Redis cache and queue, audit log, comments | Not started; awaiting approval |
| 3 | Blade dashboard, hardening, final review | Not started |

## Environment

- Project root: `C:\Users\dhruv\opsflow-ai`. The host has no PHP; everything runs through `docker compose` (`app`, `queue`, `mysql`, `redis`).
- Run commands as `docker compose run --rm app <cmd>`. From Git Bash, prefix with `MSYS_NO_PATHCONV=1`.
- Laravel 13.17, PHP 8.4, Sanctum 4, PHPUnit 12, MySQL 8.4, Redis 7.
- Laravel Boost guidelines are installed (`CLAUDE.md`, `AGENTS.md`, `boost.json`). Its MCP server is not configured, because the host has no PHP binary.
- Tests use in-memory SQLite (`phpunit.xml`). The dev database is MySQL in Docker (host port 3307).
- `.env` still uses `QUEUE_CONNECTION=database` and `CACHE_STORE=database`. Phase 2 switches both to Redis.

## Phase 1: what exists

**Domain**

- Enums in `app/Enums`: `Role`, `RequestStatus` (holds the transition graph), `RequestPriority`, `RequestCategory`.
- Models: `User` (role cast, `HasApiTokens`), `OperationalRequest` (`visibleTo` scope), `RequestStatusHistory`, `RequestAssignment`.
- Services: `RequestWorkflowService` (transaction, `lockForUpdate`, history row) and `RequestAssignmentService`.
- Exceptions: `InvalidStatusTransitionException` and `RequestNotAssignableException`. Both render a 409.
- Policy: `OperationalRequestPolicy`, with abilities `view`, `update`, `submit`, `review`, `assign` and `work`.

**HTTP**

- `routes/api.php`: login (`throttle:login`); everything else behind `auth:sanctum` and `throttle:api`.
- Controllers in `app/Http/Controllers/Api`: `Auth`, `OperationalRequest`, `RequestWorkflow`, `RequestAssignment`.
- Six Form Requests and four API Resources.
- `bootstrap/app.php` renders API 404s as `{"message": "Resource not found.", "error": "not_found"}`.
- `AppServiceProvider` sets `Model::shouldBeStrict()` outside production and defines the `api` and `login` rate limiters.

**Data**

- Four migrations: role on users, `operational_requests`, `request_status_histories`, `request_assignments`.
- `OperationalRequestFactory` with status states. `UserFactory` gains `manager()` and `admin()`.
- `DatabaseSeeder`: 1 admin, 3 managers and 7 employees; 40 requests across all statuses, advanced through the real services.

## Key decisions

- **403 vs 409.** The policy decides *who* may act; the service decides whether the *state* allows it.
- **Separation of duties.** Nobody can start review on, approve or reject a request they created.
- **Assignees** must be managers or admins (validated with `Rule::exists(...)->whereIn('role', ...)`).
- **Employee visibility.** Employees see requests they created or are assigned to. Managers and admins see all.
- **Enum columns** are strings plus PHP backed enums, not MySQL `ENUM`.
- **No public registration.** It's an internal tool, so admin user management is deferred to Phase 3.
- **Route parameter** is `{operationalRequest}`, to avoid confusion with `Illuminate\Http\Request`.

## Test results (last run 2026-11-02)

- `php artisan test`: **42 passed, 189 assertions**, 0 failed.
- `vendor/bin/pint`: passed.
- `migrate:fresh --seed` on MySQL 8.4: all migrations ran; the seeder produced 11 users, 40 requests and 101 history rows.
- Live HTTP smoke test against MySQL confirmed 201 create, 200 submit, 403 employee approve, 409 approve-before-review, 422 reject reason validation, clean 404 and 401 without a token.

## Known gaps and outstanding items

- No audit log, comments, AI analysis, caching or queued jobs yet (Phase 2).
- No web UI; API only (Phase 3).
- No admin user and role management endpoint yet.
- The `queue` service in `compose.yaml` is defined but has no jobs to process until Phase 2.
- Stock `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php` are still present (left untouched).

## Next steps (Phase 2)

1. Set `QUEUE_CONNECTION=redis` and `CACHE_STORE=redis`.
2. Add the `ai_analyses` and `audit_logs` migrations and models, plus `request_comments`.
3. Add `AIRequestAnalyzerInterface` with `LocalRequestAnalyzer` and `OpenAIRequestAnalyzer`, bound in a service provider with fallback to local when `OPENAI_API_KEY` is empty.
4. Add `AnalyzeOperationalRequestJob` (tries and backoff, `failed()` handler), dispatched after commit from `RequestWorkflowService::submit`.
5. Add an `AuditService` called from inside the existing workflow and assignment transactions.
6. Add cached stats with invalidation, and tests using `Queue::fake()`, `Cache` assertions and the analyzer contract.
