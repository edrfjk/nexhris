<?php

namespace App\Services;

/**
 * Raised when a verification code could not be emailed. Distinct from a
 * generic failure so the login flow can sign the half-authenticated user back
 * out and explain what happened, rather than stranding them on the code screen.
 */
class TwoFactorDeliveryException extends \RuntimeException
{
}
