<?php

namespace App\Notifications;

use App\Models\PdsSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent when a PDS is submitted, approved or returned. */
class PdsStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public PdsSubmission $submission,
        public string $headline,
        public string $detail,
        public string $actionLabel,
        public string $actionUrl,
        public string $tone = 'info',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('NexHRIS — ' . $this->headline)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($this->detail)
            ->line('Employee: ' . ($this->submission->user->name ?? '—'))
            ->line('Applicable year: ' . $this->submission->applicable_year)
            ->action($this->actionLabel, $this->actionUrl)
            ->salutation('— NexHRIS, ISPSC Tagudin Campus');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'pds',
            'headline' => $this->headline,
            'detail' => $this->detail,
            'tone' => $this->tone,
            'url' => $this->actionUrl,
            'submission_id' => $this->submission->id,
            'employee' => $this->submission->user->name ?? null,
        ];
    }
}
