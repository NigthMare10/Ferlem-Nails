<?php

namespace App\Notifications;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InternalNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $eventType,
        public readonly string $title,
        public readonly string $message,
        public readonly string $url,
        public readonly ?User $actor,
        public readonly array $entity,
        public readonly CarbonInterface $occurredAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->eventType,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'actor' => [
                'id' => $this->actor?->getKey(),
                'name' => $this->actor?->name ?? 'Sistema',
            ],
            'entity' => $this->entity,
            'occurred_at' => $this->occurredAt->utc()->toIso8601String(),
        ];
    }
}
