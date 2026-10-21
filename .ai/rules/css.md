---
paths:
  - resources/css/app.css
---

# Css

## The low-end guard collapses every transition to 0.01ms
html[data-low-end] * collapses transition-duration and animation-duration to 0.01ms !important, so on any machine with <= 4 cores (or a coarse pointer, or deviceMemory <= 4) every transition in the app is instant. Anything that must still move has to be excluded in that rule's :not() list — and only excluded, never added to the `revert` block below it, because `transition-duration: revert` resolves to the UA default (0s), not the author's value, which kills the transition rather than restoring it.
