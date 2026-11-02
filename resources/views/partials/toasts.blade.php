<div x-data class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 p-4 sm:items-end" aria-live="polite" aria-atomic="false">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-(--radius-card) border border-line bg-surface px-4 py-3 text-sm shadow-(--shadow-pop)"
             x-transition:enter="transition duration-200" x-transition:enter-start="translate-y-2 opacity-0" x-transition:leave="transition duration-150" x-transition:leave-end="opacity-0"
             :role="toast.tone === 'danger' ? 'alert' : 'status'">
            <span class="mt-1 size-2 shrink-0 rounded-full" :class="toast.tone === 'danger' ? 'bg-danger' : 'bg-ok'" aria-hidden="true"></span>
            <p class="flex-1" x-text="toast.message"></p>
            <button type="button" class="text-muted hover:text-ink" @click="$store.toasts.dismiss(toast.id)" aria-label="Dismiss notification">
                <x-icon name="x" class="size-4" />
            </button>
        </div>
    </template>
</div>
