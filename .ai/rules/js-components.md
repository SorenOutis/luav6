---
paths:
  - resources/js/components/ChatAiOrb.vue
---

# Js Components

## ChatAiOrb is CSS-only; tests assert sizes and source order
tests/js/chat-ai-orb.test.ts asserts the source contains no setInterval/setTimeout and keeps the literal size classes h-24/h-20/h-32/h-7 plus the `wolf-to-circle 3.6s` keyframe references. New states (micro, outline) must be pure CSS and inserted BEFORE the `.is-reduced` block — equal-specificity rules later in the stylesheet win, so reduced-motion must stay last. Inline placements use size="inline" (h-[1em] w-[1em]) inside a font-sized wrapper; stagger multiple orbs with :delay (sets --wolf-delay). Outline variant is stroke-only with vector-effect: non-scaling-stroke, safe to scale up as a watermark.
