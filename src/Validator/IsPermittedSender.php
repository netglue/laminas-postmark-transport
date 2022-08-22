<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Validator;

use Laminas\Validator\AbstractValidator;
use Laminas\Validator\EmailAddress;
use Netglue\Mail\Postmark\PermittedSenders;

use function is_string;

final class IsPermittedSender extends AbstractValidator
{
    public const NOT_STRING = 'ValueNotSting';
    public const NOT_PERMITTED = 'NotPermitted';
    public const NOT_VALID_EMAIL = 'NotValidEmailAddress';

    private PermittedSenders $permittedSenders;

    /** @var string[] */
    protected $messageTemplates = [
        self::NOT_STRING => 'Expected a string',
        self::NOT_PERMITTED => 'The email address %value% is not listed in Postmark’s sender signatures',
        self::NOT_VALID_EMAIL => '"%value%" is not a valid email address',
    ];

    public function __construct(PermittedSenders $permittedSenders)
    {
        $this->permittedSenders = $permittedSenders;
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

        if (! $this->permittedSenders->isPermittedSender($value)) {
            $this->error(self::NOT_PERMITTED);

            return false;
        }

        return true;
    }
}
