@if (\Filament\Facades\Filament::auth()->user()?->isSuperAdmin() && request()->routeIs('filament.admin.pages.ai-chat', 'filament.admin.pages.ai-assistant-app'))
    <link rel="manifest" href="{{ asset('ai-assistant.webmanifest') }}" />
    <link rel="apple-touch-icon" href="{{ route('favicon', ['size' => 180]) }}" />
    <meta name="theme-color" content="{{ \App\Models\Setting::get('school_accent_color', '#09090b') }}" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-title" content="Echo AI" />
    <script src="{{ asset('js/ai-app-install.js') }}" defer></script>
@endif
