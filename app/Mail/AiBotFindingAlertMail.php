<?php

namespace App\Mail;

use App\Models\AiBotFinding;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Instant alert for new watcher findings.
 *
 * One email per watcher pass per workspace, containing every new actionable
 * finding. Evidence in the payload is aggregate-only; no user messages,
 * transcript contents, or raw approval payloads ever reach the template.
 */
class AiBotFindingAlertMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** @param list<AiBotFinding> $findings */
    public function __construct(
        public Workspace $workspace,
        public array $findings,
    ) {
        $this->onQueue('ai');
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $count = count($this->findings);

        return new Envelope(
            subject: sprintf('[AI Bots] %d new finding%s — %s', $count, $count === 1 ? '' : 's', $this->workspace->name),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ai-bot-finding-alert');
    }
}
