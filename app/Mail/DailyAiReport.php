<?php

namespace App\Mail;

use App\Models\AiDailyReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyAiReport extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public AiDailyReport $report)
    {
        $this->onQueue(config('ai_reports.queue', 'ai'));
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $marker = $this->report->status === AiDailyReport::STATUS_COMPLETED ? '' : ' [Incomplete]';

        return new Envelope(subject: 'Daily AI report: '.$this->report->report_date->toDateString().$marker);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ai-daily-report');
    }
}
