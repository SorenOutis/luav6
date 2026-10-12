<?php

namespace App\Jobs;

use App\Mail\DailyAiReport;
use App\Models\AiDailyReport;
use App\Services\AiDailyReportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;
use RuntimeException;
use Throwable;

class DeliverAiDailyReport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public bool $failOnTimeout = true;

    public function __construct(public int $reportId)
    {
        $this->onQueue(config('ai_reports.queue', 'ai'));
    }

    public function uniqueId(): string
    {
        return (string) $this->reportId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(AiDailyReportService $reports): void
    {
        $report = AiDailyReport::query()->find($this->reportId);
        if (! $report || ! $report->isReadyForDelivery() || $report->email_sent_at !== null) {
            return;
        }

        $recipient = $reports->recipient($report->recipient_id);
        if (! $recipient || ! $reports->isAllowed($report)) {
            $report->update(['delivery_failed_at' => now(), 'delivery_error_code' => 'delivery_not_authorized']);

            return;
        }

        if (! $this->hasDeliveryTransport((string) config('mail.default'))) {
            $report->update(['delivery_failed_at' => now(), 'delivery_error_code' => 'mail_transport_not_configured']);

            return;
        }

        $claimed = AiDailyReport::query()->whereKey($report->id)->whereNull('email_sent_at')
            ->where(fn ($query) => $query->whereNull('delivery_started_at')
                ->orWhere('delivery_started_at', '<', now()->subMinutes(5)))
            ->update(['delivery_started_at' => now()]);

        if (! $claimed) {
            return;
        }

        $report->refresh();

        try {
            // Delivery already runs in a queued job. Sending now keeps tracking
            // and permission checks at the actual send, not at a second queue hop.
            $sent = Mail::to($recipient->email)->sendNow(new DailyAiReport($report));
            if ($sent === null && Mail::getFacadeRoot()::class !== MailFake::class) {
                throw new RuntimeException('The daily report email was not accepted by the mail transport.');
            }

            $report->update([
                'email_sent_at' => now(),
                'delivery_started_at' => null,
                'delivery_failed_at' => null,
                'delivery_error_code' => null,
            ]);
        } catch (Throwable $exception) {
            $report->update(['delivery_started_at' => null, 'delivery_error_code' => 'mail_delivery_failed']);
            throw $exception;
        }
    }

    /** @param list<string> $visited */
    private function hasDeliveryTransport(string $mailer, array $visited = []): bool
    {
        if (in_array($mailer, $visited, true)) {
            return false;
        }

        $configuration = config("mail.mailers.{$mailer}");
        if (! is_array($configuration)) {
            return false;
        }

        $transport = $configuration['transport'] ?? null;
        if (! is_string($transport) || $transport === '' || in_array($transport, ['log', 'array'], true)) {
            return false;
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $mailers = $configuration['mailers'] ?? [];
            if (! is_array($mailers) || $mailers === []) {
                return false;
            }

            foreach ($mailers as $child) {
                if (! is_string($child) || ! $this->hasDeliveryTransport($child, [...$visited, $mailer])) {
                    return false;
                }
            }
        }

        return true;
    }

    public function failed(?Throwable $exception): void
    {
        AiDailyReport::query()->whereKey($this->reportId)->whereNull('email_sent_at')->update([
            'delivery_started_at' => null,
            'delivery_failed_at' => now(),
            'delivery_error_code' => 'mail_delivery_failed',
        ]);

        Log::warning('Nightly AI report email delivery failed.', [
            'report_id' => $this->reportId,
            'exception_type' => $exception ? $exception::class : null,
        ]);
    }
}
