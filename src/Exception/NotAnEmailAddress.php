<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Exception;

use InvalidArgumentException;

use function sprintf;

final class NotAnEmailAddress extends InvalidArgumentException
{
    public static function withString(string $invalidValue): self
    {
        return new self(sprintf(
            'The value "%s" is not a valid email address',
            $invalidValue,
        ));
    }
}
