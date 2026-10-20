---
paths:
  - resources/js/composables/useDemoLoop.ts
---

# Composables

## set() must reschedule a running demo loop
useDemoLoop.set(phase) must reschedule the pending timer while the loop is playing; otherwise the new phase inherits the leftover of the phase it replaced and the scripted beat is cut short (e.g. switching a welcome demo conversation jumped straight to a finished turn). The timer callback also bails when the loop was stopped, so stop() really parks it.
