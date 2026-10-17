---
paths:
  - resources/js/**
---

# Js

## Entrance animations must not use animation-fill-mode both when the element also animates transform

CSS animations override inline styles and class transforms while filling. `.st-swap` in shop/studio.css used `fill-mode: both`, so its final `transform: scale(1)` keyframe permanently beat the inline hover zoom (`transform: scale(1.8)`) in ShopQuickViewModal — zoom silently never worked. Use `backwards` (or none) for entrance animations on elements that also receive hover/inline transforms.
