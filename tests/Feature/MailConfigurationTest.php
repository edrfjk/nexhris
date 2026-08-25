<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The SMTP transport is built lazily, so a bad scheme or host only surfaces
 * when a user is already mid-login. These assert the configuration is sound
 * before that happens.
 */
class MailConfigurationTest extends TestCase
{
    public function test_the_mail_scheme_is_one_symfony_accepts(): void
    {
        $scheme = config('mail.mailers.smtp.scheme');

        if ($scheme === null) {
            $this->addToAssertionCount(1);

            return;
        }

        // "tls" looks plausible but builds no transport at all.
        $this->assertContains($scheme, ['smtp', 'smtps'],
            'MAIL_SCHEME must be "smtp" (port 587, STARTTLS) or "smtps" (port 465).');
    }

    public function test_the_configured_transport_can_actually_be_built(): void
    {
        // Guards against every misconfiguration that fails at transport
        // construction: bad scheme, missing host, malformed DSN.
        $transport = Mail::mailer(config('mail.default'))->getSymfonyTransport();

        $this->assertNotNull($transport);
    }

    public function test_a_gmail_app_password_is_stored_without_spaces(): void
    {
        if (config('mail.mailers.smtp.host') !== 'smtp.gmail.com') {
            $this->markTestSkipped('Not using Gmail.');
        }

        $password = (string) config('mail.mailers.smtp.password');

        $this->assertStringNotContainsString(' ', $password,
            'Google displays app passwords in groups of four; the spaces must be stripped.');
        $this->assertStringNotContainsString('paste-your', $password,
            'MAIL_PASSWORD is still the placeholder.');
    }
}
