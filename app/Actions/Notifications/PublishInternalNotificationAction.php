<?php

namespace App\Actions\Notifications;

use App\Models\InternalNotification as InternalNotificationRecord;
use App\Models\User;
use App\Notifications\InternalNotification;
use App\Support\Permissions;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublishInternalNotificationAction
{
    public function execute(
        User $actor,
        string $type,
        string $title,
        string $message,
        string $url,
        array $entity,
        string $dedupeKey,
        CarbonInterface $occurredAt,
        ?string $recipientPermission = null,
    ): void {
        $notification = new InternalNotification($type, $title, $message, $url, $actor, $entity, $occurredAt);
        $payload = json_encode($notification->toDatabase($actor), JSON_THROW_ON_ERROR);
        $now = now('UTC');

        User::query()
            ->where('is_active', true)
            ->role(['owner', 'administrator'])
            ->permission(Permissions::NOTIFICATIONS_ACCESS)
            ->when($recipientPermission, fn ($query) => $query->permission($recipientPermission))
            ->select('users.id')
            ->eachById(function (User $recipient) use ($dedupeKey, $now, $payload) {
                DB::table((new InternalNotificationRecord)->getTable())->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'type' => InternalNotification::class,
                    'notifiable_type' => User::class,
                    'notifiable_id' => $recipient->getKey(),
                    'dedupe_key' => $dedupeKey,
                    'data' => $payload,
                    'read_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }
}
