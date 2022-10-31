<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Validator;

use Laminas\Validator\AbstractValidator;
use Laminas\Validator\EmailAddress;
use Netglue\Mail\Postmark\SuppressionList;

use function is_string;

final class NotSuppressed extends AbstractValidator
{
    public const NOT_STRING = 'ValueNotSting';
    public const IS_SUPPRESSED = 'IsSuppressed';
    public const NOT_VALID_EMAIL = 'NotValidEmailAddress';

    /** @var string[] */
    protected $messageTemplates = [
        self::NOT_STRING => 'Expected a string',
        self::IS_SUPPRESSED => 'The email address "%value%" has been suppressed. Email messages cannot be sent to this address.',
        self::NOT_VALID_EMAIL => '"%value%" is not a valid email address',
    ];

    public function __construct(private SuppressionList $suppressionList)
    {
        parent::__construct();
    }

    /** @inheritDoc */
    public function isValid($value): bool
    {
        if (! is_string($value)) {
            $this->error(self::NOT_STRING);

            return false;
        }

        $this->setValue($value);

        $emailValidator = new EmailAddress();
        if (! $emailValidator->isValid($value)) {
            $this->error(self::NOT_VALID_EMAIL);

            return false;
        }

        if ($this->suppressionList->isSuppressed($value)) {
            $this->error(self::IS_SUPPRESSED);

            return false;
        }

        return true;
    }
}
