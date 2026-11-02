import { api, describeError } from '../lib/api';
import { currentUser, isOperations, toLocalInput } from './shared';

const FILTER_KEYS = ['search', 'status', 'priority', 'category', 'assigned', 'sort', 'page'];

/**
 * Request table for "My requests" (scope=mine) and "All requests" (scope=all).
 * Filters are mirrored into the URL so a filtered view can be bookmarked or shared.
 */
export function requestList(scope) {
    const params = new URLSearchParams(window.location.search);

    return {
        scope,
        ops: isOperations(),
        filters: Object.fromEntries(FILTER_KEYS.map((key) => [key, params.get(key) ?? (key === 'sort' ? 'newest' : key === 'page' ? 1 : '')])),
        rows: [],
        meta: null,
        loading: true,
        error: null,

        ticket: 0,
        lastQuery: null,

        init() {
            this.lastQuery = this.querySignature();
            this.load();

            this.$watch('filters', () => {
                const signature = this.querySignature();

                // A changed filter restarts at page 1; that page change re-triggers this watcher once.
                if (signature !== this.lastQuery) {
                    this.lastQuery = signature;
                    if (Number(this.filters.page) !== 1) {
                        this.filters.page = 1;
                        return;
                    }
                }

                this.load();
            }, { deep: true });
        },

        querySignature() {
            const { page, ...rest } = this.filters;

            return JSON.stringify(rest);
        },

        clearFilters() {
            Object.assign(this.filters, { search: '', status: '', priority: '', category: '', assigned: '', sort: 'newest', page: 1 });
        },

        get hasFilters() {
            return ['search', 'status', 'priority', 'category', 'assigned'].some((key) => this.filters[key]);
        },

        async load() {
            const ticket = ++this.ticket;
            this.loading = true;
            this.error = null;
            this.syncUrl();

            try {
                const response = await api('/requests', {
                    query: {
                        search: this.filters.search,
                        status: this.filters.status,
                        priority: this.filters.priority,
                        category: this.filters.category,
                        sort: this.filters.sort,
                        page: this.filters.page,
                        per_page: 15,
                        created_by: this.scope === 'mine' ? currentUser().id : '',
                        assigned_to: this.filters.assigned === 'me' ? currentUser().id : '',
                    },
                });

                // Ignore responses that arrive after a newer filter change.
                if (ticket !== this.ticket) {
                    return;
                }

                this.rows = response.data;
                this.meta = response.meta;
            } catch (error) {
                if (ticket === this.ticket) {
                    this.error = describeError(error);
                }
            } finally {
                if (ticket === this.ticket) {
                    this.loading = false;
                }
            }
        },

        goTo(page) {
            if (page >= 1 && page <= (this.meta?.last_page ?? 1)) {
                this.filters.page = page;
            }
        },

        syncUrl() {
            const query = new URLSearchParams();
            Object.entries(this.filters).forEach(([key, value]) => {
                const isDefault = (key === 'sort' && value === 'newest') || (key === 'page' && Number(value) === 1);
                if (value && !isDefault) {
                    query.set(key, value);
                }
            });
            const search = query.toString();
            window.history.replaceState(null, '', search ? `?${search}` : window.location.pathname);
        },
    };
}

/** Create and edit form. Field rules mirror the server's Form Requests; the server stays authoritative. */
export function requestForm(requestId = null) {
    return {
        requestId,
        form: { title: '', description: '', category: '', priority: 'medium', due_at: '' },
        errors: {},
        loading: Boolean(requestId),
        saving: false,
        loadError: null,

        async init() {
            if (!this.requestId) {
                return;
            }

            try {
                const { data } = await api(`/requests/${this.requestId}`);
                this.form = {
                    title: data.title,
                    description: data.description,
                    category: data.category,
                    priority: data.priority,
                    due_at: toLocalInput(data.due_at),
                };
            } catch (error) {
                this.loadError = describeError(error);
            } finally {
                this.loading = false;
            }
        },

        error(field) {
            return this.errors[field]?.[0] ?? null;
        },

        async save(andSubmit = false) {
            this.saving = true;
            this.errors = {};

            const payload = { ...this.form, due_at: this.form.due_at ? new Date(this.form.due_at).toISOString() : null };

            try {
                const { data } = this.requestId
                    ? await api(`/requests/${this.requestId}`, { method: 'PATCH', body: payload })
                    : await api('/requests', { method: 'POST', body: payload });

                if (andSubmit) {
                    await api(`/requests/${data.id}/submit`, { method: 'POST' });
                }

                this.$store.toasts.flash(andSubmit ? 'Request submitted. AI analysis has been queued.' : 'Draft saved.');
                window.location.assign(`/app/requests/${data.id}`);
            } catch (error) {
                this.errors = error.errors ?? {};
                if (!Object.keys(this.errors).length) {
                    this.$store.toasts.push(describeError(error), 'danger');
                } else {
                    this.$nextTick(() => this.$root.querySelector('[aria-invalid="true"]')?.focus());
                }
                this.saving = false;
            }
        },
    };
}
