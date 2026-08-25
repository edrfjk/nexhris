<?php

namespace App\Console\Commands;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Checks that outgoing mail actually works, so a broken SMTP setup is found
 * here rather than by a user stuck on the verification screen.
 */
class TestMailCommand extends Command
{
    protected $signature = 'mail:test {to? : Address to send to (defaults to the signed-in HR account)}';

    protected $description = 'Send a test verification email to confirm the mail settings work';

    public function handle(): int
    {
        $mailer = config('mail.default');
        $host = config('mail.mailers.smtp.host');
        $username = config('mail.mailers.smtp.username');
        $password = (string) config('mail.mailers.smtp.password');

        $this->line('');
        $this->line('  <options=bold>Mail configuration</>');
        $this->line("  transport : {$mailer}");
        $this->line("  host      : {$host}:" . config('mail.mailers.smtp.port'));
        $this->line("  username  : {$username}");
        $this->line('  password  : ' . ($password === '' ? '(empty)' : str_repeat('*', min(16, strlen($password)))));
        $this->line('  from      : ' . config('mail.from.address'));
        $this->line('');

        if ($mailer === 'log') {
            $this->warn('  MAIL_MAILER is "log", so nothing is actually sent.');
            $this->line('  Emails are written to storage/logs/laravel.log instead.');

            return self::FAILURE;
        }

        // Symfony's mailer accepts only "smtp" and "smtps". A value like
        // "tls" builds no transport at all, and the failure surfaces to users
        // as an unexplained "could not send your code".
        $scheme = config('mail.mailers.smtp.scheme');

        if ($scheme && ! in_array($scheme, ['smtp', 'smtps'], true)) {
            $this->error("  MAIL_SCHEME is \"{$scheme}\", which is not a valid scheme.");
            $this->line('  Use "smtp" for port 587 (STARTTLS) or "smtps" for port 465.');

            return self::FAILURE;
        }

        if (str_contains($password, 'paste-your')) {
            $this->error('  MAIL_PASSWORD is still the placeholder.');
            $this->line('  Put your Gmail App Password in .env, then run: php artisan config:clear');

            return self::FAILURE;
        }

        // Gmail App Passwords are 16 characters; Google displays them in
        // groups of four, and pasting the spaces is the usual mistake.
        if ($host === 'smtp.gmail.com' && str_contains($password, ' ')) {
            $this->warn('  Your app password contains spaces. Google shows it as "abcd efgh ijkl mnop"');
            $this->warn('  but it must be stored without spaces: "abcdefghijklmnop".');
            $this->line('');
        }

        $to = $this->argument('to')
            ?? User::where('role', 'admin')->value('email')
            ?? config('mail.from.address');

        $user = User::where('email', $to)->first()
            ?? User::where('role', 'admin')->first()
            ?? new User(['name' => 'Test Recipient', 'email' => $to, 'role' => 'admin']);

        $this->line("  Sending a sample verification email to <options=bold>{$to}</> ...");

        try {
            Mail::to($to)->send(new TwoFactorCodeMail($user, '123456', 10));
        } catch (\Throwable $e) {
            $this->line('');
            $this->error('  Send failed: ' . $e->getMessage());
            $this->line('');
            $this->line('  Common causes:');
            $this->line('   - Using your normal Gmail password instead of an App Password');
            $this->line('   - 2-Step Verification not enabled on the Google account');
            $this->line('   - App Password pasted with spaces');
            $this->line('   - A firewall blocking outbound port 587');

            return self::FAILURE;
        }

        $this->line('');
        $this->info("  Sent. Check the inbox for {$to} (look in Spam too, on the first send).");

        return self::SUCCESS;
    }
}
