<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Exception;

use Laminas\Validator\ValidatorInterface;
use Netglue\Mail\Exception\InvalidArgument;

use function array_filter;
use function implode;
use function is_string;
use function sprintf;

use const PHP_EOL;

class MessageValidationFailure extends InvalidArgument
{
    public static function withValidator(ValidatorInterface $validator): self
    {
        $errors = array_filter($validator->getMessages(), static function ($value): bool {
            return is_string($value);
        });

        $message = sprintf(
            'The message provided does not pass Postmark validation rules: ' . PHP_EOL . '%s',
            implode(PHP_EOL, $errors)
        );

        return new self($message, 400);
    }
}
