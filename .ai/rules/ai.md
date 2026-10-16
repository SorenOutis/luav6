---
paths:
  - 'app/Ai/**'
---

# Ai

## New admin AI tool = 5 touch points; provider test asserts exact tool list
Adding a tool to the admin assistant requires: (1) tool class, (2) `AdminAssistantAgent::tools()` AND a matching instruction line, (3) write tools only: `PendingAiActionService::ALLOWED_TYPES`, (4) write tools only: `AiActionExecutor` dispatch match + `prepareX()` handler. (5) `OpenAiCompatibleProviderTest` asserts an EXACT ORDERED list of tool names plus that instructions mention every name — update it or tests fail. Staging an unknown action_type is rejected by ALLOWED_TYPES.

## Student lookups in AI tools are workspace-scoped with enrollment guards
Student lookups in admin tools are workspace-scoped: create_exam blocked-student name search only matches users who are workspace members (hint/section/current_workspace) and not admins; record_grade requires the student to be section-enrolled and re-checks enrollment inside AiActionExecutor::prepareRecordGrade (throws PendingAiActionException if they were unenrolled between staging and approval). Fallback to "first section in workspace" was removed. Tests must attach students via `$section->users()->attach($student->id)` or tools legitimately refuse.

## Bare-name student lookups must force the students tool call
Root cause found for "student search cannot find partial name" reports: when the admin's chat message is ONLY a name (e.g. "angeline", "cabrera", "angeline cabrera"), the LLM replies conversationally and never calls the `students` tool — no search happens at all (verified with a live probe). The fix is instruction-driven: AdminAssistantAgent rule 7 + the students tool line must keep the bare-name sentences; tests assert the exact phrases 'is a student search request' and 'call this tool immediately with only the name as `search`'.
StudentsTool search is tokenized on whitespace/commas with AND across tokens, each token matching name/first/middle/last/email, quotes stripped — keep that so full names work in any word order ("cabrera angeline") and middle names match.
