<dialog id="confirm-dialog" x-data class="m-auto w-[min(28rem,calc(100vw-2rem))] rounded-(--radius-card) border border-line bg-surface p-0 text-ink shadow-(--shadow-pop) backdrop:bg-ink/40"
        aria-labelledby="confirm-title" @close="$store.confirm.resolver && $store.confirm.close(false)">
    <form method="dialog" class="grid gap-4 p-5" @submit.prevent="$store.confirm.accept()">
        <div>
            <h2 id="confirm-title" class="text-base font-semibold" x-text="$store.confirm.options.title"></h2>
            <p class="mt-1 text-sm text-muted" x-text="$store.confirm.options.message"></p>
        </div>

        <template x-if="$store.confirm.options.reason">
            <div>
                <label for="confirm-reason" class="field-label">
                    Reason <span class="font-normal text-muted" x-show="$store.confirm.options.reason === 'optional'">(optional)</span>
                </label>
                <textarea id="confirm-reason" rows="3" class="input" maxlength="1000" x-model="$store.confirm.reason"
                          :aria-invalid="$store.confirm.error ? 'true' : 'false'" aria-describedby="confirm-reason-error" x-init="$nextTick(() => $el.focus())"></textarea>
                <p id="confirm-reason-error" class="field-error" x-show="$store.confirm.error" x-text="$store.confirm.error"></p>
            </div>
        </template>

        <div class="flex justify-end gap-2">
            <button type="button" class="btn btn-secondary" @click="$store.confirm.close(false)">Cancel</button>
            <button type="submit" class="btn" :class="$store.confirm.options.tone === 'danger' ? 'btn-danger' : 'btn-primary'" x-text="$store.confirm.options.confirmLabel"></button>
        </div>
    </form>
</dialog>
