/**
 * Shared chat error helpers.
 *
 * The chat endpoints (ChatHistoryController + ChatController) return a
 * structured `error` payload on failure:
 *
 *   { "response": "<safe generic message>",
 *     "error":    { "id": "<uuid>", "type": "<Exception class>",
 *                   "message": "<detail, only when APP_DEBUG=on>" } }
 *
 * `error.id` is a correlation id that is ALSO written to the server logs, so a
 * reported failure can be matched to the exact log line. This module turns any
 * thrown error (axios / fetch / SSE) into a safe, user-facing message plus the
 * diagnostics developers need, and keeps the raw error for the console.
 */

interface ChatServerErrorPayload {
    id?: string;
    type?: string;
    message?: string;
}

interface ChatErrorBody {
    /** Safe generic message shown in the bubble (from the chat endpoints). */
    response?: string;
    /** Structured failure detail (see above). */
    error?: ChatServerErrorPayload;
    /** Inertia/Laravel validation error body on 422. */
    message?: string;
    errors?: Record<string, string[]>;
}

interface ResolvedChatError {
    /** User-facing, safe message to show in the chat bubble. */
    message: string;
    /** Server correlation id, when the endpoint returned one. */
    reference?: string;
    /** Diagnostic detail present when APP_DEBUG is enabled. */
    detail?: string;
    /** Original thrown value, for console logging. */
    cause?: unknown;
}

const FALLBACK = 'Sorry, something went wrong. Please try again in a moment.';

/**
 * Turn any thrown error into a safe, user-facing message plus diagnostics.
 *
 * Use `resolved.message` for the UI, and log
 * `{ reference, detail, cause }` to the console for debugging.
 */
export const resolveChatError = (error: unknown): ResolvedChatError => {
    const err = error as {
        response?: { status?: number; data?: ChatErrorBody };
        message?: string;
    };

    const status = err.response?.status;
    const body = err.response?.data;
    const payload = body?.error;

    // The chat endpoints returned a structured error — prefer its safe
    // `response` text and surface the correlation id.
    if (payload || body?.response) {
        return {
            message: body?.response || FALLBACK,
            reference: payload?.id,
            detail: payload?.message,
            cause: error,
        };
    }

    // 422 — validation failed (Inertia-style body: { errors, message }).
    if (status === 422) {
        return {
            message:
                'Your message could not be sent. Please review your input and try again.',
            cause: error,
        };
    }

    // 403/404 — the conversation is gone or not yours.
    if (status === 403 || status === 404) {
        return {
            message: 'This conversation is no longer available.',
            cause: error,
        };
    }

    // 429 — throttled / rate limited.
    if (status === 429) {
        return {
            message:
                body?.message ||
                'You are sending messages too quickly. Please wait a moment and try again.',
            cause: error,
        };
    }

    // 503 — Echo disabled / maintenance.
    if (status === 503) {
        return {
            message: body?.response || 'Echo is currently unavailable.',
            cause: error,
        };
    }

    // Unknown / network / stream failure.
    return { message: FALLBACK, cause: error };
};

/**
 * Append a correlation reference to a user-facing message when one exists, so
 * the user/support can quote it to locate the matching server log line.
 */
export const withErrorReference = (
    message: string,
    reference?: string,
): string => (reference ? `${message} (Reference: ${reference})` : message);
