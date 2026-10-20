---
paths:
  - app/Filament/Pages/AiSettings.php
---

# Filament Pages

## Prefer fast ag/ model variants for Echo AI chat
Measured 2026-10-17 on the live 9router: ag/claude-opus-4-6-thinking ~2.7-3.2s even for toy prompts; ag/gemini-3.8-flash-low ~1.7-2.5s including a tool call, and is the configured chat model. The ag/ family exposes -high/-medium/-low thinking variants; the router drops reasoning_effort/thinking parameters, so express fast mode by model choice only.
