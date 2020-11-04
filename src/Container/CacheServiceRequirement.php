<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Container;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

trait CacheServiceRequirement
{
    private function retrieveCache(ContainerInterface $container): CacheItemPoolInterface
    {
        $config = $container->get('config')['postmark'];
        $cacheId = $config['cache_service'] ?? null;
        if (! $cacheId || ! $container->has($cacheId)) {
            throw new RuntimeException(
                'A cache service must be defined in your container configuration under the key '
                . '[postmark][cache_service] that resolves to a Psr\Cache\CacheItemPoolInterface ' .
                'implementation of your choosing.'
            );
        }

        return $container->get($cacheId);
    }
}
