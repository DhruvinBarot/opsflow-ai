<div class="flex items-start gap-3 rounded-(--radius-card) border border-danger/30 bg-danger-soft p-4 text-sm text-danger" role="alert">
    <x-icon name="alert" class="mt-0.5" />
    <p class="flex-1" x-text="{{ $message ?? 'error' }}"></p>
    @isset($retry)
        <button type="button" class="btn btn-secondary btn-sm" @click="{{ $retry }}"><x-icon name="refresh" /> Retry</button>
    @endisset
</div>
