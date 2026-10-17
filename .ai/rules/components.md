---
paths:
  - resources/views/filament/components/ai-*.blade.php
---

# Components

## Echo AI PWA manifest is a dynamic route
The Echo AI PWA link points at the dynamic route ai-app-manifest (/echo-ai.webmanifest, AiAppManifestController), which overrides theme_color/background_color from school_accent_color on top of static public/ai-assistant.webmanifest. Because it is a Laravel route, 'echo-ai.webmanifest' must stay in EnsurePlatformMaintenance's allowlist or the manifest 503s during maintenance. public/ai-app-sw.js registers with scope /admin/ai-assistant-app from js/ai-app-install.js; never cache navigations or /api/ in the worker.

## Installed Echo AI app spans the whole admin panel
The manifest scope is /admin so the floating chat head works on any admin page inside the installed app. ai-chat-head.blade.php (rendered via PanelsRenderHook::BODY_END for superadmins, scripts in public/js/ai-chat-head.js) only reveals itself client-side in standalone display mode and hides on /admin/ai-chat and /admin/ai-assistant-app; it loads the chat app in an iframe with ?embed=1, which strips the sidebar/header chrome in ai-chat.blade.php. The service worker scope stays /admin/ai-assistant-app — do not assume SW caching for other admin pages. Tailwind classes used by the chat head live in the Blade partial (scanned by the Filament theme); JS must not inject new class names. The head reopens the minimized conversation: ai-chat-script.blade.php mirrors activeSessionUuid/activeSessionId into localStorage key echoAiChatHead.session, and ai-chat-head.js appends ?c=<uuid> when loading the iframe (AdminAiChat resolves ?c=/?session= server-side); when the stored session changes, the iframe is reloaded. After a drag the bubble snaps to the nearest horizontal edge.
