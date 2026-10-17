---
paths:
  - 'resources/views/filament/pages/**'
  - 'resources/views/filament/pages/ai-chat*.blade.php'
---

# Pages

## Admin Echo AI chat is Blade + Alpine, not Inertia/Vue
The admin Echo AI chat (`ai-chat.blade.php` + `ai-chat-script.blade.php` + `ai-chat-style.blade.php`) is a Filament page built with Blade + Alpine.js. It bypasses Filament chrome via `$layout = 'filament-panels::components.layout.base'`. Do not port the Inertia/Vue student-chat components (`resources/js/components/ChatAiOrb.vue`, `Chats.vue`, etc.) into it — they are a separate stack. Backend endpoints live in `routes/web.php` under `api/chat*` / `api/chats*` / `api/ai-actions*`.

## Echo AI starter prompts prefill the composer, never send immediately
Settled UX decision: clicking a prompt starter must populate + focus the composer (with an "Enter to send" hint), never call sendMessage() directly — the page's premise is explicit confirmation before anything runs. Also: copy on this page uses plain admin language, not engineer jargon ("0 tokens", "Deterministic Guard Passed", "agent/db/audit" are banned).

## Echo AI chat page structure conventions
Session lists render via the `ai-chat-session-list` partial (real button rows, `$source` parameter) — edit the partial, don't re-inline sidebar markup. Welcome starter cards are server-rendered from the `$promptStarters` array in `ai-chat.blade.php` (heroicons via `x-dynamic-component`); there is no `filteredPromptStarters` JS getter. Starter clicks must call `prefillPrompt()` (prefill composer, never auto-send). The 8 `gray-on-color` warnings the impeccable detector reports on the mic button are false positives (Alpine `:class` ternaries) — do not "fix" them.

## Echo chat JS helpers live in window.EchoChat
Markdown rendering (formatMarkdown/escapeHtml) and SSE frame parsing (createSseParser) live in public/js/ai-chat-format.js as window.EchoChat so they are vitest-covered (tests/js/ai-chat-format.test.ts). The Alpine component must keep the formatMarkdown(msg.content) delegate and the handleToolCallEvent/handleToolResultEvent/sendAdjustment method names because templates and AdminAiChatTest assert them. ai-chat.blade.php loads the helper script before the ai-chat-script include.
