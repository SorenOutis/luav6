# AI Bot Teams — Design Spec

Status: **proposed** — no code written yet.

| Decision | Choice |
| --- | --- |
| Scope | Build the *pattern* natively. **Do not** embed Grok Bot (closed product, no public API) |
| Unit of work | A named **Bot** row in the database, not a PHP class |
| Capability boundary | Tools + Skills shared per workspace; **Memory + Routines per Bot** |
| Handoff | Async, persisted (`ai_bot_messages`) — a Bot wakes and replies later |
| Writes | Never bypass the existing `PendingAiAction` approval boundary |
| Storage | New tables; **no change** to `laravel/ai` dependency |

---

## 1. Problem

luav6 has a strong single-agent assistant ("Echo") for admins
(`AdminAssistantAgent`, 75 tools, `MaxSteps(12)`) and a student assistant
(`AssistantAgent`). Both are **hardcoded PHP classes**. That gives three
limitations that the Grok Bot product exists to solve:

1. **No named teammates.** An admin cannot say "ask the Registrar bot" — there
   is exactly one assistant, with one instruction set, one tool surface, and one
   memory stream. A registrar task and a facilities task compete for the same
   context window.
2. **No delegation.** A single agent with 75 tools has to do research, drafting,
   and verification itself. It cannot hand a well-scoped subtask to a
   specialist that only carries the three tools that subtask needs.
3. **No standing responsibilities.** Work that should happen every morning
   ("prepare the weekly at-risk-student watch list") has to be typed each time.

Additionally, the current tool surface is the single largest cost driver:
every tool definition is shipped on every request. `AdminAssistantIntent`
mitigates this with keyword routing, but it is a heuristic on the *message*,
not a durable *role*.

## 2. What we are actually building

Grok Bot (xAI) is a managed product: named persistent Bots on a shared cloud
computer, per-Bot memory, Bot-to-Bot handoff, group chats, and scheduled
Routines. There is **no public API** to create Bots, provision their computers,
or manage group chats, so none of it can be embedded.

What we can do — and what this spec covers — is reimplement the *architecture*
inside luav6 using the assets already present:

| Grok Bot concept | luav6 asset today | Gap |
| --- | --- | --- |
| Named Bot with its own identity | `AdminAssistantAgent` / `AssistantAgent` (hardcoded classes) | `ai_bots` table + generic `BotAgent` |
| **Memory per Bot**, Tools per account | `ChatSession`/`ChatMessage` scoped to *user*; 75 tools; `AdminAssistantSkill` | Memory is per-session, not per-Bot |
| Bot → Bot handoff | `Laravel\Ai\Tools\AgentTool` ships with `laravel/ai` v0.9.1 | Not wired; sync-only |
| Group chat (2–6 Bots) | — | Coordinator agent + multi-participant messages |
| Routines (cron) | Scheduler + `ai` queue + 7 jobs | `ai_bot_routines` + dispatch command |
| "Returns only when it needs approval" | `PendingAiAction` + nonce + `AiActionExecutor` + `AiPolicyEngineService` | **Already there** — reuse unchanged |
| Cost governance | `AiBudgetManager`, `AiUsageTracker`, `AiCostEstimator` | Needs per-Bot attribution |
| Per-Bot "own computer" | 75 workspace-scoped, approval-gated tools | **Deliberately not replicated** |

### 2.1 Deliberate non-goals

- **No per-Bot Linux VM / shell access.** Grok Bot gives each Bot a computer.
  For a school LMS holding student PII, that is a downgrade in safety, not an
  upgrade. A Bot's capability is exactly its allowlisted tools — already
  workspace-scoped and approval-gated.
- **No rewrite of the approval pipeline.** Bots write by staging
  `PendingAiAction` records, exactly like `AdminAssistantAgent` does today.
- **No student-facing Bot creation in v1.** Bots are an admin-side surface.
- **No new Composer dependency.** `laravel/ai` v0.9.1 already ships
  `AgentTool`, `RemembersConversations`, and `ConversationStore`.

## 3. Core concepts

### 3.1 The capability / context boundary

This is the single most important rule, and it comes directly from Grok Bot's
own design notes.

> **Capabilities** (tools, skills, credentials) live at the **workspace** level,
> because many Bots need to browse, read grades, or send mail.
> **Context** (memory, routines, identity) lives at the **Bot** level, because
> that is what makes a role reusable.

Concretely:

- A Bot's `allowed_tools` is an **allowlist** drawn from the shared registry.
  Two Bots in the same workspace may have disjoint tool sets.
- A Bot's `instructions` and `memory` belong to that Bot and are never shared.
- A `ChatSession` records which Bot (or Bots) participated, so transcripts stay
  attributable.

### 3.2 Bot lifecycle

```
ai_bots row created (admin)  →  Bot appears in roster
      │
      ├─ direct chat          → BotAgent runs in a chat_session
      ├─ delegated handoff    → ai_bot_messages row → RunAiBotHandoff job → reply
      └─ routine fired        → RunAiBotRoutine job → transcript stored
```

### 3.3 Two delegation modes

| Mode | Mechanism | Use when |
| --- | --- | --- |
| **Sync** | `Laravel\Ai\Tools\AgentTool` wrapping a `BotAgent` | The caller needs the answer *in this turn* to compose its own reply |
| **Async** | `App\Ai\Tools\DelegateToBot` writes `ai_bot_messages`, a job runs the target | Work that outlives the turn (Grok Bot's model) |

Phase 2 ships both: sync for intra-turn composition, async for the "wake and
reply later" behaviour.

## 4. Schema

### 4.1 New: `ai_bots`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | pk | |
| `workspace_id` | fk → workspaces | nullable = platform Bot (super-admin only) |
| `slug` | string | stable identifier, unique per workspace; used as the delegation tool key |
| `name` | string | display name, e.g. "Registrar" |
| `title` | string nullable | role subtitle, e.g. "Owns enrollment + section records" |
| `avatar` | string nullable | emoji or image path |
| `instructions` | text | the Bot's system prompt (role, boundaries, output contract) |
| `allowed_tools` | json | list of registry keys, e.g. `["students","sections_admin","record_grade"]` |
| `model` | string nullable | per-Bot model override; null = workspace default provider/model |
| `max_steps` | unsigned int | default `8`; caps the tool-call loop per turn |
| `memory_scope` | enum-string | `session` (current chat only) or `bot` (persists across sessions) |
| `is_active` | bool | default `true` |
| `created_by` | fk → users nullable | |
| timestamps | | |

Indexes:
- unique `(workspace_id, slug)`
- index `(workspace_id, is_active)`

Uses `App\Models\Concerns\BelongsToWorkspace` — the house tenant convention.

### 4.2 New: `ai_bot_messages` (handoff log)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | pk | |
| `workspace_id` | fk → workspaces | denormalised so the tenant scope check is a single `where` |
| `from_bot_id` | fk → ai_bots | nullable for user-initiated handoffs |
| `to_bot_id` | fk → ai_bots cascade | the woken Bot |
| `chat_session_id` | fk → chat_sessions nullable | conversation the handoff belongs to |
| `parent_message_id` | fk → ai_bot_messages nullable | threads a reply to its request |
| `task` | text | the delegated instruction, verbatim |
| `result` | text nullable | the target Bot's reply |
| `status` | enum-string | `queued`, `running`, `completed`, `failed`, `rejected`, `expired` |
| `error` | text nullable | |
| `depth` | unsigned tinyint | delegation depth, default `0`; hard-capped at 2 (§6.4) |
| `estimated_cost_micros` | unsigned bigint nullable | cost attribution |
| timestamps | | |

Indexes:
- index `(workspace_id, status)`
- index `(to_bot_id, status)` — the "what is waiting for me" query
- index `(chat_session_id, id)`

### 4.3 New: `ai_bot_routines`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | pk | |
| `workspace_id` | fk → workspaces | |
| `bot_id` | fk → ai_bots cascade | |
| `name` | string | |
| `cron` | string | standard 5-field cron expression |
| `prompt` | text | the standing instruction |
| `is_active` | bool | default `true` |
| `last_run_at` | timestamp nullable | |
| `last_status` | string nullable | `completed`, `failed`, `skipped` |
| `last_error` | text nullable | |
| timestamps | | |

Index: `(bot_id, is_active)`.

### 4.4 New: `ai_bot_memories`

Distinct from chat history — this is the durable "learned" store that makes a
role reusable across sessions (Grok Bot calls this *Memory*, and keeps it
separate from transcripts).

| Column | Type | Notes |
| --- | --- | --- |
| `id` | pk | |
| `workspace_id` | fk → workspaces | |
| `bot_id` | fk → ai_bots cascade | |
| `content` | text | the fact |
| `source` | enum-string | `manual` (admin wrote it) or `approved` (Bot proposed, human approved) |
| `importance` | unsigned tinyint | 1–5; 1 = always injected, 5 = retrieved on demand |
| `created_by` | fk → users nullable | |
| timestamps | | |

Index: `(bot_id, importance)`.

**Population rule:** a Bot may never write its own memory directly. It stages a
`PendingAiAction` of type `remember_bot_memory`, and a human approves. This
mirrors the existing write-tool rule exactly.

### 4.5 Changed: `chat_sessions`

| Column | Type | Notes |
| --- | --- | --- |
| `bot_id` | fk → ai_bots nullable | the primary Bot for a solo session |
| `mode` | enum-string | `solo` (default) or `group` |

For `mode = group`, `bot_id` is null and participation is derived from
`chat_messages.bot_id`.

### 4.6 Changed: `chat_messages`

| Column | Type | Notes |
| --- | --- | --- |
| `bot_id` | fk → ai_bots nullable | author Bot; null = the human user |

### 4.7 Changed: `ai_usage_logs`

| Column | Type | Notes |
| --- | --- | --- |
| `bot_id` | fk → ai_bots nullable | per-Bot cost attribution |

### 4.8 New: `ai_bot_handoffs` — *not needed*

Early drafts proposed a separate handoff-budget table. Rejected: the existing
`ai_budget_periods` / `ai_budget_reservations` pair already models period
budgets and per-request reservations, and `ai_budget_events` already records
reasons. We add a `bot_handoff` **feature** key and a `bot_id` column rather
than a parallel budget system.

## 5. Architecture

### 5.1 Tool registry — `App\Ai\Support\ToolRegistry`

Today the tool surface is hardcoded in `AdminAssistantAgent::tools()`. A
database-driven allowlist needs a **stable key → tool class** map that is not
the agent class.

```php
final class ToolRegistry
{
    /** @var array<string, class-string<Tool>> */
    private const MAP = [
        'students' => StudentsTool::class,
        'grades_admin' => AdminGradesTool::class,
        'record_grade' => RecordGradeTool::class,
        // …
    ];

    public static function keys(): array;
    public static function classFor(string $key): ?string;
    public static function make(string $key, ?int $chatSessionId = null): ?Tool;
    public static function isWrite(string $key): bool;
}
```

`isWrite()` is derived from a parallel `WRITE_KEYS` list — the registry becomes
the single source of truth for "which tools stage approvals". Today that truth
is split between `PendingAiActionService::ALLOWED_TYPES` (43 action types) and
the agent's own conditional mounting.

**Refactor requirement:** `AdminAssistantAgent::tools()` must be rewritten to
build from the registry so the two lists cannot drift. This also fixes the
`OpenAiCompatibleProviderTest` fragility described in §9.3.

### 5.2 `App\Ai\Agents\BotAgent`

The generic, database-driven agent. One class replaces N hardcoded classes.

```php
class BotAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    public function __construct(
        public AiBot $bot,
        public ?User $actor = null,
        public ?ChatSession $session = null,
        public array $history = [],
    ) {}

    public function instructions(): Stringable|string;   // bot instructions + shared guardrails
    public function messages(): iterable;                // session scope or bot scope
    public function tools(): iterable;                   // allowlist → registry → instances
}
```

- `instructions()` = `$bot->instructions` + the shared
  `AdminAssistantAgent` write-approval rules + `PlatformKnowledge::adminNotes()`
  + the actor's workspace/user context.
- `tools()` resolves `allowed_tools` through the registry and instantiates each
  with `chatSessionId:` where the tool accepts it (every write tool does).
- `messages()` honours `memory_scope`:
  - `session` → the current `chat_session`'s messages (today's behaviour).
  - `bot` → the last *N* (default 20) messages where `bot_id = $bot->id` or the
    session includes this Bot, across sessions in the workspace, plus the
    top `importance <= 2` rows from `ai_bot_memories`.

`max_steps` is dynamic per Bot. **The SDK already supports this**:
`TextGenerationOptions::resolve()` (`vendor/laravel/ai/src/Gateway/TextGenerationOptions.php:111`)
looks for a **method** on the agent first and only falls back to the
`#[MaxSteps]` attribute. So `BotAgent` implements:

```php
public function maxSteps(): int
{
    return $this->bot->max_steps;
}
```

The same trick gives per-Bot `model()`, `provider()`, `timeout()`,
`maxTokens()` and `temperature()` — `Promptable::getProvidersAndModels()` and
`::getTimeout()` both prefer a method over the attribute. **No SDK change and
no SDK upgrade is required** for any per-Bot override.

### 5.3 `App\Ai\Agents\CoordinatorAgent`

The "Chief of Staff" pattern.

```php
class CoordinatorAgent implements Agent, HasTools
{
    public function tools(): iterable
    {
        // One AgentTool per specialist in the roster, plus DelegateToBot
        return $this->roster->map(
            fn (AiBot $bot) => new AgentTool(new BotAgent($bot, ...))
        )->all();
    }
}
```

Its instructions direct it to pick **one** owner per stage, delegate narrow
tasks, and summarize — mirroring the "one delivery owner per task by default"
rule that keeps multi-agent teams from turning into ceremony.

`AgentTool` is used verbatim: it already calls `$agent->prompt($task)->text` and
returns a string. Note its own description states the sub-agent "runs in
isolation and has no access to the parent conversation history" — which is
correct, and is why the *task* must be self-contained.

### 5.4 `App\Ai\Tools\DelegateToBot` (async)

```php
class DelegateToBot implements Tool
{
    public function handle(Request $request): Stringable|string
    {
        // validate target bot is in the same workspace + active
        // insert ai_bot_messages row (status = queued)
        // dispatch RunAiBotHandoff
        // return "Handoff queued to <Bot>. Reference #<id>."
    }
}
```

The tool **never** returns the result inline. The waking Bot's reply is
delivered later as a `chat_message` authored by the target Bot.

### 5.5 `App\Jobs\RunAiBotHandoff`

Queue: `ai`. Mirrors the existing `GenerateAiDailyReport` job shape
(`ShouldBeUnique`, `tries`, `timeout`, `failOnTimeout`).

1. Load the `ai_bot_messages` row; if not `queued`, return (idempotent).
2. Mark `running`.
3. Build a `BotAgent` for the target Bot with `memory_scope = bot`.
4. Prompt it with the task. **Wrap the task through
   `AiInputGuardrailService::wrapUntrusted()`** — a handoff body originates
   from another model and must be treated as untrusted input (§6.3).
5. Persist `result`, set `status = completed`, store the transcript as
   `chat_messages` authored by the target Bot.
6. If the target Bot staged a `PendingAiAction`, surface the approval card
   against the **originating human's** session.

### 5.6 `App\Jobs\RunAiBotRoutine` + `App\Console\Commands\DispatchAiBotRoutines`

Mirrors `DispatchAiDailyReport`, which is already scheduled in
`routes/console.php`:

```php
Schedule::command(DispatchAiBotRoutines::class, ['--no-interaction'])
    ->everyMinute()->timezone(config('app.timezone'))
    ->withoutOverlapping(5)->onOneServer();
```

Each due routine dispatches `RunAiBotRoutine`, which prompts the Bot and
stores the transcript in a dedicated session (`source = 'routine'`).

### 5.7 Wiring into `ChatService`

`ChatService::prompt()` / `::stream()` currently hardcode:

```php
$agentClass = $user?->is_admin ? AdminAssistantAgent::class : AssistantAgent::class;
```

New resolution order:

1. If the request names a Bot (`bot_id` on the session) → `BotAgent`.
2. Else if the session is `mode = group` → `CoordinatorAgent`.
3. Else → today's behaviour, unchanged.

`promptAgent()` / `streamAgent()` currently type the parameter as
`class-string`; this must widen to `Agent` so a `BotAgent` instance can be
passed. Existing behaviour must not change for non-Bot sessions.

## 6. Guardrails and security

This is a school LMS. The threat surface of "agents that talk to each other"
is larger than "one agent that talks to a human".

### 6.1 Writes still require a human

**Invariant: no Bot, and no Bot-to-Bot handoff, can execute a write without a
human approving a `PendingAiAction`.**

- A delegated Bot mounts write tools normally; those tools stage approvals
  exactly as they do today.
- The approval card must show the **full chain**: which Bot asked, which Bot
  proposed the action, and the exact diff.
- The nonce remains server-side and is never returned to any model.
- The approval is requested from the human who started the chain, not from
  another Bot.

### 6.2 Tenant isolation

Every delegation validates `from_bot.workspace_id === to_bot.workspace_id`
before writing an `ai_bot_messages` row. A cross-workspace delegation is
rejected with an audit event, not a silent no-op.

Platform Bots (`workspace_id = null`) may only delegate to platform Bots.

### 6.3 Untrusted content crossing agent boundaries

A Bot's output is untrusted input to the next Bot. The handoff body and any
Bot-authored chat message are passed through `AiInputGuardrailService`
(`wrapUntrusted()`), which fences tool-output content so it cannot be mistaken
for an instruction.

Bot `instructions` are **not** wrapped — they are admin-authored configuration
and are treated as trusted. A Bot may never modify its own instructions.

### 6.4 Runaway prevention

| Limit | Value | Reason |
| --- | --- | --- |
| Delegation depth | 2 hops max | Stops A → B → C → D recursion |
| Bots per group session | 6 max | Matches Grok Bot's 2–6 bound |
| `max_steps` per Bot | default 8, hard cap 20 | Bounds the tool-call loop |
| Pending handoffs per workspace | 50 | Bounds queue depth |
| Routine handoffs per day per Bot | 10 | Bounds unattended spend |

### 6.5 Approval spoofing

`EchoReplyGuard` already strips invented approval/export claims from replies.
The same guard must apply to Bot replies — a Bot must not claim an approval
card exists unless a write tool actually ran in that turn. `ChatService::
guardAdminReply()` currently applies only when
`$agent instanceof AdminAssistantAgent`; extend it to any Bot.

## 7. Cost control

Tool definitions dominate input tokens. 75 tools ≈ several thousand tokens per
request, multiplied by every agent in a chain.

| Lever | Mechanism |
| --- | --- |
| **Per-Bot allowlist** | `allowed_tools` — a narrow Bot ships 3 tools instead of 75. This is the single biggest lever. |
| **Per-Bot model override** | `model` — cheap models for narrow bots, expensive for the coordinator |
| **`max_steps`** | Bounds tool-call loops per turn |
| **Per-Bot usage attribution** | `ai_usage_logs.bot_id` surfaces which Bot burns tokens |
| **Budget feature keys** | Extend `AiBudgetManager` with a `bot_handoff` feature so delegated spend is budgeted separately from `chat` |
| **Deferred tool loading** | **Not available.** `laravel/ai` v0.9.1 ships only `WebSearch`, `WebFetch`, `FileSearch` as provider tools — there is no `ToolSearch` (that arrived in a later release). Tool scoping must therefore be **manual**, via the per-Bot allowlist. Do not plan around deferred loading. |

**Target:** a 3-Bot chain should cost no more than roughly one current Echo
turn. If it does not, the allowlists are too wide.

## 8. UI

Admin-facing only in v1 (Filament v5, matching `AiSettings` conventions).

| Surface | Purpose |
| --- | --- |
| `App\Filament\Resources\AiBots\AiBotResource` | CRUD the roster: name, title, avatar, instructions, tool allowlist (checkbox group, grouped read/write/platform), model, memory scope, active |
| `App\Filament\Pages\AiBotRoutines` | manage crons per Bot, last-run status |
| `App\Filament\Pages\AiBotActivity` | handoff log + transcripts per Bot (audit view) |
| `AdminAiChat` (existing) | add a Bot picker; `mode = group` shows the participating Bots |

The tool allowlist UI must group tools by kind and default write tools to
**off** for every new Bot.

## 9. Testing

Pest. The project's rule is that every change is programmatically tested.

### 9.1 New test files

| File | Covers |
| --- | --- |
| `tests/Feature/Ai/ToolRegistryTest.php` | every registry key resolves to a class; every `PendingAiActionService::ALLOWED_TYPES` write tool has a registry key; no duplicates |
| `tests/Feature/Ai/BotAgentTest.php` | instructions include bot text; tools honour the allowlist; `memory_scope` selects the right history; `max_steps` respected |
| `tests/Feature/Ai/BotHandoffTest.php` | delegation creates a `queued` row; `RunAiBotHandoff` runs the target and stores the result; cross-workspace delegation rejected; depth cap enforced; idempotent re-run |
| `tests/Feature/Ai/BotRoutineTest.php` | due routines dispatch; overlapping runs are skipped; a failed routine records `last_status`/`last_error` |
| `tests/Feature/Ai/BotGuardrailTest.php` | delegated writes still stage approvals and never execute; `EchoReplyGuard` strips invented approval claims from Bot replies; handoff bodies are wrapped as untrusted; no cross-tenant reads |

### 9.2 Existing tests that must keep passing

- `tests/Feature/OpenAiCompatibleProviderTest.php` — asserts an **exact ordered**
  tool list and that instructions mention every tool name.
- `tests/Feature/Chat*`, `tests/Feature/AdminAi*` — current Echo behaviour.

### 9.3 The tool-list test is a trap

Per `.ai/rules/ai.md`, adding a tool requires five touch points and
`OpenAiCompatibleProviderTest` asserts an exact ordered list. Phase 0's registry
extraction exists partly to make this test derive from one source of truth
instead of a hand-maintained literal. Update the assertion to read from
`ToolRegistry` and keep an explicit expectation of the **count** so a silent
drop is still caught.

## 10. Rollout

| Phase | Deliverable | Acceptance criteria |
| --- | --- | --- |
| **0 — Foundation** | `ToolRegistry`, `ai_bots` migration + model + factory + seeder, `BotAgent`, `AiBotResource` (CRUD only) | An admin can create a Bot with an allowlist; the Bot answers a chat turn using only its allowlisted tools; registry test green |
| **1 — Direct use** | `ChatService` resolves `BotAgent` when a session names a Bot; Bot picker in `AdminAiChat` | A Bot behaves as a named teammate with its own instructions and session memory; Echo behaviour unchanged when no Bot is selected |
| **2 — Delegation** | `ai_bot_messages`, `DelegateToBot`, `RunAiBotHandoff`, `CoordinatorAgent`, group sessions | A coordinator hands a subtask to a specialist and the specialist's reply lands in the transcript; depth and tenant caps enforced; all delegated writes require approval |
| **3 — Routines** | `ai_bot_routines`, `RunAiBotRoutine`, `DispatchAiBotRoutines` | A daily routine fires once per day, stores its transcript, and respects the daily cap |
| **4 — Memory** | `ai_bot_memories`, approval-gated `remember_bot_memory` action | A Bot's durable facts persist across sessions and can only be written with human approval |

Phases 0–1 are the smallest slice that delivers the headline value ("a named
teammate with its own role and memory"). Phases 2–4 add delegation, autonomy,
and durability.

## 11. Risks

| Risk | Mitigation |
| --- | --- |
| Token blowup from wide tool sets | Per-Bot allowlist is mandatory, not optional; per-Bot budget attribution; `max_steps` |
| Runaway agent loops / cost | Depth cap 2, group cap 6, pending-handoff cap, daily routine cap, per-Bot budget |
| Approval spoofing across a chain | Approval cards show the full chain; nonce stays server-side; `EchoReplyGuard` extended to Bot replies |
| Prompt injection from one Bot into another | `wrapUntrusted()` on every handoff body and Bot-authored message |
| Cross-tenant data leak via delegation | `workspace_id` equality check on every delegation, audited |
| Octane state bleed between requests | No static/singleton state; `AiManager` is already flushed per request via `config/octane.php`; `AiSdkProviderService` reads immutable `env_key`/`env_url` for fallbacks — **do not** introduce a per-request mutable static |
| Registry drift vs `ALLOWED_TYPES` | Single registry test asserts parity; registry is the source of truth for both |
| Feature creep toward a general agent framework | Non-goals in §2.1 are binding; no new Composer dependency |

## 12. Open questions

1. **Are Bots workspace-scoped or platform-scoped?** Recommendation:
   workspace-scoped, plus a `workspace_id = null` tier restricted to super
   admins for platform-ops Bots. Needs a product decision.
2. **May Bots write in v1?** Recommendation: Phase 0–1 Bot allowlists default to
   **read-only**; write tools are unlocked in Phase 2 once the delegated
   approval chain is proven.
3. **Default model per Bot?** Recommendation: inherit the workspace default
   provider/model; allow a per-Bot override only for cheap/small models.
4. **Does the student side ever get Bots?** Recommendation: not in v1. The
   student `AssistantAgent` stays a single assistant.
5. **`grok-4.20-multi-agent` as a cheaper alternative?** The xAI multi-agent
   model (`grok-4.20-multi-agent`, 4 or 16 agents, server-side) gives
   "multiple agents" with a one-line model change and no orchestration layer.
   It is **not** a substitute for this design — no persistent identity, no
   per-Bot memory, no cross-Bot handoff, no Routines — but it is a good
   complement for one-shot research and should be wired into
   `AiSdkProviderService` independently.

---

## Appendix A — File touch points

### New files

```
app/Models/AiBot.php
app/Models/AiBotMessage.php
app/Models/AiBotRoutine.php
app/Models/AiBotMemory.php
app/Ai/Support/ToolRegistry.php
app/Ai/Agents/BotAgent.php
app/Ai/Agents/CoordinatorAgent.php
app/Ai/Tools/DelegateToBot.php
app/Jobs/RunAiBotHandoff.php
app/Jobs/RunAiBotRoutine.php
app/Console/Commands/DispatchAiBotRoutines.php
app/Filament/Resources/AiBots/AiBotResource.php
app/Filament/Resources/AiBots/{ListAiBots,CreateAiBot,EditAiBot}.php
app/Filament/Pages/AiBotRoutines.php
app/Filament/Pages/AiBotActivity.php
database/migrations/xxxx_xx_xx_create_ai_bots_table.php
database/migrations/xxxx_xx_xx_create_ai_bot_messages_table.php
database/migrations/xxxx_xx_xx_create_ai_bot_routines_table.php
database/migrations/xxxx_xx_xx_create_ai_bot_memories_table.php
database/migrations/xxxx_xx_xx_add_bot_columns_to_chat_tables.php
database/factories/AiBotFactory.php
```

### Modified files

```
app/Services/ChatService.php                        # resolve Bot / Coordinator agent
app/Services/PendingAiActionService.php             # ALLOWED_TYPES + remember_bot_memory
app/Services/AiActionExecutor.php                   # prepareRememberBotMemory()
app/Services/AiBudgetManager.php                    # bot_handoff feature key
app/Ai/Agents/AdminAssistantAgent.php               # build tools from ToolRegistry
app/Ai/Support/AdminAssistantIntent.php             # optional: intent hints per Bot
app/Models/ChatSession.php                          # bot_id, mode
app/Models/ChatMessage.php                          # bot_id
app/Models/AiUsageLog.php                           # bot_id
app/Services/AiOutputSanitizerService.php           # allow Bot-authored message markers
routes/web.php                                      # bot-scoped chat endpoints
routes/console.php                                  # DispatchAiBotRoutines schedule
resources/views/filament/pages/ai-chat.blade.php    # Bot picker + group view
tests/Feature/OpenAiCompatibleProviderTest.php      # tool list from ToolRegistry
```

### Rules to update

`.ai/rules/ai.md` — add the registry as the canonical tool source and document
the Bot allowlist rule, so the next agent inherits it.
