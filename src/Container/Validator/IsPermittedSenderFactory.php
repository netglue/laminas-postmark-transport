<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Container\Validator;

use Netglue\Mail\Postmark\PermittedSenders;
use Netglue\Mail\Postmark\Validator\IsPermittedSender;
use Psr\Container\ContainerInterface;

final class IsPermittedSenderFactory
{
    public function __invoke(ContainerInterface $container): IsPermittedSender
    {
        return new IsPermittedSender(
            $container->get(PermittedSenders::class)
        );
    }
}
