<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCloseReport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const TRIGGER_SCHEDULED = 'scheduled';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_TEST = 'test';

    public const TRIGGER_DOWNLOAD = 'download';

    protected $fillable = [
        'operational_date', 'recipient_email', 'trigger', 'status', 'idempotency_key',
        'pdf_path', 'pdf_sha256', 'pdf_mime', 'summary_snapshot', 'attempts',
        'external_message_id', 'error_message', 'requested_by', 'started_at', 'sent_at', 'failed_at',
    ];

    protected $hidden = ['pdf_path', 'idempotency_key', 'summary_snapshot'];

    protected function casts(): array
    {
        return [
            'operational_date' => 'immutable_date',
            'summary_snapshot' => 'array',
            'attempts' => 'integer',
            'started_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
