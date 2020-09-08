<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Container\Validator;

use Netglue\Mail\Postmark\PermittedSenders;
use Netglue\Mail\Postmark\Validator\FromAddressValidator;
use Psr\Container\ContainerInterface;

class FromAddressValidatorFactory
{
    public function __invoke(ContainerInterface $container): FromAddressValidator
    {
        return new FromAddressValidator(
            $container->get(PermittedSenders::class)
        );
    }
}
