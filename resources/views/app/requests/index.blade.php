@php
    $all = $scope === 'all';
    $enum = fn (string $class) => collect($class::cases())->map->value;
@endphp

<x-layouts.app :title="$all ? 'All requests' : 'My requests'" :heading="$all ? 'All requests' : 'My requests'"
               :description="$all ? 'Every operational request across the organization.' : 'Requests you have created, in every state.'">
    <x-slot:actions>
        <a href="{{ route('app.requests.create') }}" class="btn btn-primary"><x-icon name="plus" /> New request</a>
    </x-slot:actions>

    <div x-data="requestList(@js($scope))" class="grid gap-4">
        {{-- Filters --}}
        <form class="card grid gap-3 p-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_repeat(4,minmax(0,1fr))_auto]" role="search" @submit.prevent>
            <div class="relative sm:col-span-2 lg:col-span-1">
                <label for="filter-search" class="sr-only">Search by title</label>
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted" />
                <input id="filter-search" type="search" class="input pl-9" placeholder="Search by title" maxlength="100" x-model.debounce.350ms="filters.search">
            </div>

            <div>
                <label for="filter-status" class="sr-only">Status</label>
                <select id="filter-status" class="input" x-model="filters.status">
                    <option value="">All statuses</option>
                    @foreach ($enum(\App\Enums\RequestStatus::class) as $value)
                        <option value="{{ $value }}" x-text="$fmt.status('{{ $value }}')"></option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-priority" class="sr-only">Priority</label>
                <select id="filter-priority" class="input" x-model="filters.priority">
                    <option value="">All priorities</option>
                    @foreach ($enum(\App\Enums\RequestPriority::class) as $value)
                        <option value="{{ $value }}" x-text="$fmt.priority('{{ $value }}')"></option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-category" class="sr-only">Category</label>
                <select id="filter-category" class="input" x-model="filters.category">
                    <option value="">All categories</option>
                    @foreach ($enum(\App\Enums\RequestCategory::class) as $value)
                        <option value="{{ $value }}" x-text="$fmt.category('{{ $value }}')"></option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-sort" class="sr-only">Sort</label>
                <select id="filter-sort" class="input" x-model="filters.sort">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                    <option value="priority">Highest priority</option>
                    <option value="due_soon">Due soonest</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                @if ($all)
                    <label class="flex min-h-9 cursor-pointer items-center gap-2 text-sm whitespace-nowrap">
                        <input type="checkbox" class="size-4 accent-(--color-primary)" :checked="filters.assigned === 'me'" @change="filters.assigned = $event.target.checked ? 'me' : ''">
                        Assigned to me
                    </label>
                @endif
                <button type="button" class="btn btn-ghost btn-sm" x-show="hasFilters" x-cloak @click="clearFilters()">Clear</button>
            </div>
        </form>

        <template x-if="error">
            @include('partials.error-banner', ['retry' => 'load()'])
        </template>

        <section class="card overflow-hidden" aria-labelledby="results-title">
            <h2 id="results-title" class="sr-only">Results</h2>

            {{-- Desktop table --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Request</th>
                            <th scope="col">Status</th>
                            <th scope="col">Priority</th>
                            <th scope="col">AI risk</th>
                            <th scope="col">Assignee</th>
                            <th scope="col">Created</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody :aria-busy="loading">
                        <template x-if="loading && !rows.length">
                            <tr><td colspan="8"><div class="grid gap-2 py-2" aria-hidden="true"><div class="skeleton h-4 w-full"></div><div class="skeleton h-4 w-5/6"></div><div class="skeleton h-4 w-4/6"></div></div></td></tr>
                        </template>
                        <template x-for="row in rows" :key="row.id">
                            <tr :class="loading ? 'opacity-60' : ''">
                                <td class="mono-id" x-text="`#${row.id}`"></td>
                                <td class="max-w-sm">
                                    <a :href="`/app/requests/${row.id}`" class="block truncate font-medium hover:underline" x-text="row.title"></a>
                                    <span class="text-xs text-muted" x-text="$fmt.category(row.category) + ({{ $all ? 'true' : 'false' }} ? ` · ${row.creator?.name ?? ''}` : '')"></span>
                                </td>
                                <td><span class="pill" :class="$fmt.statusTone(row.status)" x-text="$fmt.status(row.status)"></span></td>
                                <td><span class="text-sm font-medium" :class="$fmt.priorityTone(row.priority)" x-text="$fmt.priority(row.priority)"></span></td>
                                <td>
                                    <template x-if="row.ai_analysis?.recommendation"><span class="pill" :class="$fmt.riskTone(row.ai_analysis.recommendation.risk_level)" x-text="$fmt.risk(row.ai_analysis.recommendation.risk_level)"></span></template>
                                    <template x-if="row.ai_analysis && !row.ai_analysis.recommendation"><span class="text-xs text-muted" x-text="row.ai_analysis.status === 'failed' ? 'Unavailable' : 'Analyzing…'"></span></template>
                                    <template x-if="!row.ai_analysis"><span class="text-xs text-muted">—</span></template>
                                </td>
                                <td class="text-sm whitespace-nowrap" x-text="row.assignee?.name ?? 'Unassigned'" :class="row.assignee ? '' : 'text-muted'"></td>
                                <td class="text-sm whitespace-nowrap text-muted" x-text="$fmt.date(row.created_at)"></td>
                                <td class="text-right"><a :href="`/app/requests/${row.id}`" class="btn btn-ghost btn-sm" :aria-label="`Open request ${row.id}`">Open <x-icon name="chevron-right" /></a></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <ul class="divide-y divide-line md:hidden">
                <template x-for="row in rows" :key="row.id">
                    <li>
                        <a :href="`/app/requests/${row.id}`" class="grid gap-2 p-4 active:bg-subtle">
                            <div class="flex items-start justify-between gap-3">
                                <span class="font-medium" x-text="row.title"></span>
                                <span class="mono-id" x-text="`#${row.id}`"></span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="pill" :class="$fmt.statusTone(row.status)" x-text="$fmt.status(row.status)"></span>
                                <span class="font-medium" :class="$fmt.priorityTone(row.priority)" x-text="$fmt.priority(row.priority)"></span>
                                <span class="text-muted" x-text="`${$fmt.category(row.category)} · ${$fmt.date(row.created_at)}`"></span>
                            </div>
                        </a>
                    </li>
                </template>
                <template x-if="loading && !rows.length">
                    <li class="grid gap-2 p-4" aria-hidden="true"><div class="skeleton h-4 w-3/4"></div><div class="skeleton h-3 w-1/2"></div></li>
                </template>
            </ul>

            {{-- Empty state --}}
            <template x-if="!loading && !error && !rows.length">
                <div class="grid justify-items-center gap-2 px-6 py-14 text-center">
                    <span class="grid size-10 place-items-center rounded-full bg-subtle text-muted"><x-icon name="inbox" class="size-5" /></span>
                    <p class="font-medium" x-text="hasFilters ? 'No requests match these filters' : '{{ $all ? 'No requests yet' : 'You have not created any requests' }}'"></p>
                    <p class="max-w-sm text-sm text-muted" x-text="hasFilters ? 'Try a different status or clear the filters.' : 'New requests start as drafts. Submit one to queue its AI analysis.'"></p>
                    <div class="mt-2 flex gap-2">
                        <button type="button" class="btn btn-secondary" x-show="hasFilters" @click="clearFilters()">Clear filters</button>
                        <a href="{{ route('app.requests.create') }}" class="btn btn-primary" x-show="!hasFilters">New request</a>
                    </div>
                </div>
            </template>

            {{-- Pagination --}}
            <nav class="flex items-center justify-between gap-3 border-t border-line px-4 py-3 text-sm" aria-label="Pagination" x-show="meta && meta.total > 0">
                <p class="text-muted">
                    <span x-text="meta?.from"></span>–<span x-text="meta?.to"></span> of <span class="font-medium text-ink" x-text="meta?.total"></span>
                </p>
                <div class="flex items-center gap-1">
                    <button type="button" class="btn btn-secondary btn-sm" @click="goTo(meta.current_page - 1)" :disabled="meta?.current_page <= 1" aria-label="Previous page"><x-icon name="chevron-left" /></button>
                    <span class="px-2 tabular-nums" x-text="`Page ${meta?.current_page} of ${meta?.last_page}`"></span>
                    <button type="button" class="btn btn-secondary btn-sm" @click="goTo(meta.current_page + 1)" :disabled="meta?.current_page >= meta?.last_page" aria-label="Next page"><x-icon name="chevron-right" /></button>
                </div>
            </nav>
        </section>
    </div>
</x-layouts.app>
