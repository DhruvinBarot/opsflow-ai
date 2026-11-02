<x-layouts.app title="Admin" heading="Operations console" description="Platform-wide visibility: workload, AI pipeline health, operations staff and the audit trail.">
    <div x-data="adminConsole" class="grid gap-6">
        <template x-if="error">
            @include('partials.error-banner', [])
        </template>

        <section aria-label="Workload" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach (['total' => 'Total requests', 'open' => 'Open', 'pending_approval' => 'Awaiting decision', 'completed' => 'Completed'] as $key => $label)
                <div class="card p-4">
                    <p class="text-[13px] text-muted">{{ $label }}</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight tabular-nums" x-text="loading ? '—' : stats?.{{ $key }}"></p>
                </div>
            @endforeach
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="card" aria-labelledby="pipeline-title">
                <div class="card-header">
                    <h2 id="pipeline-title" class="card-title flex items-center gap-2"><x-icon name="zap" class="text-ai" /> AI pipeline</h2>
                    <span class="eyebrow">Redis queue: ai</span>
                </div>
                <div class="grid gap-3 p-4">
                    {{-- Stacked share bar of analysis outcomes --}}
                    <div class="flex h-2.5 overflow-hidden rounded-full bg-subtle" role="img" :aria-label="`${stats?.ai_analysis?.completed ?? 0} completed, ${stats?.ai_analysis?.failed ?? 0} failed, ${(stats?.ai_analysis?.pending ?? 0) + (stats?.ai_analysis?.processing ?? 0)} in flight`">
                        <span class="bg-ok" :style="`width: ${aiTotal ? (stats.ai_analysis.completed / aiTotal) * 100 : 0}%`"></span>
                        <span class="bg-ai" :style="`width: ${aiTotal ? ((stats.ai_analysis.pending + stats.ai_analysis.processing) / aiTotal) * 100 : 0}%`"></span>
                        <span class="bg-danger" :style="`width: ${aiTotal ? (stats.ai_analysis.failed / aiTotal) * 100 : 0}%`"></span>
                    </div>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="flex items-center gap-1.5 text-muted"><span class="size-2 rounded-full bg-ok"></span>Completed</dt><dd class="text-lg font-semibold tabular-nums" x-text="stats?.ai_analysis?.completed ?? '—'"></dd></div>
                        <div><dt class="flex items-center gap-1.5 text-muted"><span class="size-2 rounded-full bg-ai"></span>In flight</dt><dd class="text-lg font-semibold tabular-nums" x-text="stats ? stats.ai_analysis.pending + stats.ai_analysis.processing : '—'"></dd></div>
                        <div><dt class="flex items-center gap-1.5 text-muted"><span class="size-2 rounded-full bg-danger"></span>Failed</dt><dd class="text-lg font-semibold tabular-nums" x-text="stats?.ai_analysis?.failed ?? '—'"></dd></div>
                        <div><dt class="text-muted">Local fallbacks</dt><dd class="text-lg font-semibold tabular-nums" x-text="stats?.ai_analysis?.fallbacks ?? '—'"></dd></div>
                    </dl>
                    <p class="text-xs text-muted">Failed analyses never block a request. Inspect failed jobs with <code class="font-mono">php artisan queue:failed</code>.</p>
                </div>
            </section>

            <section class="card lg:col-span-2" aria-labelledby="staff-title">
                <div class="card-header">
                    <h2 id="staff-title" class="card-title flex items-center gap-2"><x-icon name="users" class="text-muted" /> Operations staff</h2>
                    <span class="eyebrow" x-text="`${staff.length} people`"></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th></tr></thead>
                        <tbody>
                            <template x-for="person in staff" :key="person.id">
                                <tr>
                                    <td class="font-medium" x-text="person.name"></td>
                                    <td class="text-muted" x-text="person.email"></td>
                                    <td><span class="pill" :class="person.role === 'admin' ? 'tone-info' : 'tone-neutral'" x-text="person.role"></span></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-line px-4 py-3 text-xs text-muted">Read-only. Roles are assigned outside the app in this version (seeders or the database); there is no role-management endpoint yet.</p>
            </section>
        </div>

        <section class="card" aria-labelledby="audit-title">
            <div class="card-header">
                <h2 id="audit-title" class="card-title flex items-center gap-2"><x-icon name="activity" class="text-muted" /> Audit trail</h2>
                <span class="eyebrow">All requests</span>
            </div>
            <div class="p-4">
                @include('partials.activity-timeline', ['groups' => 'activityGroups', 'withRequest' => true])
            </div>
            <div class="border-t border-line p-3 text-center" x-show="activityMeta && activityMeta.current_page < activityMeta.last_page">
                <button type="button" class="btn btn-secondary btn-sm" :disabled="loadingMore" @click="loadMore()" x-text="loadingMore ? 'Loading…' : 'Load older events'"></button>
            </div>
        </section>
    </div>
</x-layouts.app>
