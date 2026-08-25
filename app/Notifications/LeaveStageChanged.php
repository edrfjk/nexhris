<?php

namespace App\Notifications;

use App\Models\LeaveApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent on every leave stage transition — to the reviewer a form has just
 * landed on, and to the employee when it is approved, returned or completed.
 *
 * Dual channel: an in-app row for the notification bell, and an email.
 */
class LeaveStageChanged extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveApplication $application,
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
        $employee = $this->application->user;

        return (new MailMessage)
            ->subject('NexHRIS — ' . $this->headline)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($this->detail)
            ->line('Employee: ' . ($employee->name ?? '—'))
            ->line('Leave type: ' . ($this->application->leave_type === 'VL' ? 'Vacation Leave' : 'Sick Leave'))
            ->line('Inclusive dates: ' . $this->application->date_from?->format('F j, Y')
                . ($this->application->date_to && ! $this->application->date_to->eq($this->application->date_from)
                    ? ' to ' . $this->application->date_to->format('F j, Y') : ''))
            ->action($this->actionLabel, $this->actionUrl)
            ->salutation('— NexHRIS, ISPSC Tagudin Campus');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'leave',
            'headline' => $this->headline,
            'detail' => $this->detail,
            'tone' => $this->tone,
            'url' => $this->actionUrl,
            'application_id' => $this->application->id,
            'employee' => $this->application->user->name ?? null,
        ];
    }
}
