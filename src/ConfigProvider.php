<?php
declare(strict_types=1);

namespace Netglue\Mail\Postmark;

use Laminas\Mail\Transport\TransportInterface;
use Laminas\ServiceManager\Factory\InvokableFactory;

class ConfigProvider
{
    /** @return mixed[] */
    public function __invoke() : array
    {
        return [
            'dependencies' => $this->dependencies(),
            'validators' => $this->validators(),
            'postmark' => $this->postmarkConfig(),
        ];
    }

    /** @return mixed[] */
    private function dependencies() : array
    {
        return [
            'factories' => [
                Transport\PostmarkTransport::class => Container\PostmarkTransportFactory::class,
                PermittedSenders::class => Container\PermittedSendersFactory::class,
            ],
            'aliases' => [
                TransportInterface::class => Transport\PostmarkTransport::class,
            ],
        ];
    }

    /** @return mixed[] */
    private function validators() : array
    {
        return [
            'factories' => [
                Validator\FromAddressValidator::class => Container\Validator\FromAddressValidatorFactory::class,
                Validator\MessageValidator::class => InvokableFactory::class,
            ],
        ];
    }

    /** @return mixed[] */
    private function postmarkConfig() : array
    {
        return ['cache_service' => null];
    }
}
