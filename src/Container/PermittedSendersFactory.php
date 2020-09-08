<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Container;

use Netglue\Mail\Postmark\PermittedSenders;
use Postmark\PostmarkAdminClient;
use Psr\Container\ContainerInterface;
use RuntimeException;

class PermittedSendersFactory
{
    public function __invoke(ContainerInterface $container): PermittedSenders
    {
        $config = $container->get('config')['postmark'];
        $cacheId = $config['cache_service'] ?? null;
        if (! $cacheId || ! $container->has($cacheId)) {
            throw new RuntimeException(
                'In order to use the permitted senders helper, a cache service must be defined in ' .
                'configuration under the key [postmark][cache_service] that resolves to a Psr\Cache\CacheItemPoolInterface ' .
                'implementation of your choosing.'
            );
        }

        return new PermittedSenders(
            $container->get(PostmarkAdminClient::class),
            $container->get($cacheId)
        );
    }
}
