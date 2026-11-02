/**
 * App-wide Alpine stores: toasts, a promise-based confirm dialog, and the theme.
 */
const FLASH_KEY = 'opsflow.flash';

export function registerStores(Alpine) {
    Alpine.store('toasts', {
        items: [],
        nextId: 1,

        push(message, tone = 'ok') {
            const id = this.nextId++;
            this.items.push({ id, message, tone });
            setTimeout(() => this.dismiss(id), tone === 'danger' ? 8000 : 4500);
        },

        dismiss(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },

        /** Show a toast on the next page, e.g. after a redirect. */
        flash(message, tone = 'ok') {
            try {
                sessionStorage.setItem(FLASH_KEY, JSON.stringify({ message, tone }));
            } catch {
                // Storage unavailable: the redirect target still renders correctly without the toast.
            }
        },

        consumeFlash() {
            try {
                const flash = JSON.parse(sessionStorage.getItem(FLASH_KEY) ?? 'null');
                sessionStorage.removeItem(FLASH_KEY);
                if (flash) {
                    this.push(flash.message, flash.tone);
                }
            } catch {
                // Ignore malformed or unavailable storage.
            }
        },
    });

    /*
     * $store.confirm.ask({ title, message, confirmLabel, tone, reason: 'required' | 'optional' | null })
     * resolves to { confirmed, reason }. Rendered once in the app layout with a native <dialog>,
     * which gives focus trapping and Escape-to-close for free.
     */
    Alpine.store('confirm', {
        options: {},
        reason: '',
        error: '',
        resolver: null,

        ask(options) {
            this.options = { confirmLabel: 'Confirm', tone: 'primary', reason: null, ...options };
            this.reason = '';
            this.error = '';
            document.getElementById('confirm-dialog')?.showModal();

            return new Promise((resolve) => {
                this.resolver = resolve;
            });
        },

        accept() {
            if (this.options.reason === 'required' && this.reason.trim().length < 3) {
                this.error = 'Add a reason of at least 3 characters.';
                return;
            }

            this.close(true);
        },

        close(confirmed = false) {
            document.getElementById('confirm-dialog')?.close();
            this.resolver?.({ confirmed, reason: this.reason.trim() || null });
            this.resolver = null;
        },
    });

    Alpine.store('theme', {
        dark: document.documentElement.classList.contains('dark'),

        toggle() {
            this.dark = !this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            try {
                localStorage.setItem('opsflow.theme', this.dark ? 'dark' : 'light');
            } catch {
                // Theme still applies for this page view.
            }
        },
    });
}
