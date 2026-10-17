/**
 * Shared Echo chat rendering helpers.
 *
 * Extracted from the admin chat Alpine component so the markdown formatter,
 * HTML escaper, and SSE frame parser can be unit-tested with Vitest instead
 * of only existing inside a Blade-inlined Alpine factory.
 */
(function () {
    if (window.EchoChat) return;

    const escapeHtml = (raw) => String(raw ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const formatMarkdown = (raw) => {
        if (!raw) return '';
        let text = escapeHtml(raw);

        // Code blocks with copy button
        text = text.replace(/```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/g, (match, lang, code) => {
            return `<div class="my-3 rounded-xl border border-zinc-200 bg-zinc-900 text-zinc-100 p-3 font-mono text-xs overflow-x-auto dark:border-white/10"><div class="flex justify-between items-center text-[10px] text-zinc-400 pb-2 border-b border-zinc-800"><span>${lang || 'code'}</span><button type="button" class="hover:text-white" data-code="${code}" onclick="navigator.clipboard.writeText(this.getAttribute('data-code'))">Copy</button></div><pre class="pt-2"><code>${code}</code></pre></div>`;
        });

        // Inline code
        text = text.replace(/`([^`]+)`/g, '<code class="rounded bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 font-mono text-xs text-amber-700 dark:text-amber-400">$1</code>');

        // Headers
        text = text.replace(/^### (.*$)/gim, '<h4 class="text-xs font-bold text-zinc-900 dark:text-white mt-3 mb-1">$1</h4>');
        text = text.replace(/^## (.*$)/gim, '<h3 class="text-sm font-bold text-zinc-900 dark:text-white mt-4 mb-1.5">$1</h3>');
        text = text.replace(/^# (.*$)/gim, '<h2 class="text-base font-extrabold text-zinc-900 dark:text-white mt-4 mb-2">$1</h2>');

        // Bold & Italic
        text = text.replace(/\*\*([^*]+)\*\*/g, '<strong class="font-semibold text-zinc-900 dark:text-white">$1</strong>');
        text = text.replace(/\*([^*]+)\*/g, '<em class="italic">$1</em>');

        // Blockquotes
        text = text.replace(/^\> (.*$)/gim, '<blockquote class="border-s-2 border-amber-500 ps-3 my-2 text-xs italic text-zinc-500 dark:text-zinc-400">$1</blockquote>');

        // Unordered list
        text = text.replace(/^\s*[-*]\s+(.*)$/gim, '<li class="ms-4 list-disc text-xs leading-relaxed">$1</li>');

        // Tables
        if (text.includes('|')) {
            const lines = text.split('\n');
            let inTable = false;
            let tableHtml = '';
            const outputLines = [];

            for (let i = 0; i < lines.length; i++) {
                const line = lines[i].trim();
                if (line.startsWith('|') && line.endsWith('|')) {
                    const cells = line.slice(1, -1).split('|').map((c) => c.trim());
                    if (!inTable) {
                        inTable = true;
                        tableHtml = '<div class="my-3 overflow-x-auto rounded-xl border border-zinc-200 dark:border-white/10"><table class="min-w-full text-xs divide-y divide-zinc-200 dark:divide-white/10"><thead class="bg-zinc-50 dark:bg-zinc-800"><tr>';
                        cells.forEach((c) => { tableHtml += `<th class="px-3 py-2 text-left font-semibold text-zinc-900 dark:text-white">${c}</th>`; });
                        tableHtml += '</tr></thead><tbody class="divide-y divide-zinc-200 dark:divide-white/5">';
                    } else if (line.includes('---')) {
                        // Skip separator line
                    } else {
                        tableHtml += '<tr>';
                        cells.forEach((c) => { tableHtml += `<td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">${c}</td>`; });
                        tableHtml += '</tr>';
                    }
                } else {
                    if (inTable) {
                        inTable = false;
                        tableHtml += '</tbody></table></div>';
                        outputLines.push(tableHtml);
                    }
                    outputLines.push(lines[i]);
                }
            }
            if (inTable) {
                tableHtml += '</tbody></table></div>';
                outputLines.push(tableHtml);
            }
            text = outputLines.join('\n');
        }

        // Links
        text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (match, label, url) => {
            const schemeCheck = url.replace(/[\x00-\x20]+/g, '').toLowerCase();
            if (schemeCheck.startsWith('javascript:') || schemeCheck.startsWith('data:') || schemeCheck.startsWith('vbscript:')) {
                return label;
            }
            return `<a href="${url}" target="_blank" rel="noopener noreferrer" class="text-amber-600 underline dark:text-amber-400 hover:text-amber-700">${label}</a>`;
        });

        // Line breaks
        text = text.replace(/\n\n/g, '<div class="h-2"></div>');
        text = text.replace(/\n/g, '<br/>');

        return text;
    };

    /**
     * Incremental parser for `text/event-stream` frames. `push()` returns the
     * `data:` payloads completed by the received chunk; multi-line data is
     * joined with newlines, comments/other fields are ignored, and CRLF
     * streams are normalized to LF.
     */
    const createSseParser = () => {
        let buffer = '';

        return {
            push(chunk) {
                buffer += String(chunk ?? '').replace(/\r\n/g, '\n');

                const payloads = [];
                let boundary;

                while ((boundary = buffer.indexOf('\n\n')) !== -1) {
                    const frame = buffer.slice(0, boundary);
                    buffer = buffer.slice(boundary + 2);

                    const dataLines = frame
                        .split('\n')
                        .filter((line) => line.startsWith('data:'))
                        .map((line) => line.replace(/^data:\s?/, ''));

                    if (dataLines.length > 0) {
                        payloads.push(dataLines.join('\n'));
                    }
                }

                return payloads;
            },
            reset() {
                buffer = '';
            },
        };
    };

    window.EchoChat = { escapeHtml, formatMarkdown, createSseParser };
})();
