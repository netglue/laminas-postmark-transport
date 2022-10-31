<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Validator;

use Laminas\Mail\Message;
use Netglue\Mail\Postmark\PermittedSenders;
use Netglue\Mail\Validator\IsMessage;

use function assert;

class FromAddressValidator extends IsMessage
{
    public const MISSING_FROM = 'MissingFrom';
    public const NOT_PERMITTED = 'NotPermitted';

    /** @var string[] */
    protected $messageTemplates = [
        self::NOT_MESSAGE => 'Expected a Message object',
        self::MISSING_FROM => 'The message does not have a from address',
        self::NOT_PERMITTED => 'The email address %email% is not listed in Postmark’s sender signatures',
    ];

    /** @var string|null */
    protected $email;

    /** @var string[] */
    protected $messageVariables = ['email' => 'email'];

    public function __construct(private PermittedSenders $permittedSenders)
    {
        parent::__construct();
    }

    /** @inheritDoc */
    public function isValid($value): bool
    {
        if (! parent::isValid($value)) {
            return false;
        }

        assert($value instanceof Message);

        $from = $value->getFrom();
        if (! $from->count()) {
            $this->error(self::MISSING_FROM);

            return false;
        }

        foreach ($from as $address) {
            if (! $this->permittedSenders->isPermittedSender($address->getEmail())) {
                $this->email = $address->getEmail();
                $this->error(self::NOT_PERMITTED);

                return false;
            }
        }

        return true;
    }
}
