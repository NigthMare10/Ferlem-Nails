<?php

namespace App\Support;

use App\Models\PayrollEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class PayrollAudit
{
    public static function record(Model $subject, string $type, ?User $actor, array $previous = [], array $current = [], ?string $notes = null): void
    {
        $event = new PayrollEvent;
        $event->subject_type = $subject::class;
        $event->subject_id = $subject->getKey();
        $event->event_type = $type;
        $event->performed_by = $actor?->getKey();
        $event->occurred_at = now('UTC');
        $event->previous_values = $previous ?: null;
        $event->new_values = $current ?: null;
        $event->notes = $notes;
        $event->save();
    }
}
