# OpsFlow AI: Project Progress

Use this file to restore context at the start of a new session.

## Status

| Phase | Scope | State |
| --- | --- | --- |
| 1 | Foundation and core backend | **Complete** (2026-11-02, commit `0023890`) |
| 2 | AI analysis, Redis queue and cache, audit log, comments | **Complete** (2026-11-02, uncommitted) |
| 3 | Web UI ("Ledger" design), session auth, UI-supporting endpoints, final review | **Complete** (2026-11-02, uncommitted; awaiting user review) |

Phases 2 and 3 are both uncommitted on top of commit `0023890`.

## Phase 3: what exists

**Design**

- The user chose direction A, "Ledger" (Swiss minimal, light-first with dark support), plus B's pipeline trace on the landing page.
- A comparison page of the three directions was published as a private artifact: https://claude.ai/artifact/UvGuFAdrHCBEpUD5ELhtPf

**Stack**

- Blade with Alpine.js 3 (the only new npm dependency) and Tailwind 4 via Vite. Build with `npm install && npm run build` on the host.
- The design tokens are in `resources/css/app.css`, with a `.dark` override.

**Authentication**

- `Web\SessionController` handles login and logout with sessions (`throttle:login`).
- `bootstrap/app.php` uses `statefulApi()`, so the UI calls `/api/*` with the session cookie and CSRF token.
- `/api/auth/logout` now returns 400 for session callers instead of crashing on `TransientToken`.

**Pages** (`routes/web.php`)

- `/`, `/login`, `/app`, `/app/requests`, `/app/requests/new`, `/app/requests/{id}` and `/app/requests/{id}/edit`, `/app/all-requests` and `/app/review` (`can:operations`), `/app/admin` (`can:admin`).

**JavaScript** (`resources/js`)

- `lib/api.js` is the fetch client: CSRF header, typed `ApiError`, redirect on 401 or 419.
- `lib/format.js` holds enum labels and tones, dates, `$fmt`.
- `stores.js` provides toasts, the confirm dialog and theme.
- `pages/*.js` are the Alpine components.

**Views**

- `components/layouts/app.blade.php` (shell), `components/icon.blade.php` (inline Lucide paths).
- Partials: AI panel, activity timeline, confirm dialog, toasts, error banner.
- Pages under `app/`, plus `landing` and `auth/login`.

**Backend additions**

- `GET /api/activity` and `GET /api/users/assignable` (both operations-only, via `Gate 'operations'`).
- The list endpoint gains `search` (LIKE with an explicit `!` escape) and `sort` (an allow-list), and eager-loads `aiAnalysis`.
- Stats gain `ai_analysis` counts, with `AIAnalysisObserver` invalidating them.
- Gates `operations` and `admin`, and config `app.repository_url` (`APP_REPOSITORY_URL`).

**21st MCP**

- Free search was used for heroes, sidebars, KPIs, tables, timelines, AI panels, steppers, empty states and dialogs.
- Code for "Hero Section 6" and "Activity Timeline" was retrieved, which used the free tier's 2 daily downloads. Both were ported to Blade, not installed, because they are React/shadcn.

## Phase 3 verification (2026-11-02)

- `php artisan test`: **127 passed (475 assertions), 0 failed**, including all Phase 1 and 2 tests. `vendor/bin/pint`: passed. `npm run build`: passed (CSS 14.6 KB and JS 24.8 KB gzipped).
- **Live HTTP checks against the Docker app:**
  - Login page with CSRF; manager login gives 302 to `/app`.
  - All pages return 200 for the manager, `/app/admin` returns 403.
  - The session API returns stats with AI counts.
  - An API POST without the XSRF header returns 419; with it, 201.
  - Assignable staff, the activity feed and search all work.
  - The employee navigation hides Review queue and Admin.
  - Logout then `/app` redirects to `/login`.
- **Security review.** The Ruflo `reviewer` agent did a read-only pass over authorization, XSS, SQL, CSRF and session handling, N+1 queries and caching, and found no high-severity issues. Four UI logic findings were fixed:
  - AI polling stopped after one failed poll.
  - Review-queue tab responses could arrive out of order.
  - An invalid `?queue=` value broke the page.
  - An error toast flashed during the 401 redirect.
  The tests were re-run afterwards: 127 passed.
- **Deployment note.** Cookie-authenticated API calls only work on hosts listed in `SANCTUM_STATEFUL_DOMAINS`, which defaults to localhost plus the `APP_URL` host.
- **Not verified:** visual rendering in a real browser. The Claude in Chrome extension was declined and no headless browser is installed. Alpine bindings were reviewed statically against the component data.

## Environment

- Project root: `C:\Users\dhruv\opsflow-ai`. The host has no PHP; everything runs through `docker compose` (`app`, `queue`, `mysql`, `redis`).
- Run commands as `docker compose run --rm app <cmd>` or `docker compose exec app <cmd>`. From Git Bash, prefix with `MSYS_NO_PATHCONV=1`.
- `.env` now uses `QUEUE_CONNECTION=redis` and `CACHE_STORE=redis`. Laravel's cache lives in Redis DB 1; queues are in DB 0.
- The `queue` worker must be restarted (`php artisan queue:restart`) after code changes.
- `OPENAI_API_KEY` is empty, so the local analyzer is active. `phpunit.xml` forces it empty in tests.
- Laravel Boost guidelines are installed (`CLAUDE.md`, `AGENTS.md`, `boost.json`).

## Phase 1 summary

Enums (`Role`, `RequestStatus` with the transition graph, `RequestPriority`, `RequestCategory`); models `User`, `OperationalRequest`, `RequestStatusHistory` and `RequestAssignment`; `RequestWorkflowService` (transaction plus `lockForUpdate`); `RequestAssignmentService`; `OperationalRequestPolicy` (the policy decides 403 for *who*, the service decides 409 for *state*); separation of duties; Sanctum login with a rate limit; Form Requests and API Resources; a seeder that drives the real services.

## Phase 2: what exists

**AI (`app/Services/AI`)**

- `AIRequestAnalyzerInterface`, with `OpenAIRequestAnalyzer` (Chat Completions, strict JSON schema, timeouts) and `LocalRequestAnalyzer` (deterministic keyword rules).
- `AnalysisResult::fromArray()` validates all output against the enums (`RiskLevel` and `Team` are new).
- `AIProviderException` carries an error code and a transient flag; `InvalidAnalysisOutputException` covers unusable output.
- `AIRequestAnalysisService` provides `queue()` (the pending row, via `createOrFirst`), `analyze()` (idempotent; retry vs. fallback) and `markFailed()`.
- Binding lives in `AppServiceProvider::register()`; the config is `services.openai`.

**Async**

- `App\Jobs\AnalyzeOperationalRequestJob` runs on the `ai` queue: `ShouldBeUnique` keyed by request id, `tries = 3`, backoff of 10 s then 60 s, `timeout = 60`, and a `failed()` handler that calls `markFailed`.
- It is dispatched with `afterCommit()` inside `RequestWorkflowService::transition()` when the target is `submitted`.

**Audit, comments and cache**

- `AuditService::record()` sanitizes metadata and is called inside the transactions for create, update, transitions, assign, comment and AI events. The `AuditAction` enum holds the actions.
- `RequestComment` with `RequestCommentController` (`index` and `store`, the latter transactional with an audit record).
- `DashboardStatsService` keys stats per scope with a random version token, a 300 s TTL and a MySQL fallback. `OperationalRequestObserver` (after commit) sets a new token.

**API**

- New endpoints: `GET /api/dashboard/stats`, `GET /api/requests/{id}/ai-analysis`, `GET` and `POST /api/requests/{id}/comments`, `GET /api/requests/{id}/activity` (operations only).
- `GET /api/requests/{id}` now embeds `ai_analysis`.

**Migrations (additive)**

- `2026_11_03_100000_create_ai_analyses_table` (unique `operational_request_id`).
- `2026_11_03_100100_create_request_comments_table`.
- `2026_11_03_100200_create_audit_logs_table`.

## Key decisions (Phase 2)

- **Advisory AI.** Analysis never writes to `operational_requests`.
- **Retry vs. fallback.** Transient errors (timeout, 5xx, 429) are retried by the queue, falling back to local on the final attempt. Invalid output, refusals and auth errors fall back immediately.
- **Audit entity.** Request-related audits all use `entity_type = operational_request`, with ids such as `comment_id` and `analysis_id` in the metadata. This keeps one indexed query per activity feed.
- **Activity feed.** It is operations-only because it includes reviewer reasons and risk data. Comments and analysis follow the request's `view` rule.
- **Cache invalidation.** A global random version token rather than per-key deletes. It is simpler, cannot miss a scope, and cannot regress if the key is evicted; superseded keys expire on their TTL.
- **Dispatch timing.** The job is dispatched inside `DB::afterCommit()`, not with `->afterCommit()`, because `ShouldBeUnique` takes its lock at dispatch time (found in the Ruflo reviewer pass).
- **No content in logs.** Comment bodies are never copied into audit metadata, and request text never reaches the logs.

## Verification (2026-11-02)

- `php artisan test`: **108 passed (398 assertions), 0 failed** (all 42 Phase 1 tests still pass). `vendor/bin/pint`: passed.
- **Security review.** A read-only review by the Ruflo `reviewer` agent found no high-severity issues. Two findings were fixed: the unique lock taken before commit, and the version counter that could regress. Two were documented as limitations: a completed analysis is not refreshed after edits, and `failed_jobs` can hold SQL text.
- After the fixes, the live Redis flow was re-verified: submit, worker completed the analysis, stats invalidated.
- `php artisan migrate` on the existing MySQL dev database: the 3 new migrations ran, and the existing 41 requests were preserved (no fresh or refresh run).
- **Asynchronous queue, live.** With the worker stopped, submit returned `submitted` and the analysis stayed `pending`, with 1 job in the Redis `ai` queue and a unique lock present. After the worker started, the analysis completed, the queue depth was 0 and `failed_jobs` was 0.
- **Fallback, live.** A worker pointed at an unreachable provider failed attempt 1, retried after 10 s and failed again, retried after 60 s, then completed with the local fallback (`provider_timeout`, 3 attempts).
- **Cache, live.** A second stats read was a hit (same `generated_at`); after a submission the version was bumped and `pending_review` went from 14 to 15. Redis keys showed per-scope entries with TTLs of 300 s or less.
- **Authorization, live.** Employee reading `/activity` got 403; employee reading another user's analysis got 403; an empty comment got 422.
- **Logs.** No request text and no API key appear in `storage/logs/laravel.log`.

## Incident during Phase 2

- During live verification a `redis-cli FLUSHDB` was run against the Redis container's DB 0 before starting the worker. Before Phase 2 the app used MySQL for queue and cache, so Redis should not have held any app data, but its prior contents were not checked. The MySQL `cache` and `jobs` tables were untouched. No other destructive commands were run.

## Known gaps

- Tests use SQLite and the array cache; Redis and MySQL were verified manually only.
- The OpenAI analyzer has been tested with mocked HTTP only, never against the live API.
- There is no web UI, no global audit search and no admin user management (Phase 3).
- Stock `tests/*/ExampleTest.php` files are still present.

## Next steps (Phase 3)

1. Blade dashboard: my requests, all requests, review queue, request detail (analysis, comments, history), admin overview.
2. Admin user and role management endpoints and UI.
3. Final security, N+1 and index review; README polish; demo script.
