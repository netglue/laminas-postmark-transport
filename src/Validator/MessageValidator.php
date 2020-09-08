<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Validator;

use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorPluginManager;
use Netglue\Mail\Postmark\Transport\PostmarkTransport;
use Netglue\Mail\Validator\HasFromAddress;
use Netglue\Mail\Validator\HasSubject;
use Netglue\Mail\Validator\HasToRecipient;
use Netglue\Mail\Validator\TotalFromCount;
use Netglue\Mail\Validator\TotalRecipientCount;
use Netglue\Mail\Validator\TotalReplyToCount;

final class MessageValidator extends ValidatorChain
{
    public function __construct(?ValidatorPluginManager $pluginManager = null)
    {
        parent::__construct();
        if ($pluginManager) {
            $this->setPluginManager($pluginManager);
        }

        $this->configureDefaults();
    }

    private function configureDefaults(): void
    {
        $this->attachByName(HasFromAddress::class);
        $this->attachByName(TotalFromCount::class, ['max' => 1]);
        $this->attachByName(HasSubject::class);
        $this->attachByName(HasToRecipient::class);
        $this->attachByName(TotalRecipientCount::class, ['max' => PostmarkTransport::MAX_RECIPIENT_COUNT]);
        $this->attachByName(TotalReplyToCount::class, ['max' => 1]);
        $this->attachByName(MetaDataValidator::class);
    }
}
