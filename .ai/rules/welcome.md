---
paths:
  - 'resources/js/components/welcome/**'
---

# Welcome

## Welcome vignettes are phase-driven and pause off-screen
All welcome-page demo loops (HeroDemoWindow, FeatureCards card demos, MiniDemo wrappers) must use useDemoLoop + useInView from resources/js/composables/useDemoLoop.ts — never raw setInterval/setTimeout. Pass `:enabled="false"` when prefersReducedMotion || isCoarsePointer so the loop parks on `staticPhase` (tests assert these final frames). Replay per-cycle entrance CSS via `:key="cycle"` on the demo's inner root; phase-based state changes use `.is-on` class toggles. The hero H1 keeps the literal 'Make every assessment count.' in an sr-only span (tests + a11y) with aria-hidden animated word spans beside it.

## RevealGroup owns scroll reveals; rv children stagger via --rv-i
All scroll-triggered entrance animations use RevealGroup (x.ai-style blur+slide). Children carry `rv` + `:style="{ '--rv-i': n }"`; variants: rv-down (from above), rv-nudge (x-axis), rv-pop (scale, for chips/pills/stats). Never put `rv` on an element that already owns opacity/transform transitions or animations (rp-panel crossfade, statement-orb float, MiniDemo internals) — the animation fill overrides them; animate a wrapper instead (the role panels stack reveals as one block for this reason). Groups self-disable when useMobile says reduced-motion or low-end; CSS guards live in app.css after the hero motion block. test: tests/js/welcome-reveal.test.ts. jsdom quirk: `'ontouchstart' in window` is true, so useMobile reports low-end in tests — pin hardwareConcurrency/deviceMemory and delete ontouchstart when a test needs the observer path.
