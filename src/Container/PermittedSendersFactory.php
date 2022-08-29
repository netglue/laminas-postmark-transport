<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Container;

use Netglue\Mail\Postmark\PermittedSenders;
use Postmark\PostmarkAdminClient;
use Psr\Container\ContainerInterface;

class PermittedSendersFactory
{
    use CacheServiceRequirement;

    public function __invoke(ContainerInterface $container): PermittedSenders
    {
        $cache = $this->retrieveCache($container);

        return new PermittedSenders(
            $container->get(PostmarkAdminClient::class),
            $cache,
        );
    }
}
