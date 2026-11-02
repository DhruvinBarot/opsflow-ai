import { api, ApiError, describeError } from '../lib/api';
import { dayLabel, LIFECYCLE } from '../lib/format';
import { currentUser, groupByDay, isOperations } from './shared';

const POLL_INTERVAL_MS = 3000;
const POLL_LIMIT = 40;

/**
 * Workflow actions the UI offers. Visibility is a convenience only: every endpoint
 * re-checks the policy and the state machine, and a refusal is shown as a toast.
 */
const ACTIONS = {
    submit: { to: 'submitted', label: 'Submit for review', who: 'owner', confirm: { title: 'Submit this request?', message: 'You will not be able to edit it after submission. AI analysis starts right away.', confirmLabel: 'Submit' } },
    review: { to: 'under_review', label: 'Start review', who: 'reviewer' },
    approve: { to: 'approved', label: 'Approve', who: 'reviewer', confirm: { title: 'Approve this request?', message: 'The request moves to Approved and can then be started.', confirmLabel: 'Approve', reason: 'optional' } },
    reject: { to: 'rejected', label: 'Reject', who: 'reviewer', tone: 'danger', confirm: { title: 'Reject this request?', message: 'Rejection is final. Tell the requester why.', confirmLabel: 'Reject', tone: 'danger', reason: 'required' } },
    start: { to: 'in_progress', label: 'Start work', who: 'ops' },
    complete: { to: 'completed', label: 'Mark complete', who: 'ops', confirm: { title: 'Mark as complete?', message: 'Completed requests are closed and cannot be reopened.', confirmLabel: 'Complete', reason: 'optional' } },
};

export function requestDetail(requestId) {
    return {
        id: requestId,
        user: currentUser(),
        ops: isOperations(),
        request: null,
        history: [],
        analysis: null,
        analysisMissing: false,
        comments: [],
        activity: [],
        staff: [],
        loading: true,
        loadError: null,
        busy: null,
        pollCount: 0,
        pollTimer: null,
        comment: { body: '', error: null, saving: false },
        assign: { assignee_id: '', note: '', error: null, saving: false },

        async init() {
            this.$store.toasts.consumeFlash();

            try {
                await Promise.all([this.loadRequest(), this.loadHistory(), this.loadAnalysis(), this.loadComments(), this.loadActivity()]);
            } catch (error) {
                this.loadError = describeError(error);
            } finally {
                this.loading = false;
            }
        },

        destroy() {
            clearTimeout(this.pollTimer);
        },

        async loadRequest() {
            this.request = (await api(`/requests/${this.id}`)).data;
        },

        async loadHistory() {
            this.history = (await api(`/requests/${this.id}/history`)).data;
        },

        async loadComments() {
            this.comments = (await api(`/requests/${this.id}/comments`)).data;
        },

        async loadActivity() {
            if (this.ops) {
                this.activity = (await api(`/requests/${this.id}/activity`)).data;
            }
        },

        async loadAnalysis() {
            try {
                this.analysis = (await api(`/requests/${this.id}/ai-analysis`)).data;
                this.analysisMissing = false;
            } catch (error) {
                if (error instanceof ApiError && error.status === 404) {
                    this.analysis = null;
                    this.analysisMissing = true;
                    return;
                }
                throw error;
            }

            this.schedulePoll();
        },

        /** Poll while the worker has the job; the queue usually finishes within seconds. */
        schedulePoll() {
            clearTimeout(this.pollTimer);

            if (!['pending', 'processing'].includes(this.analysis?.status) || this.pollCount >= POLL_LIMIT) {
                return;
            }

            this.pollTimer = setTimeout(async () => {
                this.pollCount++;
                try {
                    await this.loadAnalysis();
                    if (this.analysis?.status === 'completed' && this.ops) {
                        await this.loadActivity();
                    }
                } catch {
                    // A transient failure (network blip, 429) is not worth a banner: keep polling within the limit.
                    this.schedulePoll();
                }
            }, POLL_INTERVAL_MS);
        },

        get pollGaveUp() {
            return this.pollCount >= POLL_LIMIT && ['pending', 'processing'].includes(this.analysis?.status);
        },

        retryPolling() {
            this.pollCount = 0;
            this.loadAnalysis();
        },

        get isOwner() {
            return this.request?.creator?.id === this.user.id;
        },

        get canAssign() {
            return this.ops && this.request && !['draft', 'rejected', 'completed'].includes(this.request.status);
        },

        get availableActions() {
            if (!this.request) {
                return [];
            }

            return Object.entries(ACTIONS)
                .filter(([, action]) => this.request.allowed_transitions.includes(action.to))
                .filter(([, action]) => ({
                    owner: this.isOwner,
                    reviewer: this.ops && !this.isOwner,
                    ops: this.ops,
                })[action.who])
                .map(([key, action]) => ({ key, ...action }));
        },

        /** Stepper state for the main lifecycle; a rejected request stops at review. */
        get steps() {
            const status = this.request?.status;
            const rejected = status === 'rejected';
            const currentIndex = rejected ? LIFECYCLE.indexOf('under_review') : LIFECYCLE.indexOf(status);

            return LIFECYCLE.map((step, index) => ({
                step,
                state: rejected && index > currentIndex ? 'skipped' : index < currentIndex ? 'done' : index === currentIndex ? (rejected ? 'rejected' : 'current') : 'upcoming',
            }));
        },

        get activityGroups() {
            return groupByDay(this.activity, dayLabel);
        },

        async run(action) {
            let reason = null;

            if (action.confirm) {
                const result = await this.$store.confirm.ask(action.confirm);
                if (!result.confirmed) {
                    return;
                }
                reason = result.reason;
            }

            this.busy = action.key;

            try {
                await api(`/requests/${this.id}/${action.key}`, { method: 'POST', body: reason ? { reason } : {} });
                this.$store.toasts.push(`${action.label} done.`);
                await Promise.all([this.loadRequest(), this.loadHistory(), this.loadActivity()]);

                if (action.key === 'submit') {
                    this.pollCount = 0;
                    await this.loadAnalysis();
                }
            } catch (error) {
                this.$store.toasts.push(describeError(error), 'danger');
                await this.loadRequest().catch(() => {});
            } finally {
                this.busy = null;
            }
        },

        async changePriority(priority) {
            const previous = this.request.priority;
            this.request.priority = priority;

            try {
                await api(`/requests/${this.id}`, { method: 'PATCH', body: { priority } });
                this.$store.toasts.push('Priority updated.');
                await this.loadActivity();
            } catch (error) {
                this.request.priority = previous;
                this.$store.toasts.push(describeError(error), 'danger');
            }
        },

        async openAssign() {
            this.assign = { assignee_id: this.request.assignee?.id ?? '', note: '', error: null, saving: false };
            document.getElementById('assign-dialog').showModal();

            if (!this.staff.length) {
                try {
                    this.staff = (await api('/users/assignable')).data;
                } catch (error) {
                    this.assign.error = describeError(error);
                }
            }
        },

        async submitAssign() {
            this.assign.saving = true;
            this.assign.error = null;

            try {
                await api(`/requests/${this.id}/assign`, { method: 'POST', body: { assignee_id: Number(this.assign.assignee_id), note: this.assign.note || null } });
                document.getElementById('assign-dialog').close();
                this.$store.toasts.push('Request assigned.');
                await Promise.all([this.loadRequest(), this.loadActivity()]);
            } catch (error) {
                this.assign.error = error.field?.('assignee_id') ?? error.field?.('note') ?? describeError(error);
            } finally {
                this.assign.saving = false;
            }
        },

        async addComment() {
            this.comment.saving = true;
            this.comment.error = null;

            try {
                const { data } = await api(`/requests/${this.id}/comments`, { method: 'POST', body: { body: this.comment.body } });
                this.comments.push(data);
                this.comment.body = '';
                await this.loadActivity();
            } catch (error) {
                this.comment.error = error.field?.('body') ?? describeError(error);
            } finally {
                this.comment.saving = false;
            }
        },
    };
}
