@if (\Filament\Facades\Filament::auth()->user()?->isSuperAdmin() && request()->routeIs('filament.admin.pages.ai-chat', 'filament.admin.pages.ai-assistant-app'))
    <link rel="manifest" href="{{ asset('ai-assistant.webmanifest') }}" />
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" />
    <meta name="theme-color" content="#09090b" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-title" content="AI Assistant" />
    <script src="{{ asset('js/ai-app-install.js') }}" defer></script>
@endif
