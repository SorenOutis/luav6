export interface ServerSentEvent {
    data: string;
    event: string;
    id?: string;
}

type EventHandler = (event: ServerSentEvent) => void;

/**
 * Incremental Server-Sent Events parser.
 *
 * Browser fetch streams may split anywhere (including between CR and LF), and
 * reverse proxies / app servers are allowed to rewrite LF framing to CRLF.
 * Keeping the parser line-oriented makes it resilient to both cases, plus
 * `data:` fields without a space, multi-line data payloads, comment
 * keep-alives, and a final frame that is not terminated by a blank line.
 *
 * The previous framing loop (buffer.indexOf('\n\n')) silently swallowed the
 * whole response when a proxy used CRLF line endings — the reply never
 * appeared and the UI fell into a dead-end error bubble.
 */
export const createSseParser = (onEvent: EventHandler) => {
    let buffer = '';
    let dataLines: string[] = [];
    let eventName = 'message';
    let lastEventId: string | undefined;

    const dispatch = () => {
        if (dataLines.length === 0) {
            eventName = 'message';
            return;
        }

        onEvent({
            data: dataLines.join('\n'),
            event: eventName,
            id: lastEventId,
        });

        dataLines = [];
        eventName = 'message';
    };

    const processLine = (line: string) => {
        if (line === '') {
            dispatch();
            return;
        }

        // SSE comments are commonly used as proxy keep-alives.
        if (line.startsWith(':')) return;

        const colon = line.indexOf(':');
        const field = colon === -1 ? line : line.slice(0, colon);
        let value = colon === -1 ? '' : line.slice(colon + 1);
        if (value.startsWith(' ')) value = value.slice(1);

        if (field === 'data') {
            dataLines.push(value);
        } else if (field === 'event') {
            eventName = value || 'message';
        } else if (field === 'id' && !value.includes('\0')) {
            lastEventId = value;
        }
    };

    const drainLines = (final: boolean) => {
        let offset = 0;

        while (offset < buffer.length) {
            const lf = buffer.indexOf('\n', offset);
            const cr = buffer.indexOf('\r', offset);
            let newline = -1;

            if (lf !== -1 && cr !== -1) newline = Math.min(lf, cr);
            else newline = Math.max(lf, cr);

            if (newline === -1) break;

            // A CR at the end of a chunk may be the first half of CRLF. Wait
            // for the next chunk so its LF is not mistaken for a blank line.
            if (
                buffer[newline] === '\r' &&
                newline === buffer.length - 1 &&
                !final
            ) {
                break;
            }

            processLine(buffer.slice(offset, newline));
            offset =
                buffer[newline] === '\r' && buffer[newline + 1] === '\n'
                    ? newline + 2
                    : newline + 1;
        }

        buffer = buffer.slice(offset);
    };

    return {
        feed(chunk: string) {
            buffer += chunk;
            drainLines(false);
        },
        end() {
            drainLines(true);

            // Although the SSE specification dispatches on a blank line,
            // processing a final unterminated frame is safer when a proxy
            // closes the response immediately after the last payload.
            if (buffer.length > 0) {
                processLine(buffer);
                buffer = '';
            }
            dispatch();
        },
    };
};

/** Read and parse a fetch response body without assuming anything about chunk boundaries. */
export const readSseStream = async (
    body: ReadableStream<Uint8Array>,
    onEvent: EventHandler,
): Promise<void> => {
    const reader = body.getReader();
    const decoder = new TextDecoder();
    const parser = createSseParser(onEvent);

    try {
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            parser.feed(decoder.decode(value, { stream: true }));
        }

        parser.feed(decoder.decode());
        parser.end();
    } finally {
        reader.releaseLock();
    }
};
