<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDailyReport extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_INCOMPLETE = 'incomplete';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'report_date',
        'timezone',
        'status',
        'recipient_id',
        'workspace_ids',
        'max_cost_usd',
        'snapshot',
        'analysis',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'estimated_cost_micros',
        'error_code',
        'generation_queued_at',
        'started_at',
        'completed_at',
        'delivery_queued_at',
        'delivery_started_at',
        'email_sent_at',
        'delivery_failed_at',
        'delivery_error_code',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_date' => 'immutable_date',
            'workspace_ids' => 'array',
            'max_cost_usd' => 'float',
            'snapshot' => 'array',
            'analysis' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost_micros' => 'integer',
            'generation_queued_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'delivery_queued_at' => 'immutable_datetime',
            'delivery_started_at' => 'immutable_datetime',
            'email_sent_at' => 'immutable_datetime',
            'delivery_failed_at' => 'immutable_datetime',
        ];
    }

    public function setReportDateAttribute(string|DateTimeInterface $value): void
    {
        $this->attributes['report_date'] = $value instanceof DateTimeInterface
            ? $value->format('Y-m-d')
            : CarbonImmutable::parse($value)->toDateString();
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function isReadyForDelivery(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_INCOMPLETE, self::STATUS_FAILED], true);
    }
}
