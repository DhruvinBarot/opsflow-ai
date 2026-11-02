@php($repository = config('app.repository_url'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('partials.head', ['title' => null])
</head>
<body>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 btn btn-secondary">Skip to content</a>

    {{-- Navigation: structure ported from the 21st "Hero Section 6" header (collapsible mobile menu, dashed divider). --}}
    <header x-data="siteNav" class="sticky top-0 z-30 border-b border-dashed border-line bg-canvas/85 backdrop-blur" @keydown.escape="open = false">
        <nav class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-3" aria-label="Main">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold" aria-label="OpsFlow AI home">@include('partials.logo')</a>

            <button type="button" class="btn btn-ghost px-2 md:hidden" @click="toggle()" :aria-expanded="open" aria-controls="site-menu" :aria-label="open ? 'Close menu' : 'Open menu'">
                <x-icon name="menu" class="size-5" x-show="!open" />
                <x-icon name="x" class="size-5" x-show="open" x-cloak />
            </button>

            <div id="site-menu" class="w-full md:flex md:w-auto md:items-center md:gap-8" :class="open ? 'block' : 'hidden'">
                <ul class="grid gap-1 py-3 text-sm md:flex md:gap-6 md:py-0">
                    @foreach (['#product' => 'Product', '#features' => 'Features', '#how-it-works' => 'How it works', '#architecture' => 'Architecture'] as $href => $label)
                        <li><a href="{{ $href }}" class="block rounded-md px-2 py-2 text-ink-soft hover:text-ink md:p-0" @click="open = false">{{ $label }}</a></li>
                    @endforeach
                </ul>
                <div class="flex gap-2 border-t border-line pt-3 md:border-0 md:border-l md:pt-0 md:pl-6">
                    <button type="button" class="btn btn-ghost px-2" @click="$store.theme.toggle()" :aria-label="$store.theme.dark ? 'Switch to light theme' : 'Switch to dark theme'">
                        <x-icon name="moon" x-show="!$store.theme.dark" /><x-icon name="sun" x-show="$store.theme.dark" x-cloak />
                    </button>
                    @auth
                        <a href="{{ route('app.overview') }}" class="btn btn-primary">Open the app</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-secondary">Sign in</a>
                        <a href="{{ route('login') }}" class="btn btn-primary">Launch demo</a>
                    @endauth
                </div>
            </div>
        </nav>
    </header>

    <main id="main">
        {{-- Hero --}}
        <section id="product" class="relative overflow-hidden">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 [background-image:linear-gradient(to_right,var(--color-line)_1px,transparent_1px),linear-gradient(to_bottom,var(--color-line)_1px,transparent_1px)] [background-size:56px_56px] opacity-40 [mask-image:radial-gradient(ellipse_at_top,black_20%,transparent_70%)]"></div>

            <div class="relative mx-auto max-w-6xl px-6 pt-20 pb-12 text-center sm:pt-28">
                <p class="mx-auto inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1 text-xs text-ink-soft">
                    <x-icon name="sparkles" class="size-3.5 text-ai" /> AI triage that advises; people decide
                </p>
                <h1 class="mx-auto mt-6 max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl lg:leading-[1.05]">
                    Operational workflows. <span class="text-muted">Intelligent triage.</span>
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-base text-ink-soft sm:text-lg">
                    Manage operational requests, streamline approvals and turn incoming work into actionable recommendations with AI-assisted analysis.
                </p>
                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('login') }}" class="btn btn-primary min-h-11 px-5">Explore the demo <x-icon name="arrow-right" /></a>
                    <a href="#features" class="btn btn-secondary min-h-11 px-5">View features</a>
                    @if ($repository)
                        <a href="{{ $repository }}" class="btn btn-ghost min-h-11 px-4" rel="noopener"><x-icon name="github" /> View on GitHub</a>
                    @endif
                </div>
            </div>

            {{-- Product preview: a static rendering of the real dashboard layout, with example data. --}}
            <div class="relative mx-auto max-w-6xl px-6 pb-20">
                <figure class="overflow-hidden rounded-2xl border border-line bg-surface shadow-(--shadow-pop)">
                    <div class="flex items-center gap-2 border-b border-line px-4 py-2.5">
                        <span class="size-2.5 rounded-full bg-line-strong"></span><span class="size-2.5 rounded-full bg-line-strong"></span><span class="size-2.5 rounded-full bg-line-strong"></span>
                        <span class="ml-3 font-mono text-[11px] text-muted">opsflow / review queue</span>
                    </div>
                    <div class="grid gap-4 p-4 text-left sm:p-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
                        <div class="grid content-start gap-3">
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ([['Open', 26], ['Pending review', 15], ['High priority', 9], ['Completed', 8]] as [$label, $value])
                                    <div class="rounded-(--radius-control) border border-line p-3">
                                        <p class="text-xs text-muted">{{ $label }}</p>
                                        <p class="mt-0.5 text-2xl font-semibold tabular-nums">{{ $value }}</p>
                                    </div>
                                @endforeach
                            </div>
                            <div class="overflow-x-auto rounded-(--radius-control) border border-line">
                                <table class="table text-[13px]">
                                    <thead><tr><th>ID</th><th>Request</th><th>Status</th><th>AI risk</th></tr></thead>
                                    <tbody>
                                        @foreach ([
                                            [42, 'Production API latency above 2s', 'Submitted', 'tone-info', 'High', 'tone-danger'],
                                            [17, 'Quarterly SOC 2 access review', 'Under review', 'tone-warn', 'Medium', 'tone-warn'],
                                            [31, 'Rotate expiring TLS certificate', 'In progress', 'tone-info', 'Medium', 'tone-warn'],
                                            [8, 'Refund failed upgrade payment', 'Completed', 'tone-ok', 'Low', 'tone-ok'],
                                        ] as [$id, $title, $status, $statusTone, $risk, $riskTone])
                                            <tr>
                                                <td class="mono-id">#{{ $id }}</td>
                                                <td class="font-medium whitespace-nowrap">{{ $title }}</td>
                                                <td><span class="pill {{ $statusTone }}">{{ $status }}</span></td>
                                                <td><span class="pill {{ $riskTone }}">{{ $risk }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="rounded-(--radius-card) border border-ai-line bg-ai-soft p-4">
                            <div class="flex items-center justify-between gap-2">
                                <p class="flex items-center gap-2 text-sm font-semibold"><x-icon name="sparkles" class="text-ai" /> AI recommendation</p>
                                <span class="eyebrow text-ai">Advisory</span>
                            </div>
                            <p class="mt-3 text-sm">Production API latency above 2s: p95 latency on checkout has exceeded 2s in production since 09:00.</p>
                            <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                @foreach (['Category' => 'Incident', 'Priority' => 'High', 'Risk' => 'High', 'Team' => 'Platform'] as $k => $v)
                                    <div class="rounded-md border border-line bg-surface px-2.5 py-1.5"><dt class="text-muted">{{ $k }}</dt><dd class="font-semibold">{{ $v }}</dd></div>
                                @endforeach
                            </dl>
                            <p class="mt-3 border-l-3 border-ai pl-2.5 text-[13px]">Page the platform on-call, confirm the scope of impact and open an incident channel.</p>
                        </div>
                    </div>
                    <figcaption class="border-t border-line px-4 py-2 text-center text-xs text-muted">Product preview with example data.</figcaption>
                </figure>
            </div>
        </section>

        {{-- Features --}}
        <section id="features" class="border-t border-line bg-surface py-20">
            <div class="mx-auto max-w-6xl px-6">
                <p class="eyebrow">Features</p>
                <h2 class="mt-2 max-w-2xl text-3xl font-semibold tracking-tight">Everything a request needs, from intake to sign-off.</h2>
                <div class="mt-10 grid gap-px overflow-hidden rounded-(--radius-card) border border-line bg-line sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['sparkles', 'AI request analysis', 'Suggests category, priority, risk, owning team and a next action. Every field is validated against fixed values, and nothing is applied automatically.'],
                        ['review', 'Controlled workflow', 'Draft → submitted → review → approved or rejected → in progress → completed. Invalid jumps are refused by the server.'],
                        ['lock', 'Role-based access', 'Employees, managers and admins see and do different things, enforced by Laravel policies on every API call.'],
                        ['zap', 'Background processing', 'Analysis runs on a Redis-backed queue worker with retries, backoff and a local fallback analyzer.'],
                        ['refresh', 'Live status visibility', 'Pages refresh request and analysis state from the API as work moves, without a page reload.'],
                        ['activity', 'Audit trail', 'Every creation, transition, assignment, comment and AI result is recorded with its actor, in the same transaction.'],
                        ['database', 'MySQL-backed', 'Relational schema with foreign keys, uniqueness constraints and indexes shaped to the real queries.'],
                        ['chart', 'Operational dashboard', 'Open work, pending reviews, priorities and AI pipeline health, cached in Redis and scoped to each role.'],
                    ] as [$icon, $title, $text])
                        <div class="bg-surface p-6">
                            <span class="grid size-9 place-items-center rounded-(--radius-control) border border-line {{ $icon === 'sparkles' ? 'bg-ai-soft text-ai' : 'bg-subtle text-ink-soft' }}"><x-icon :name="$icon" /></span>
                            <h3 class="mt-4 text-sm font-semibold">{{ $title }}</h3>
                            <p class="mt-1.5 text-sm text-muted">{{ $text }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- How it works: the pipeline trace borrowed from direction B --}}
        <section id="how-it-works" class="py-20">
            <div class="mx-auto grid max-w-6xl gap-10 px-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:items-center">
                <div>
                    <p class="eyebrow">How it works</p>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight">One request, five steps, nothing hidden.</h2>
                    <ol class="mt-8 grid gap-5">
                        @foreach ([
                            ['Submit request', 'The employee submits; the status change, history entry and audit record commit together.'],
                            ['Queue AI analysis', 'A job is pushed to the Redis ai queue only after that commit. The API responds straight away.'],
                            ['Review recommendations', 'A worker runs the analyzer and stores a validated, advisory recommendation.'],
                            ['Manager approval', 'A manager who did not raise the request approves or rejects it, with a reason.'],
                            ['Track completion', 'Assigned staff start and complete the work; every step stays in the audit trail.'],
                        ] as $index => [$title, $text])
                            <li class="flex gap-4">
                                <span class="grid size-7 shrink-0 place-items-center rounded-full border border-line-strong font-mono text-xs">{{ $index + 1 }}</span>
                                <div><h3 class="text-sm font-semibold">{{ $title }}</h3><p class="mt-0.5 text-sm text-muted">{{ $text }}</p></div>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <figure class="overflow-hidden rounded-(--radius-card) border border-[#243050] bg-[#0b1020] text-[#e6eaf2] shadow-(--shadow-pop)">
                    <div class="flex items-center justify-between border-b border-[#243050] px-4 py-2.5 font-mono text-[11px] text-[#93a0b8]">
                        <span>request #42 · pipeline trace</span><span>example</span>
                    </div>
                    <ol class="grid gap-2.5 p-4 font-mono text-[12.5px] leading-5 sm:p-5">
                        @foreach ([
                            ['16:38:01', 'POST /api/requests/42/submit', '200', '#4ade80'],
                            ['16:38:01', 'tx commit · status_history + audit_log', 'ok', '#4ade80'],
                            ['16:38:01', 'queue:ai ← AnalyzeOperationalRequestJob', 'queued', '#67d4f5'],
                            ['16:38:05', 'worker picked job · analyzer=local', 'running', '#67d4f5'],
                            ['16:38:07', 'validated · risk=high team=platform', 'stored', '#4ade80'],
                            ['16:38:07', 'audit · ai_analysis_completed', 'ok', '#4ade80'],
                            ['16:52:13', 'POST /api/requests/42/approve · manager', 'pending', '#fbbf24'],
                        ] as [$time, $event, $state, $color])
                            <li class="grid grid-cols-[4.5rem_minmax(0,1fr)_auto] gap-3">
                                <span class="text-[#93a0b8]">{{ $time }}</span>
                                <span class="truncate">{{ $event }}</span>
                                <span style="color: {{ $color }}">{{ $state }}</span>
                            </li>
                        @endforeach
                    </ol>
                    <figcaption class="border-t border-[#243050] px-4 py-2 text-[11px] text-[#93a0b8]">Illustrative trace of the real request lifecycle. Timings are examples.</figcaption>
                </figure>
            </div>
        </section>

        {{-- Architecture --}}
        <section id="architecture" class="border-t border-line bg-surface py-20">
            <div class="mx-auto max-w-6xl px-6">
                <p class="eyebrow">Architecture</p>
                <h2 class="mt-2 max-w-2xl text-3xl font-semibold tracking-tight">A conventional Laravel backend, built carefully.</h2>
                <p class="mt-3 max-w-2xl text-muted">MySQL is the source of truth. Redis carries the queue and the dashboard cache. The AI provider sits behind an interface, with a deterministic local analyzer as the default and the fallback.</p>

                <div class="mt-10 grid gap-3 lg:grid-cols-[1fr_auto_1.4fr_auto_1fr] lg:items-stretch" role="img" aria-label="Clients call the Laravel API. Laravel reads and writes MySQL, caches statistics in Redis, and pushes analysis jobs to a Redis queue consumed by a worker that runs the analyzer and writes results back to MySQL.">
                    <div class="grid content-start gap-3">
                        <div class="rounded-(--radius-card) border border-line p-4"><p class="eyebrow">Client</p><p class="mt-1 text-sm font-semibold">Blade + Alpine UI</p><p class="text-xs text-muted">Session cookie, CSRF-protected</p></div>
                        <div class="rounded-(--radius-card) border border-line p-4"><p class="eyebrow">Client</p><p class="mt-1 text-sm font-semibold">API consumers</p><p class="text-xs text-muted">Sanctum bearer tokens</p></div>
                    </div>
                    <div class="hidden items-center text-muted lg:flex" aria-hidden="true"><x-icon name="arrow-right" class="size-5" /></div>
                    <div class="rounded-(--radius-card) border border-primary/40 bg-primary-soft/50 p-4">
                        <p class="eyebrow text-primary-ink">Laravel 13 · PHP 8.4</p>
                        <ol class="mt-3 grid gap-2 text-sm">
                            <li class="rounded-md border border-line bg-surface px-3 py-2"><span class="font-medium">Routes</span> <span class="text-muted">· auth:sanctum, rate limits</span></li>
                            <li class="rounded-md border border-line bg-surface px-3 py-2"><span class="font-medium">Form Requests + Policies</span> <span class="text-muted">· 422 / 403</span></li>
                            <li class="rounded-md border border-line bg-surface px-3 py-2"><span class="font-medium">Thin controllers → API Resources</span></li>
                            <li class="rounded-md border border-line bg-surface px-3 py-2"><span class="font-medium">Services</span> <span class="text-muted">· workflow (409), assignment, AI analysis, audit, stats</span></li>
                        </ol>
                    </div>
                    <div class="hidden items-center text-muted lg:flex" aria-hidden="true"><x-icon name="arrow-right" class="size-5" /></div>
                    <div class="grid content-start gap-3">
                        <div class="rounded-(--radius-card) border border-line p-4"><p class="eyebrow">Source of truth</p><p class="mt-1 flex items-center gap-2 text-sm font-semibold"><x-icon name="database" /> MySQL 8.4</p><p class="text-xs text-muted">Transactions, row locks, FKs, indexes</p></div>
                        <div class="rounded-(--radius-card) border border-line p-4"><p class="eyebrow">Cache + queue</p><p class="mt-1 flex items-center gap-2 text-sm font-semibold"><x-icon name="zap" /> Redis 7</p><p class="text-xs text-muted">Role-scoped stats · ai queue</p></div>
                        <div class="rounded-(--radius-card) border border-ai-line bg-ai-soft p-4"><p class="eyebrow text-ai">Worker</p><p class="mt-1 flex items-center gap-2 text-sm font-semibold"><x-icon name="server" /> Queue worker</p><p class="text-xs text-muted">Local analyzer or OpenAI → validated result → MySQL</p></div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Final CTA --}}
        <section class="py-20">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <h2 class="text-3xl font-semibold tracking-tight">See a request move from intake to sign-off.</h2>
                <p class="mt-3 text-muted">Sign in with a seeded employee, manager or admin account and walk through the full workflow.</p>
                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('login') }}" class="btn btn-primary min-h-11 px-5">Sign in to the demo <x-icon name="arrow-right" /></a>
                    @if ($repository)
                        <a href="{{ $repository }}" class="btn btn-secondary min-h-11 px-5" rel="noopener"><x-icon name="github" /> View the repository</a>
                    @endif
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-line">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-6 py-6 text-sm text-muted">
            <span class="flex items-center gap-2">@include('partials.logo')</span>
            <span>A portfolio project · Laravel, MySQL, Redis</span>
        </div>
    </footer>
</body>
</html>
