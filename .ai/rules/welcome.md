---
paths:
  - 'resources/js/components/welcome/**'
  - resources/js/components/welcome/HeroDemoWindow.vue
  - resources/js/components/welcome/RolePillSwitcher.vue
---

# Welcome

## Welcome vignettes are phase-driven and pause off-screen
All welcome-page demo loops (HeroDemoWindow, FeatureCards card demos, MiniDemo wrappers) must use useDemoLoop + useInView from resources/js/composables/useDemoLoop.ts — never raw setInterval/setTimeout. Pass `:enabled="false"` when prefersReducedMotion || isCoarsePointer so the loop parks on `staticPhase` (tests assert these final frames). Replay per-cycle entrance CSS via `:key="cycle"` on the demo's inner root; phase-based state changes use `.is-on` class toggles. The hero H1 keeps the literal 'Make every assessment count.' in an sr-only span (tests + a11y) with aria-hidden animated word spans beside it.

## RevealGroup owns scroll reveals; rv children stagger via --rv-i
All scroll-triggered entrance animations use RevealGroup (x.ai-style blur+slide). Children carry `rv` + `:style="{ '--rv-i': n }"`; variants: rv-down (from above), rv-nudge (x-axis), rv-pop (scale, for chips/pills/stats). Never put `rv` on an element that already owns opacity/transform transitions or animations (rp-panel crossfade, statement-orb float, MiniDemo internals) — the animation fill overrides them; animate a wrapper instead (the role panels stack reveals as one block for this reason). Groups self-disable when useMobile says reduced-motion or low-end; CSS guards live in app.css after the hero motion block. test: tests/js/welcome-reveal.test.ts. jsdom quirk: `'ontouchstart' in window` is true, so useMobile reports low-end in tests — pin hardwareConcurrency/deviceMemory and delete ontouchstart when a test needs the observer path.

## Hero demo: live history preview + Enter press
The history list mirrors the live exchange (activePreview: Echo typing → "You: <request>" → result value → "Sent to class ✓"); closed rows keep their own last line. The Enter keypress is a tail state of phase 0 driven by `promptComplete` (not a timer), so the request only leaves the composer after Maria hits it. Keep the preview line `hidden md:block` — the stacked sidebar below md has no room for a second line.

## Hero demo: scripted pointer drives the Enter press
The Enter press is driven by a scripted on-screen pointer (.hdw-cursor, data-testid="demo-cursor"), not a bare timer: it teleports to a start point with the is-instant class (so it never drifts), glides onto the key, then clicks (is-pressing on both the pointer and the button). The phase-0 budget is prompt + PROMPT_TAIL_MS + CURSOR_TRAVEL_MS + PRESS_MS, so the turn only lands after the click. Position is measured from the Enter key with getBoundingClientRect (zero in jsdom — assert on classes, not coordinates).

## Hero demo: the pointer drives the thread switch
Between conversations the pointer crosses to the next thread instead of the loop auto-advancing: phase 4 arms a switch gesture (APPROVED_MS + SWITCH_TRAVEL_MS + SWITCH_CLICK_MS + SWITCH_SLACK_MS in the phase budget), the target row is measured via [data-conversation] inside the nav, the row lights up (pointedIndex), and the click calls selectScenario(next) — which reschedules the loop, so the loop's own cycle++ never fires. The slack keeps the click ahead of the loop's phase-4 timer.

## Hero demo: the pointer never leaves the screen
The scripted pointer stays on screen for the whole demo — it never hides between phases. It is one continuous hand: resting spot next to the keyboard → glides onto Enter and clicks → waits on the key while Echo works → sweeps across the window to the next history row and clicks it → glides back to the resting spot for the next conversation. Every move is a CSS transition on the position (--cursor-travel), so the pointer never teleports; `placeAt` (the is-instant class) is only for the very first placement. Clear `cursor.pressing` before re-arming a click or the squash animation will not replay.

## Perspective tabs rotate on their own
activeKey is derived from a second useDemoLoop (ROLE_DURATIONS = one slot per role) — the phase index IS the role, so the tabs rotate teacher → learner → school on their own while the section is in view. Clicking a pill calls roleLoop.set(index), which reschedules so the hand-picked tab still gets a full slot. The teacher's chat is a separate loop that only runs while the teacher tab is active, and replayScript() restarts it from phase 0 every time the teacher tab comes back. Panel contents carry .rp-stagger with --i; the animation lives on .rp-panel.is-active .rp-stagger so adding is-active replays it.
