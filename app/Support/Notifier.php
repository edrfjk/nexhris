<?php

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifications;

/**
 * Sends a notification without letting the mail server break the work.
 *
 * Every notification here goes to two channels: the in-app bell, which is a
 * database row, and email. Nothing is queued — shared hosting has no worker to
 * run a queue — so the mail is sent inside the request that triggered it, and
 * an unreachable SMTP host throws a TransportException right there.
 *
 * Unhandled, that turns a leave approval into a 500. The reviewer sees an
 * error page, assumes the approval failed and clicks again, while the record
 * was in fact already updated and the in-app notification already written.
 *
 * Gmail's SMTP is exactly the kind of dependency that goes away for a minute
 * at a time — a rate limit, a blocked port, an expired app password. None of
 * those should be able to stop HR approving leave. The database channel has
 * already been written by the time mail fails, so the person still sees the
 * notification when they next open the system; only the email is lost, and the
 * failure is logged so it can be found.
 */
final class Notifier
{
    /**
     * @param  iterable<mixed>|object  $recipients
     */
    public static function send(mixed $recipients, Notification $notification): void
    {
        try {
            Notifications::send($recipients, $notification);
        } catch (\Throwable $e) {
            Log::warning('Notification email could not be sent; the in-app notification still stands.', [
                'notification' => $notification::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
