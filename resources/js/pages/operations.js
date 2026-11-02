import { api, describeError } from '../lib/api';
import { dayLabel } from '../lib/format';
import { currentUser, groupByDay } from './shared';

/** Each queue tab is a workflow stage with the one action that moves it forward. */
const QUEUES = [
    { status: 'submitted', label: 'Needs review', action: { key: 'review', label: 'Start review' } },
    { status: 'under_review', label: 'Awaiting decision', action: { key: 'approve', label: 'Approve' } },
    { status: 'approved', label: 'Ready to start', action: { key: 'start', label: 'Start work' } },
    { status: 'in_progress', label: 'In progress', action: { key: 'complete', label: 'Mark complete' } },
];

export function reviewQueue() {
    return {
        user: currentUser(),
        queues: QUEUES,
        active: QUEUES.some((queue) => queue.status === new URLSearchParams(window.location.search).get('queue'))
            ? new URLSearchParams(window.location.search).get('queue')
            : 'submitted',
        ticket: 0,
        counts: {},
        rows: [],
        meta: null,
        loading: true,
        error: null,
        busy: null,

        init() {
            this.$store.toasts.consumeFlash();
            this.load();
            this.$watch('active', (status) => {
                window.history.replaceState(null, '', `?queue=${status}`);
                this.load();
            });
        },

        get queue() {
            return QUEUES.find((queue) => queue.status === this.active) ?? QUEUES[0];
        },

        async load(page = 1) {
            const ticket = ++this.ticket;
            this.loading = true;
            this.error = null;

            try {
                const [list, stats] = await Promise.all([
                    api('/requests', { query: { status: this.active, sort: 'priority', per_page: 20, page } }),
                    api('/dashboard/stats'),
                ]);

                // A slower response for a previously selected tab must not replace the current rows.
                if (ticket !== this.ticket) {
                    return;
                }

                this.rows = list.data;
                this.meta = list.meta;
                this.counts = stats.data.by_status;
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

        isOwn(row) {
            return row.creator?.id === this.user.id;
        },

        async act(row, actionKey) {
            let reason = null;

            if (actionKey === 'reject' || actionKey === 'approve' || actionKey === 'complete') {
                const result = await this.$store.confirm.ask({
                    reject: { title: `Reject #${row.id}?`, message: 'Rejection is final. Tell the requester why.', confirmLabel: 'Reject', tone: 'danger', reason: 'required' },
                    approve: { title: `Approve #${row.id}?`, message: row.ai_analysis?.recommendation ? `AI suggested ${row.ai_analysis.recommendation.risk_level} risk. The decision is yours.` : 'No AI recommendation is available for this request.', confirmLabel: 'Approve', reason: 'optional' },
                    complete: { title: `Complete #${row.id}?`, message: 'Completed requests are closed and cannot be reopened.', confirmLabel: 'Complete', reason: 'optional' },
                }[actionKey]);

                if (!result.confirmed) {
                    return;
                }
                reason = result.reason;
            }

            this.busy = `${row.id}:${actionKey}`;

            try {
                await api(`/requests/${row.id}/${actionKey}`, { method: 'POST', body: reason ? { reason } : {} });
                this.$store.toasts.push(`#${row.id} updated.`);
                await this.load(this.meta?.current_page ?? 1);
            } catch (error) {
                this.$store.toasts.push(describeError(error), 'danger');
            } finally {
                this.busy = null;
            }
        },
    };
}

export function adminConsole() {
    return {
        stats: null,
        staff: [],
        activity: [],
        activityMeta: null,
        loading: true,
        loadingMore: false,
        error: null,

        async init() {
            try {
                const [stats, staff, activity] = await Promise.all([
                    api('/dashboard/stats'),
                    api('/users/assignable'),
                    api('/activity', { query: { per_page: 15 } }),
                ]);
                this.stats = stats.data;
                this.staff = staff.data;
                this.activity = activity.data;
                this.activityMeta = activity.meta;
            } catch (error) {
                this.error = describeError(error);
            } finally {
                this.loading = false;
            }
        },

        async loadMore() {
            this.loadingMore = true;

            try {
                const next = await api('/activity', { query: { per_page: 15, page: this.activityMeta.current_page + 1 } });
                this.activity.push(...next.data);
                this.activityMeta = next.meta;
            } catch (error) {
                this.$store.toasts.push(describeError(error), 'danger');
            } finally {
                this.loadingMore = false;
            }
        },

        get activityGroups() {
            return groupByDay(this.activity, dayLabel);
        },

        get aiTotal() {
            const ai = this.stats?.ai_analysis ?? {};

            return (ai.pending ?? 0) + (ai.processing ?? 0) + (ai.completed ?? 0) + (ai.failed ?? 0);
        },
    };
}
