<?php

namespace App\Filament\Widgets;

use App\Models\Setting;
use App\Services\AiUsageTracker;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Shows an estimate of today's Cloudflare Workers AI neuron usage against the
 * 10,000/day free cap. The estimate is computed from the calls the app itself
 * makes (chat widget, essay grading, AI question/source generation) — Cloudflare
 * does not expose a public usage API, so treat the numbers as approximate and
 * confirm against the Cloudflare dashboard.
 */
class NeuronUsageWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'AI Usage — Cloudflare Neurons (est.)';

    protected ?string $description = 'Estimated from calls this app has made. Cloudflare only exposes exact usage in its dashboard.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $today = AiUsageTracker::neuronsForDay();
        $limit = AiUsageTracker::DAILY_NEURON_LIMIT;
        $percent = (int) round(($today / $limit) * 100);

        $weekSeries = array_values(AiUsageTracker::neuronsForLastDays(7));
        $requests = AiUsageTracker::requestsTodayBySource();
        $totalRequests = array_sum($requests);

        $color = $percent >= 80 ? 'danger' : ($percent >= 50 ? 'warning' : 'success');

        // Filament v5.x Stat has no progress() method, so render a compact
        // text bar (10 blocks) as a version-safe visual.
        $filled = (int) min(10, round(($percent / 100) * 10));
        $bar = str_repeat('█', $filled).str_repeat('░', 10 - $filled);

        $provider = Setting::get('ai_provider', 'gemini');

        $callDescription = sprintf(
            '%s chat · %s grading · %s generation',
            number_format($requests['chat']),
            number_format($requests['grading']),
            number_format($requests['generation']),
        );

        if ($provider === 'cloudflare') {
            $chatModel = Setting::get('cloudflare_model', '@cf/zai-org/glm-4.7-flash');
            $gradeModel = Setting::get('cloudflare_grading_model') ?? Setting::get('cloudflare_model', '@cf/meta/llama-3.1-8b-instruct');
            $callDescription .= ' · chat: '.$chatModel.' · grade: '.$gradeModel;
        } elseif ($provider !== 'gemini') {
            $callDescription .= ' · provider: '.$provider;
        }

        return [
            Stat::make('AI Neurons Today (est.)', number_format(round($today)).' / '.number_format($limit))
                ->description($bar.' '.$percent.'% used · free cap resets 00:00 UTC')
                ->descriptionIcon('heroicon-m-cpu-chip', IconPosition::Before)
                ->icon('heroicon-o-sparkles')
                ->chart($weekSeries)
                ->color($color),

            Stat::make('AI Calls Today', number_format($totalRequests))
                ->description($callDescription)
                ->descriptionIcon('heroicon-m-chat-bubble-left-right', IconPosition::Before)
                ->icon('heroicon-o-command-line')
                ->color('info'),
        ];
    }
}
