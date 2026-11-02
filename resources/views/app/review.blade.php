<x-layouts.app title="Review queue" heading="Review queue" description="Move requests through review, approval and delivery. AI recommendations are shown alongside each request; every decision is yours.">
    <div x-data="reviewQueue" class="grid gap-4">
        {{-- Stage tabs --}}
        <div class="card flex overflow-x-auto p-1" role="tablist" aria-label="Workflow stage">
            <template x-for="item in queues" :key="item.status">
                <button type="button" role="tab" class="flex min-w-max flex-1 items-center justify-center gap-2 rounded-(--radius-control) px-3 py-2 text-sm font-medium transition-colors"
                        :class="active === item.status ? 'bg-primary-soft text-primary-ink' : 'text-ink-soft hover:bg-subtle'"
                        :aria-selected="active === item.status" @click="active = item.status">
                    <span x-text="item.label"></span>
                    <span class="rounded-full bg-surface px-1.5 text-xs tabular-nums text-muted" x-text="counts[item.status] ?? '·'"></span>
                </button>
            </template>
        </div>

        <template x-if="error">
            @include('partials.error-banner', ['retry' => 'load()'])
        </template>

        <ul class="grid gap-3" role="tabpanel" :aria-busy="loading">
            <template x-if="loading && !rows.length">
                <li class="card grid gap-2 p-4" aria-hidden="true"><div class="skeleton h-4 w-1/2"></div><div class="skeleton h-3 w-1/3"></div><div class="skeleton h-14 w-full"></div></li>
            </template>

            <template x-for="row in rows" :key="row.id">
                <li class="card grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)_auto] lg:items-center" :class="loading ? 'opacity-60' : ''">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="mono-id" x-text="`#${row.id}`"></span>
                            <span class="text-xs font-semibold uppercase tracking-wide" :class="$fmt.priorityTone(row.priority)" x-text="$fmt.priority(row.priority)"></span>
                            <span class="text-xs text-muted" x-text="`${$fmt.category(row.category)} · ${row.creator?.name} · ${$fmt.ago(row.submitted_at ?? row.created_at)}`"></span>
                        </div>
                        <a :href="`/app/requests/${row.id}`" class="mt-1 block truncate font-medium hover:underline" x-text="row.title"></a>
                        <p class="mt-0.5 text-xs text-muted" x-text="row.assignee ? `Assigned to ${row.assignee.name}` : 'Unassigned'"></p>
                    </div>

                    {{-- Compact AI recommendation --}}
                    <div class="rounded-(--radius-control) border border-ai-line bg-ai-soft px-3 py-2 text-[13px]">
                        <template x-if="row.ai_analysis?.recommendation">
                            <div class="grid gap-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <x-icon name="sparkles" class="size-3.5 text-ai" />
                                    <span class="pill" :class="$fmt.riskTone(row.ai_analysis.recommendation.risk_level)" x-text="`${$fmt.risk(row.ai_analysis.recommendation.risk_level)} risk`"></span>
                                    <span class="text-ink-soft" x-text="`→ ${$fmt.team(row.ai_analysis.recommendation.suggested_team)}`"></span>
                                </div>
                                <p class="line-clamp-2 text-muted" x-text="row.ai_analysis.recommendation.suggested_action"></p>
                            </div>
                        </template>
                        <template x-if="!row.ai_analysis?.recommendation">
                            <p class="flex items-center gap-1.5 text-muted"><x-icon name="sparkles" class="size-3.5 text-ai" />
                                <span x-text="row.ai_analysis?.status === 'failed' ? 'AI analysis unavailable' : row.ai_analysis ? 'Analysis in progress…' : 'No analysis'"></span>
                            </p>
                        </template>
                    </div>

                    <div class="flex flex-wrap gap-2 lg:justify-end">
                        <a :href="`/app/requests/${row.id}`" class="btn btn-secondary btn-sm">Open</a>
                        <template x-if="isOwn(row) && ['submitted', 'under_review'].includes(row.status)">
                            <span class="self-center text-xs text-muted">Your request: another reviewer decides</span>
                        </template>
                        <template x-if="!(isOwn(row) && ['submitted', 'under_review'].includes(row.status))">
                            <div class="flex gap-2">
                                <template x-if="row.status === 'under_review'">
                                    <button type="button" class="btn btn-secondary btn-sm text-danger" :disabled="busy" @click="act(row, 'reject')">Reject</button>
                                </template>
                                <button type="button" class="btn btn-primary btn-sm" :disabled="busy" @click="act(row, queue.action.key)"
                                        x-text="busy === `${row.id}:${queue.action.key}` ? 'Working…' : queue.action.label"></button>
                            </div>
                        </template>
                    </div>
                </li>
            </template>

            <template x-if="!loading && !error && !rows.length">
                <li class="card grid justify-items-center gap-2 px-6 py-14 text-center">
                    <span class="grid size-10 place-items-center rounded-full bg-ok-soft text-ok"><x-icon name="check" class="size-5" /></span>
                    <p class="font-medium">Nothing waiting here</p>
                    <p class="text-sm text-muted">No requests are in this stage right now.</p>
                </li>
            </template>
        </ul>

        <nav class="flex items-center justify-end gap-2 text-sm" aria-label="Pagination" x-show="meta && meta.last_page > 1">
            <button type="button" class="btn btn-secondary btn-sm" @click="load(meta.current_page - 1)" :disabled="meta?.current_page <= 1" aria-label="Previous page"><x-icon name="chevron-left" /></button>
            <span class="tabular-nums" x-text="`Page ${meta?.current_page} of ${meta?.last_page}`"></span>
            <button type="button" class="btn btn-secondary btn-sm" @click="load(meta.current_page + 1)" :disabled="meta?.current_page >= meta?.last_page" aria-label="Next page"><x-icon name="chevron-right" /></button>
        </nav>
    </div>
</x-layouts.app>
