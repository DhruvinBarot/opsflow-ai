/** The signed-in user, rendered into the layout by the server. */
export function currentUser() {
    if (!window.__opsflowUser) {
        const node = document.getElementById('opsflow-user');
        window.__opsflowUser = node ? JSON.parse(node.textContent) : null;
    }

    return window.__opsflowUser;
}

export function isOperations() {
    return ['manager', 'admin'].includes(currentUser()?.role);
}

/** Converts an ISO timestamp to the value a datetime-local input expects (local time). */
export function toLocalInput(iso) {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const offset = date.getTimezoneOffset() * 60000;

    return new Date(date - offset).toISOString().slice(0, 16);
}

/** Groups audit events into [{ label, events }] by day, newest first. */
export function groupByDay(events, labelFor) {
    const groups = [];

    for (const event of events) {
        const label = labelFor(event.created_at);
        const last = groups.at(-1);

        if (last?.label === label) {
            last.events.push(event);
        } else {
            groups.push({ label, events: [event] });
        }
    }

    return groups;
}
