<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Container;

use Laminas\Validator\ValidatorInterface;
use Laminas\Validator\ValidatorPluginManager;
use Netglue\Mail\Postmark\Transport\PostmarkTransport;
use Netglue\Mail\Postmark\Validator\MessageValidator;
use Postmark\PostmarkClient;
use Psr\Container\ContainerInterface;

use function assert;
use function is_array;

class PostmarkTransportFactory
{
    public function __invoke(ContainerInterface $container): PostmarkTransport
    {
        $validators = $container->get(ValidatorPluginManager::class);

        $config = $container->get('config');
        assert(is_array($config));
        $config = isset($config['postmark']) && is_array($config['postmark']) ? $config['postmark'] : [];
        /** @var class-string<ValidatorInterface> $validatorId */
        $validatorId = $config['message_validator'] ?? MessageValidator::class;

        return new PostmarkTransport(
            $container->get(PostmarkClient::class),
            $validators->get($validatorId)
        );
    }
}
