<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Container;

use Netglue\Mail\Postmark\SuppressionList;
use Postmark\PostmarkClient;
use Psr\Container\ContainerInterface;

final class SuppressionListFactory
{
    use CacheServiceRequirement;

    public function __invoke(ContainerInterface $container): SuppressionList
    {
        return new SuppressionList(
            $container->get(PostmarkClient::class),
            $this->retrieveCache($container)
        );
    }
}
