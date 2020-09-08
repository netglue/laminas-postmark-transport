<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Container;

use Laminas\Validator\ValidatorPluginManager;
use Netglue\Mail\Postmark\Transport\PostmarkTransport;
use Netglue\Mail\Postmark\Validator\MessageValidator;
use Postmark\PostmarkClient;
use Psr\Container\ContainerInterface;

use function assert;

class PostmarkTransportFactory
{
    public function __invoke(ContainerInterface $container): PostmarkTransport
    {
        $validators = $container->get(ValidatorPluginManager::class);
        assert($validators instanceof ValidatorPluginManager);

        $config = $container->get('config')['postmark'] ?? [];
        $validatorId = $config['message_validator'] ?? MessageValidator::class;

        return new PostmarkTransport(
            $container->get(PostmarkClient::class),
            $validators->get($validatorId)
        );
    }
}
