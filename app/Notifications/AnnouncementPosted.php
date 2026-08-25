<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnnouncementPosted extends Notification
{
    use Queueable;

    public function __construct(public Announcement $announcement)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('NexHRIS Announcement — ' . $this->announcement->title)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($this->announcement->excerpt(60))
            ->action('Read the announcement', route('announcements.index'))
            ->salutation('— NexHRIS, ISPSC Tagudin Campus');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'announcement',
            'headline' => $this->announcement->title,
            'detail' => $this->announcement->excerpt(20),
            'tone' => 'info',
            'url' => route('announcements.index'),
            'announcement_id' => $this->announcement->id,
        ];
    }
}
