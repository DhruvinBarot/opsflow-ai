/**
 * Thin client for the OpsFlow REST API, authenticated by the browser session
 * (Sanctum stateful requests). The server enforces every permission; this module
 * only turns responses into data or a typed ApiError.
 */
export class ApiError extends Error {
    constructor(status, body) {
        super(body?.message || `Request failed (${status})`);
        this.status = status;
        this.code = body?.error ?? null;
        this.errors = body?.errors ?? {};
        this.body = body;
    }

    /** First validation message for a field, if any. */
    field(name) {
        return this.errors[name]?.[0] ?? null;
    }
}

function xsrfToken() {
    const match = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

export async function api(path, { method = 'GET', body, query } = {}) {
    const url = new URL(`/api${path}`, window.location.origin);

    Object.entries(query ?? {}).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            url.searchParams.set(key, value);
        }
    });

    let response;
    try {
        response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        throw new ApiError(0, { message: 'Could not reach the server. Check your connection and try again.' });
    }

    if (response.status === 204) {
        return null;
    }

    const data = await response.json().catch(() => null);

    // Session expired (401) or CSRF token stale (419): send the user back to sign in.
    if (response.status === 401 || response.status === 419) {
        window.location.assign('/login');

        // Never settle: callers should not flash an error toast while the page navigates away.
        return new Promise(() => {});
    }

    if (!response.ok) {
        throw new ApiError(response.status, data);
    }

    return data;
}

/** Human message for an error that is not tied to a form field. */
export function describeError(error) {
    if (!(error instanceof ApiError)) {
        return 'Something went wrong. Please try again.';
    }

    return {
        403: error.message && error.message !== 'This action is unauthorized.' ? error.message : 'You do not have permission to do that.',
        404: 'That item no longer exists.',
        409: error.message,
        429: 'Too many requests. Wait a moment and try again.',
    }[error.status] ?? error.message;
}
