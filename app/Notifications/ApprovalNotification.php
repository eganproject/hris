<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * A generic in-app (database) notification used across the approval workflows.
 * It carries just enough to render a line in the bell dropdown and link through
 * to the page where the recipient can act.
 */
class ApprovalNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
        public string $url,
        public string $category = 'general',
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, message: string, url: string, category: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'category' => $this->category,
        ];
    }
}
