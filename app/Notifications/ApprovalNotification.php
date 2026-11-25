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
        /**
         * Penunjuk tambahan untuk aplikasi HP (mis. ['post_id' => 5]) agar notifikasi
         * bisa membuka layar yang tepat; web cukup memakai url.
         *
         * @var array<string, scalar|null>
         */
        public array $meta = [],
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'category' => $this->category,
            ...($this->meta !== [] ? ['meta' => $this->meta] : []),
        ];
    }
}
