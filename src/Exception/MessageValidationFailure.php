<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Exception;

use Laminas\Validator\ValidatorInterface;
use Netglue\Mail\Exception\InvalidArgument;

use function implode;
use function sprintf;

use const PHP_EOL;

class MessageValidationFailure extends InvalidArgument
{
    public static function withValidator(ValidatorInterface $validator): self
    {
        $message = sprintf(
            'The message provided does not pass Postmark validation rules: ' . PHP_EOL . '%s',
            implode(PHP_EOL, $validator->getMessages()),
        );

        return new self($message, 400);
    }
}
