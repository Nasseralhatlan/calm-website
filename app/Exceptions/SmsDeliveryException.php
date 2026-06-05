<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an SMS gateway returns a non-OK response or the network call fails.
 * The OtpService catches none of these — they propagate up so the rate limiter
 * and global error handlers can deal with them appropriately.
 */
final class SmsDeliveryException extends RuntimeException
{
}
