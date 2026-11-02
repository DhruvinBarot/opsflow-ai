/** Display labels and semantic tones for the API's enum values. */
export const STATUS = {
    draft: { label: 'Draft', tone: 'tone-neutral' },
    submitted: { label: 'Submitted', tone: 'tone-info' },
    under_review: { label: 'Under review', tone: 'tone-warn' },
    approved: { label: 'Approved', tone: 'tone-ok' },
    rejected: { label: 'Rejected', tone: 'tone-danger' },
    in_progress: { label: 'In progress', tone: 'tone-info' },
    completed: { label: 'Completed', tone: 'tone-ok' },
};

export const PRIORITY = {
    low: { label: 'Low', tone: 'text-muted' },
    medium: { label: 'Medium', tone: 'text-ink-soft' },
    high: { label: 'High', tone: 'text-warn' },
    critical: { label: 'Critical', tone: 'text-danger' },
};

export const RISK = {
    low: { label: 'Low', tone: 'tone-ok' },
    medium: { label: 'Medium', tone: 'tone-warn' },
    high: { label: 'High', tone: 'tone-danger' },
    critical: { label: 'Critical', tone: 'tone-danger' },
};

export const CATEGORY = {
    access: 'Access',
    infrastructure: 'Infrastructure',
    customer_escalation: 'Customer escalation',
    data: 'Data',
    compliance: 'Compliance',
    billing: 'Billing',
    incident: 'Incident',
    general: 'General',
};

export const TEAM = {
    platform: 'Platform',
    security: 'Security',
    identity: 'Identity',
    data: 'Data',
    compliance: 'Compliance',
    finance: 'Finance',
    customer_success: 'Customer success',
    operations: 'Operations',
};

export const ACTION = {
    request_created: 'created the request',
    request_updated: 'edited the request',
    request_submitted: 'submitted the request',
    request_assigned: 'assigned the request',
    request_approved: 'approved the request',
    request_rejected: 'rejected the request',
    status_changed: 'changed the status',
    request_completed: 'completed the request',
    comment_added: 'commented',
    ai_analysis_completed: 'completed AI analysis',
    ai_analysis_failed: 'could not complete AI analysis',
};

/** The main lifecycle path, used by the workflow stepper. */
export const LIFECYCLE = ['draft', 'submitted', 'under_review', 'approved', 'in_progress', 'completed'];

const dateFormatter = new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
const dateTimeFormatter = new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
const relativeFormatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

export function formatDate(iso) {
    return iso ? dateFormatter.format(new Date(iso)) : '—';
}

export function formatDateTime(iso) {
    return iso ? dateTimeFormatter.format(new Date(iso)) : '—';
}

export function timeAgo(iso) {
    if (!iso) {
        return '—';
    }

    const seconds = Math.round((new Date(iso) - Date.now()) / 1000);
    const units = [['day', 86400], ['hour', 3600], ['minute', 60]];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relativeFormatter.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
}

/** "Today", "Yesterday" or a date, for grouping timelines by day. */
export function dayLabel(iso) {
    const date = new Date(iso);
    const today = new Date();
    const yesterday = new Date(Date.now() - 86400000);

    if (date.toDateString() === today.toDateString()) {
        return 'Today';
    }

    if (date.toDateString() === yesterday.toDateString()) {
        return 'Yesterday';
    }

    return formatDate(iso);
}

export function initials(name = '') {
    return name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0].toUpperCase()).join('');
}

/** Helpers exposed to every Alpine template as $fmt. */
export const fmt = {
    STATUS, PRIORITY, RISK, CATEGORY, TEAM, ACTION, LIFECYCLE,
    status: (value) => STATUS[value]?.label ?? value,
    statusTone: (value) => STATUS[value]?.tone ?? 'tone-neutral',
    priority: (value) => PRIORITY[value]?.label ?? value,
    priorityTone: (value) => PRIORITY[value]?.tone ?? '',
    risk: (value) => RISK[value]?.label ?? value,
    riskTone: (value) => RISK[value]?.tone ?? 'tone-neutral',
    category: (value) => CATEGORY[value] ?? value,
    team: (value) => TEAM[value] ?? value,
    action: (value) => ACTION[value] ?? value.replaceAll('_', ' '),
    date: formatDate,
    dateTime: formatDateTime,
    ago: timeAgo,
    day: dayLabel,
    initials,
};
