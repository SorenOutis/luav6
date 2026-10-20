---
paths:
  - 'app/Http/Controllers/Chat*.php'
  - app/Http/Controllers/ChatHistoryController.php
---

# Controllers

## Sanitize AI output before DB persist, not just on the wire
Both chat paths (sync prompt and SSE stream) must run ChatService::sanitizeOutput() (and sanitizeOutput(combineReasoning(events)) for thinking) before chat_messages rows are created. SSE frame sanitization alone is not enough: history endpoints re-serve stored rows raw. The sync /api/chats/{session}/messages path also persists the user turn BEFORE calling the provider so a failed reply never drops the question.

## Search uses MySQL FULLTEXT with LIKE fallback
search() matches via MATCH...AGAINST IN BOOLEAN MODE only when the driver is mysql, the term is >= 3 chars, and booleanSearchTerm() is non-empty; otherwise it falls back to escaped LIKE. Fulltext index is migration 2026_10_17_120000_add_fulltext_index_to_chat_messages_table.php (skipped on non-MySQL drivers). booleanSearchTerm() strips boolean operators and appends * to each token; keep it public for tests.
