<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? 'AI bots watcher alert' }}</title>
</head>
<body style="margin: 0; padding: 24px 12px; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #18181b;">
    <div style="max-width: 640px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden;">
        <div style="padding: 20px 24px; background-color: #18181b; color: #ffffff;">
            <p style="margin: 0; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: #d4d4d8;">AI bots · watcher alert</p>
            <h1 style="margin: 6px 0 0; font-size: 18px; line-height: 1.4;">{{ count($findings) }} new finding{{ count($findings) === 1 ? '' : 's' }} — {{ $workspace->name }}</h1>
        </div>

        <div style="padding: 20px 24px;">
            <p style="margin: 0 0 16px; font-size: 14px; color: #3f3f46;">
                A specialist bot's 30-minute read-only check found something that likely needs your attention.
                Nothing was published, sent, or changed — every action below still waits for your approval.
            </p>

            @foreach ($findings as $finding)
                <div style="margin: 0 0 16px; padding: 14px 16px; border: 1px solid {{ $finding->severity === \App\Models\AiBotFinding::SEVERITY_CRITICAL ? '#fecaca' : '#fde68a' }}; border-left: 4px solid {{ $finding->severity === \App\Models\AiBotFinding::SEVERITY_CRITICAL ? '#dc2626' : '#d97706' }}; border-radius: 8px; background-color: {{ $finding->severity === \App\Models\AiBotFinding::SEVERITY_CRITICAL ? '#fef2f2' : '#fffbeb' }};">
                    <p style="margin: 0; font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: {{ $finding->severity === \App\Models\AiBotFinding::SEVERITY_CRITICAL ? '#b91c1c' : '#92400e' }};">
                        {{ ucfirst($finding->severity) }} · {{ $finding->bot?->name ?? 'Watcher bot' }} · {{ ucfirst($finding->domain) }}
                    </p>
                    <h2 style="margin: 6px 0 4px; font-size: 15px; color: #18181b;">{{ $finding->title }}</h2>
                    <p style="margin: 0 0 8px; font-size: 13px; color: #3f3f46;">{{ $finding->summary }}</p>
                    <ul style="margin: 0 0 8px; padding-left: 18px; font-size: 12px; color: #52525b;">
                        @foreach ((array) $finding->evidence as $line)
                            <li style="margin: 2px 0;">{{ $line }}</li>
                        @endforeach
                    </ul>
                    <p style="margin: 0; font-size: 13px; color: #18181b;"><strong>Suggested next step:</strong> {{ $finding->suggested_action }}</p>
                </div>
            @endforeach

            <p style="margin: 0 0 8px; font-size: 13px;">
                <a href="{{ url('/admin/platform-ai-bots/operations') }}"
                   style="color: #4f46e5; font-weight: 600; text-decoration: underline;">
                    Open Bot Operations
                </a>
                to see the bot team room, inspect the finding, and act on it in chat when you are ready.
            </p>
            <p style="margin: 0; font-size: 11px; color: #71717a;">
                Watchers are read-only: they observe counts and ages, never message contents or private details.
                You are receiving this because you are the platform super administrator. Toggles live in Platform Settings → AI Bots.
            </p>
        </div>
    </div>
</body>
</html>
