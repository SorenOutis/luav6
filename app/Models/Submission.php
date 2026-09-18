<?php

namespace App\Models;

use App\Events\AssignmentGraded;
use App\Notifications\StudentActivityNotification;
use App\Support\GamificationSyncContext;
use App\Support\PublicFileUrl;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $table = 'assignment_user';

    protected $fillable = [
        'user_id',
        'assignment_id',
        'group_id',
        'submitted',
        'status',
        'grade',
        'file_path',
        'submitted_at',
        'submitted_by',
        'points',
        'xp_earned',
        'feedback',
        'graded_at',
        'graded_by',
        'feedback_seen_at',
        'season_id',
    ];

    protected $casts = [
        'submitted' => 'boolean',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'feedback_seen_at' => 'datetime',
        'points' => 'decimal:2',
        'xp_earned' => 'decimal:2',
    ];

    protected $appends = ['file_url'];

    protected static function booted(): void
    {
        static::saving(function (self $submission): void {
            if ($submission->status === 'Graded') {
                // A feedback revision is a new grading pass: refresh the
                // stamp so "unseen feedback" (feedback_seen_at < graded_at)
                // flags again for students who already opened the old one.
                if (! $submission->graded_at || $submission->isDirty('feedback')) {
                    $submission->graded_at = now();
                }
                if (! $submission->graded_by && auth()->check()) {
                    $submission->graded_by = auth()->id();
                }
            }
        });

        static::created(function (self $submission): void {
            self::applyDelta($submission, 0, self::asFloat($submission->points), 0, self::asFloat($submission->xp_earned), false);
        });

        static::deleted(function (self $submission): void {
            $points = self::asFloat($submission->points);
            $xp = self::asFloat($submission->xp_earned);
            if ($points != 0 || $xp != 0) {
                self::applyDelta($submission, $points, 0, $xp, 0, false);
            }
        });

        static::updated(function (self $submission): void {
            $pointsChanged = $submission->wasChanged('points');
            $xpChanged = $submission->wasChanged('xp_earned');

            $oldPoints = $pointsChanged ? self::asFloat($submission->getOriginal('points')) : self::asFloat($submission->points);
            $newPoints = self::asFloat($submission->points);

            $oldXp = $xpChanged ? self::asFloat($submission->getOriginal('xp_earned')) : self::asFloat($submission->xp_earned);
            $newXp = self::asFloat($submission->xp_earned);

            $shouldNotify = $submission->wasChanged('status')
                || $submission->wasChanged('grade')
                || $submission->wasChanged('points')
                || $submission->wasChanged('xp_earned')
                || $submission->wasChanged('feedback');

            $isNowGraded = $submission->status === 'Graded';

            if ($pointsChanged || $xpChanged) {
                self::applyDelta($submission, $oldPoints, $newPoints, $oldXp, $newXp, $isNowGraded && $shouldNotify);
            } elseif ($isNowGraded && $shouldNotify) {
                self::notifyGraded($submission);
            }

            // Live updates: tell the student's browser a grade or feedback
            // landed so the assignments page can refresh in place. Group
            // members each get their own event when propagation saves their
            // sibling rows (their `updated` hooks dispatch again).
            if ($isNowGraded && $shouldNotify) {
                AssignmentGraded::dispatch(
                    (int) $submission->user_id,
                    (int) $submission->assignment_id,
                    (string) $submission->status,
                    $submission->grade,
                    self::asFloat($submission->points),
                    self::asFloat($submission->xp_earned),
                    filled($submission->feedback),
                    $submission->graded_at?->toIso8601String(),
                );
            }

            self::propagateToGroup($submission);
        });
    }

    /**
     * Group activities share one submission: whenever a member's row changes,
     * mirror the submission/grading state onto the sibling rows. Sibling rows
     * get their own `updated` events, so each member's points/XP award and
     * graded notification fire exactly once through the existing hooks.
     *
     * The propagation is guarded against re-entry: a sibling's own `updated`
     * event must award its points, but must NOT re-save the other siblings —
     * doing so would re-run their awards through stale model instances and
     * double-credit late-joining members. The top-level call already covers
     * every sibling, so nested calls simply return.
     */
    private static bool $propagatingToGroup = false;

    private static function propagateToGroup(self $submission): void
    {
        if (! $submission->group_id || self::$propagatingToGroup) {
            return;
        }

        $siblings = self::query()
            ->where('group_id', $submission->group_id)
            ->whereKeyNot($submission->getKey())
            ->get();

        if ($siblings->isEmpty()) {
            return;
        }

        $fields = [
            'submitted' => $submission->submitted,
            'status' => $submission->status,
            'grade' => $submission->grade,
            'file_path' => $submission->file_path,
            'submitted_at' => $submission->submitted_at,
            'submitted_by' => $submission->submitted_by,
            'points' => $submission->points,
            'xp_earned' => $submission->xp_earned,
            'feedback' => $submission->feedback,
            'graded_at' => $submission->graded_at,
            'graded_by' => $submission->graded_by,
        ];

        self::$propagatingToGroup = true;

        try {
            foreach ($siblings as $sibling) {
                $sibling->forceFill($fields)->save();
            }
        } finally {
            self::$propagatingToGroup = false;
        }
    }

    private static function asFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    private static function applyDelta(self $submission, float $oldPoints, float $newPoints, float $oldXp, float $newXp, bool $notify): void
    {
        $pointsDelta = round($newPoints - $oldPoints, 2);
        $xpDelta = round($newXp - $oldXp, 2);

        if (abs($pointsDelta) < 0.005 && abs($xpDelta) < 0.005) {
            if ($notify) {
                self::notifyGraded($submission);
            }

            return;
        }

        if (! $submission->user_id) {
            return;
        }

        $user = $submission->user;
        if (! $user) {
            return;
        }

        $assignment = $submission->assignment;
        $reason = 'Assignment Graded';
        $description = $assignment ? 'Graded: '.($assignment->title ?? 'Assignment') : 'Assignment graded';

        $context = app(GamificationSyncContext::class);

        $sectionId = $user->sections()->first()?->id;

        if ($sectionId) {
            $sectionProgress = $user->activeSectionProgress($sectionId);
            $context->withoutAutomaticHistory(function () use ($sectionProgress, $pointsDelta, $xpDelta): void {
                if (abs($pointsDelta) >= 0.005) {
                    $sectionProgress->points = (float) $sectionProgress->points + $pointsDelta;
                }
                if (abs($xpDelta) >= 0.005) {
                    $sectionProgress->exp = (float) ($sectionProgress->exp ?? 0) + $xpDelta;
                }
                $sectionProgress->save();
            });

            $user->recordGamificationHistory($xpDelta, $pointsDelta, $reason, $description, $sectionId, null, $submission->graded_by);
        } elseif ($progress = $user->activeSeasonProgress()) {
            $context->withoutAutomaticHistory(function () use ($progress, $pointsDelta, $xpDelta): void {
                if (abs($pointsDelta) >= 0.005) {
                    $progress->increment('points', $pointsDelta);
                }
                if (abs($xpDelta) >= 0.005) {
                    $progress->increment('exp', $xpDelta);
                }
                $progress->save();
            });

            $user->recordGamificationHistory($xpDelta, $pointsDelta, $reason, $description, null, $progress->season_id, $submission->graded_by);
        } else {
            if (abs($pointsDelta) >= 0.005) {
                $user->increment('points', $pointsDelta);
            }
            if (abs($xpDelta) >= 0.005) {
                $user->increment('exp', $xpDelta);
            }
            $user->save();

            $user->recordGamificationHistory($xpDelta, $pointsDelta, $reason, $description, null, null, $submission->graded_by);
        }

        if ($notify) {
            self::notifyGraded($submission);
        }
    }

    private static function notifyGraded(self $submission): void
    {
        if (! $submission->user) {
            return;
        }

        if ($submission->status !== 'Graded') {
            return;
        }

        $assignment = $submission->assignment;
        $title = $assignment?->title ?? 'Assignment';

        $parts = [];
        if (filled($submission->grade)) {
            $parts[] = 'Grade: '.$submission->grade;
        }
        if ((float) $submission->points > 0) {
            $parts[] = '+'.$submission->points.' pts';
        }
        if ((float) $submission->xp_earned > 0) {
            $parts[] = '+'.$submission->xp_earned.' XP';
        }

        $message = $title.($parts ? ' — '.implode(' · ', $parts) : '');
        if (filled($submission->feedback)) {
            $message .= ' Feedback: '.str($submission->feedback)->limit(120);
        }

        $submission->user->notify(new StudentActivityNotification([
            'type' => 'assignment',
            'icon' => 'clipboard-check',
            'title' => 'Assignment graded: '.$title,
            'message' => $message,
            'meta' => $submission->feedback ? str($submission->feedback)->limit(80)->toString() : 'View your submission',
            'href' => '/assignments',
        ]));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function group()
    {
        return $this->belongsTo(AssignmentGroup::class, 'group_id');
    }

    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function getFileUrlAttribute(): ?string
    {
        return PublicFileUrl::resolve($this->file_path);
    }

    public function getFileExtensionAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION));
    }
}
