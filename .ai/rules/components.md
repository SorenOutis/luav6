---
paths:
  - resources/views/filament/components/ai-app-head.blade.php
---

# Components

## Echo AI PWA manifest is a dynamic route
The Echo AI PWA link points at the dynamic route ai-app-manifest (/echo-ai.webmanifest, AiAppManifestController), which overrides theme_color/background_color from school_accent_color on top of static public/ai-assistant.webmanifest. Because it is a Laravel route, 'echo-ai.webmanifest' must stay in EnsurePlatformMaintenance's allowlist or the manifest 503s during maintenance. public/ai-app-sw.js registers with scope /admin/ai-assistant-app from js/ai-app-install.js; never cache navigations or /api/ in the worker.
