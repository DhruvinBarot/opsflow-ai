{{--
    Audit activity grouped by day on a vertical rail. Layout ported from the 21st
    "Activity Timeline" (timeline-3) component: day separators, a 1px rail behind
    the avatars, an action badge on each avatar, and right-aligned tabular timestamps.
--}}
<div class="grid gap-5">
    <template x-for="group in {{ $groups }}" :key="group.label">
        <div class="grid gap-3">
            <div class="flex items-center gap-3" role="presentation">
                <span class="eyebrow" x-text="group.label"></span>
                <span class="h-px flex-1 bg-line"></span>
            </div>
            <ol class="relative grid gap-4">
                <span class="absolute top-3 bottom-3 left-4 w-px bg-line" aria-hidden="true"></span>
                <template x-for="event in group.events" :key="event.id">
                    <li class="relative flex items-start gap-3">
                        <span class="relative shrink-0">
                            <span class="grid size-8 place-items-center rounded-full border border-line text-[11px] font-semibold"
                                  :class="event.actor.type === 'system' ? 'bg-ai-soft text-ai' : 'bg-surface text-ink-soft'"
                                  x-text="event.actor.type === 'system' ? 'AI' : $fmt.initials(event.actor.name)" aria-hidden="true"></span>
                        </span>
                        <p class="min-w-0 flex-1 pt-1 text-sm text-muted">
                            <span class="font-medium text-ink" x-text="event.actor.type === 'system' ? 'System' : event.actor.name"></span>
                            <span x-text="$fmt.action(event.action)"></span>
                            @if ($withRequest ?? false)
                                <template x-if="event.request">
                                    <a :href="`/app/requests/${event.request.id}`" class="font-medium text-ink hover:underline" x-text="`#${event.request.id} ${event.request.title}`"></a>
                                </template>
                            @endif
                            <template x-if="event.action === 'status_changed' && event.metadata?.to"><span>to <span class="font-medium text-ink" x-text="$fmt.status(event.metadata.to)"></span></span></template>
                            <template x-if="event.metadata?.reason"><span class="mt-1 block text-[13px] italic" x-text="`“${event.metadata.reason}”`"></span></template>
                        </p>
                        <time class="shrink-0 pt-1 text-xs text-muted tabular-nums" :datetime="event.created_at" x-text="$fmt.ago(event.created_at)"></time>
                    </li>
                </template>
            </ol>
        </div>
    </template>
    <template x-if="!{{ $groups }}.length">
        <p class="py-4 text-center text-sm text-muted">No activity recorded yet.</p>
    </template>
</div>
