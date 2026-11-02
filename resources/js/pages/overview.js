import { api, describeError } from '../lib/api';
import { STATUS, dayLabel } from '../lib/format';
import { currentUser, groupByDay, isOperations } from './shared';

export function overview() {
    return {
        user: currentUser(),
        ops: isOperations(),
        stats: null,
        recent: [],
        activity: [],
        loading: true,
        error: null,

        async init() {
            this.loading = true;
            this.error = null;

            try {
                const [stats, recent, activity] = await Promise.all([
                    api('/dashboard/stats'),
                    api('/requests', { query: { per_page: 6, created_by: this.ops ? '' : this.user.id } }),
                    this.ops ? api('/activity', { query: { per_page: 8 } }) : Promise.resolve({ data: [] }),
                ]);

                this.stats = stats.data;
                this.recent = recent.data;
                this.activity = activity.data;
            } catch (error) {
                this.error = describeError(error);
            } finally {
                this.loading = false;
            }
        },

        get statusBars() {
            const byStatus = this.stats?.by_status ?? {};
            const max = Math.max(1, ...Object.values(byStatus));

            return Object.keys(STATUS).map((key) => ({
                key,
                count: byStatus[key] ?? 0,
                width: `${Math.round(((byStatus[key] ?? 0) / max) * 100)}%`,
            }));
        },

        get activityGroups() {
            return groupByDay(this.activity, dayLabel);
        },
    };
}
