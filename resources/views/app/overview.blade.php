<x-layouts.app title="Overview" heading="Overview" :description="auth()->user()->can('operations') ? 'Operational load across every request, refreshed from cached statistics.' : 'Where your requests stand right now.'">
    <x-slot:actions>
        <a href="{{ route('app.requests.create') }}" class="btn btn-primary"><x-icon name="plus" /> New request</a>
        @can('operations')
            <a href="{{ route('app.review') }}" class="btn btn-secondary"><x-icon name="review" /> Review queue</a>
        @endcan
    </x-slot:actions>

    <div x-data="overview" class="grid gap-6">
        <template x-if="error">
            @include('partials.error-banner', ['retry' => 'init()'])
        </template>

        {{-- KPI row --}}
        <section aria-label="Key figures" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ([
                ['key' => 'open', 'label' => 'Open requests', 'hint' => 'Submitted through in progress'],
                ['key' => 'pending_review', 'label' => 'Pending review', 'hint' => 'Submitted or under review'],
                ['key' => 'high_priority_open', 'label' => 'High priority', 'hint' => 'High or critical, still open'],
                ['key' => 'completed', 'label' => 'Completed', 'hint' => 'Closed after delivery'],
            ] as $kpi)
                <div class="card p-4">
                    <p class="text-[13px] text-muted">{{ $kpi['label'] }}</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight tabular-nums" x-show="!loading" x-text="stats?.{{ $kpi['key'] }} ?? '—'"></p>
                    <div class="skeleton mt-2 h-8 w-16" x-show="loading" aria-hidden="true"></div>
                    <p class="mt-1 text-xs text-muted">{{ $kpi['hint'] }}</p>
                </div>
            @endforeach
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Requests by status --}}
            <section class="card lg:col-span-2" aria-labelledby="by-status-title">
                <div class="card-header">
                    <h2 id="by-status-title" class="card-title">Requests by status</h2>
                    <span class="eyebrow" x-text="stats ? `${stats.total} total` : ''"></span>
                </div>
                <ul class="grid gap-3 p-4">
                    <template x-for="bar in statusBars" :key="bar.key">
                        <li class="grid grid-cols-[7.5rem_1fr_2.5rem] items-center gap-3 text-sm">
                            <span class="text-ink-soft" x-text="$fmt.status(bar.key)"></span>
                            <span class="h-2 overflow-hidden rounded-full bg-subtle" aria-hidden="true">
                                <span class="block h-full rounded-full bg-primary transition-[width] duration-500" :style="`width: ${loading ? '0%' : bar.width}`"></span>
                            </span>
                            <span class="text-right font-medium tabular-nums" x-text="loading ? '' : bar.count"></span>
                        </li>
                    </template>
                </ul>
            </section>

            {{-- AI pipeline --}}
            <section class="card" aria-labelledby="ai-status-title">
                <div class="card-header">
                    <h2 id="ai-status-title" class="card-title flex items-center gap-2"><x-icon name="sparkles" class="text-ai" /> AI analysis</h2>
                    <span class="pill tone-ai">Advisory</span>
                </div>
                <dl class="grid grid-cols-2 gap-px bg-line">
                    @foreach (['completed' => 'Completed', 'pending' => 'Queued', 'processing' => 'Running', 'failed' => 'Failed'] as $key => $label)
                        <div class="bg-surface p-4">
                            <dt class="text-[13px] text-muted">{{ $label }}</dt>
                            <dd class="mt-0.5 text-xl font-semibold tabular-nums" :class="'{{ $key }}' === 'failed' && stats?.ai_analysis?.failed ? 'text-danger' : ''" x-text="loading ? '—' : (stats?.ai_analysis?.{{ $key }} ?? 0)"></dd>
                        </div>
                    @endforeach
                </dl>
                <p class="border-t border-line px-4 py-3 text-xs text-muted">
                    <span x-text="stats?.ai_analysis?.fallbacks ?? 0"></span> completed with the local fallback analyzer.
                    Recommendations never change a request on their own.
                </p>
            </section>
        </div>

        <div class="grid gap-6" :class="ops ? 'lg:grid-cols-2' : ''">
            {{-- Recent requests --}}
            <section class="card" aria-labelledby="recent-title">
                <div class="card-header">
                    <h2 id="recent-title" class="card-title" x-text="ops ? 'Recent requests' : 'Your recent requests'"></h2>
                    <a href="{{ auth()->user()->can('operations') ? route('app.requests.all') : route('app.requests.mine') }}" class="text-sm font-medium text-primary-ink hover:underline">View all</a>
                </div>
                <ul class="divide-y divide-line">
                    <template x-if="loading">
                        <li class="grid gap-2 p-4" aria-hidden="true"><div class="skeleton h-4 w-2/3"></div><div class="skeleton h-3 w-1/3"></div></li>
                    </template>
                    <template x-for="item in recent" :key="item.id">
                        <li>
                            <a :href="`/app/requests/${item.id}`" class="flex items-center gap-3 px-4 py-3 hover:bg-subtle/60">
                                <span class="mono-id w-10 shrink-0" x-text="`#${item.id}`"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium" x-text="item.title"></span>
                                    <span class="block text-xs text-muted" x-text="`${$fmt.category(item.category)} · ${$fmt.ago(item.created_at)}`"></span>
                                </span>
                                <span class="pill" :class="$fmt.statusTone(item.status)" x-text="$fmt.status(item.status)"></span>
                            </a>
                        </li>
                    </template>
                    <template x-if="!loading && !recent.length">
                        <li class="p-6 text-center text-sm text-muted">
                            No requests yet. <a href="{{ route('app.requests.create') }}" class="font-medium text-primary-ink hover:underline">Create the first one</a>.
                        </li>
                    </template>
                </ul>
            </section>

            {{-- Recent activity (operations only: it comes from the audit log) --}}
            @can('operations')
                <section class="card" aria-labelledby="activity-title">
                    <div class="card-header">
                        <h2 id="activity-title" class="card-title">Recent activity</h2>
                        <span class="eyebrow">Audit log</span>
                    </div>
                    <div class="p-4">
                        @include('partials.activity-timeline', ['groups' => 'activityGroups', 'withRequest' => true])
                    </div>
                </section>
            @endcan
        </div>
    </div>
</x-layouts.app>
