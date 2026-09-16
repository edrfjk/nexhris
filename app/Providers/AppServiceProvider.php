<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->composePasswordResetMail();
    }

    /**
     * The password reset notice is Laravel's, not ours, and its stock wording
     * ("we received a password reset request for your account") does not say
     * who sent it or what to do if it was not you. Every other NexHRIS email
     * opens with the person's name and closes with the office, so this one
     * does too. The shell around it comes from resources/views/vendor/mail.
     */
    private function composePasswordResetMail(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $minutes = Config::get('auth.passwords.' . Config::get('auth.defaults.passwords') . '.expire', 60);

            return (new MailMessage)
                ->subject('Reset your NexHRIS password')
                ->greeting('Hello ' . ($notifiable->name ?? 'there') . ',')
                ->line('A password reset was requested for the NexHRIS account registered to this email address.')
                ->action('Set a new password', $url)
                ->line('The link expires in ' . $minutes . ' minutes and can be used once.')
                ->line('If you did not request this, no action is needed — your password stays as it is. Tell the Human Resource Management Office if you keep receiving these.')
                ->salutation('— NexHRIS, ISPSC Tagudin Campus');
        });
    }
}
