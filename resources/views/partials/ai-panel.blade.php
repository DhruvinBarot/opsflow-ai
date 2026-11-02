{{--
    The AI recommendation. Visually separated (teal tint, "Advisory" label) from
    the request's own fields so reviewers never mistake a suggestion for a decision.
    Expects requestDetail() scope: analysis, analysisMissing, request, pollGaveUp.
--}}
<section class="overflow-hidden rounded-(--radius-card) border border-ai-line bg-ai-soft" aria-labelledby="ai-title" aria-live="polite">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ai-line px-4 py-3">
        <h2 id="ai-title" class="flex items-center gap-2 text-sm font-semibold text-ink">
            <x-icon name="sparkles" class="text-ai" /> AI recommendation
        </h2>
        <span class="eyebrow rounded-full border border-ai-line px-2 py-0.5 text-ai">Advisory · a person decides</span>
    </div>

    {{-- Not submitted yet --}}
    <template x-if="analysisMissing">
        <p class="p-4 text-sm text-muted">Analysis starts automatically when this request is submitted.</p>
    </template>

    {{-- Queued or running --}}
    <template x-if="analysis && ['pending', 'processing'].includes(analysis.status)">
        <div class="grid gap-3 p-4">
            <p class="flex items-center gap-2 text-sm text-ink-soft">
                <span class="relative flex size-2.5" aria-hidden="true">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-ai opacity-60 motion-reduce:hidden"></span>
                    <span class="relative inline-flex size-2.5 rounded-full bg-ai"></span>
                </span>
                <span x-text="analysis.status === 'pending' ? 'Queued for analysis on the background worker…' : 'Analyzing request…'"></span>
            </p>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4" aria-hidden="true">
                <div class="skeleton h-12 bg-surface"></div><div class="skeleton h-12 bg-surface"></div><div class="skeleton h-12 bg-surface"></div><div class="skeleton h-12 bg-surface"></div>
            </div>
            <template x-if="pollGaveUp">
                <p class="text-[13px] text-muted">Still waiting. The queue worker may be stopped. <button type="button" class="font-medium text-primary-ink underline" @click="retryPolling()">Check again</button></p>
            </template>
        </div>
    </template>

    {{-- Completed --}}
    <template x-if="analysis?.status === 'completed' && analysis.recommendation">
        <div class="grid gap-4 p-4">
            <p class="text-[15px] leading-6 text-ink" x-text="analysis.recommendation.summary"></p>

            <dl class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded-(--radius-control) border border-line bg-surface px-3 py-2">
                    <dt class="text-xs text-muted">Category</dt>
                    <dd class="mt-0.5 text-sm font-semibold" x-text="$fmt.category(analysis.recommendation.category)"></dd>
                    <dd class="text-[11px] text-muted" x-show="analysis.recommendation.category !== request.category" x-text="`Requester chose ${$fmt.category(request.category)}`"></dd>
                </div>
                <div class="rounded-(--radius-control) border border-line bg-surface px-3 py-2">
                    <dt class="text-xs text-muted">Suggested priority</dt>
                    <dd class="mt-0.5 text-sm font-semibold" :class="$fmt.priorityTone(analysis.recommendation.priority)" x-text="$fmt.priority(analysis.recommendation.priority)"></dd>
                    <dd class="text-[11px] text-muted" x-show="analysis.recommendation.priority !== request.priority" x-text="`Currently ${$fmt.priority(request.priority)}`"></dd>
                </div>
                <div class="rounded-(--radius-control) border border-line bg-surface px-3 py-2">
                    <dt class="text-xs text-muted">Risk level</dt>
                    <dd class="mt-1"><span class="pill" :class="$fmt.riskTone(analysis.recommendation.risk_level)" x-text="$fmt.risk(analysis.recommendation.risk_level)"></span></dd>
                </div>
                <div class="rounded-(--radius-control) border border-line bg-surface px-3 py-2">
                    <dt class="text-xs text-muted">Suggested team</dt>
                    <dd class="mt-0.5 text-sm font-semibold" x-text="$fmt.team(analysis.recommendation.suggested_team)"></dd>
                </div>
            </dl>

            <div class="rounded-(--radius-control) border-l-3 border-ai bg-surface px-3 py-2.5">
                <p class="eyebrow text-ai">Suggested next action</p>
                <p class="mt-1 text-sm text-ink" x-text="analysis.recommendation.suggested_action"></p>
            </div>

            <p class="flex flex-wrap gap-x-3 gap-y-1 font-mono text-[11px] text-muted">
                <span x-text="`provider: ${analysis.provider}`"></span>
                <template x-if="analysis.fallback_reason"><span x-text="`fallback: ${analysis.fallback_reason.replaceAll('_', ' ')}`"></span></template>
                <span x-text="`analyzed ${$fmt.dateTime(analysis.analyzed_at)}`"></span>
                <span x-text="`attempts: ${analysis.attempts}`"></span>            </p>
        </div>
    </template>

    {{-- Failed --}}
    <template x-if="analysis?.status === 'failed'">
        <div class="flex items-start gap-3 p-4 text-sm">
            <x-icon name="alert" class="mt-0.5 text-warn" />
            <div>
                <p class="font-medium text-ink" x-text="analysis.error?.message"></p>
                <p class="mt-1 font-mono text-[11px] text-muted" x-text="`code: ${analysis.error?.code} · attempts: ${analysis.attempts}`"></p>
            </div>
        </div>
    </template>
</section>
