<?php

declare(strict_types=1);

namespace Netglue\MailTest\Postmark;

use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\Mail\Transport\TransportInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ConfigProvider as ValidatorConfigProvider;
use Laminas\Validator\ValidatorPluginManager;
use Netglue\Mail\Postmark\ConfigProvider;
use Netglue\Mail\Postmark\Transport\PostmarkTransport;
use Netglue\Mail\Postmark\Validator\FromAddressValidator;
use Netglue\Mail\Postmark\Validator\IsPermittedSender;
use Netglue\PsrContainer\Postmark\ConfigProvider as PostmarkContainers;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

use function assert;
use function is_array;

class ServiceManagerIntegrationTest extends TestCase
{
    /** @var ServiceManager */
    private $serviceManager;

    protected function setUp(): void
    {
        parent::setUp();

        $config = [
            'postmark' => [
                'server_token' => 'token',
                'account_token' => 'token',
                'cache_service' => 'cache',
            ],
        ];
        $aggregator = new ConfigAggregator([
            ValidatorConfigProvider::class,
            PostmarkContainers::class,
            ConfigProvider::class,
            new ArrayProvider($config),
        ]);
        $config = $aggregator->getMergedConfig();
        assert(is_array($config['dependencies']));
        /** @psalm-suppress MixedArrayAssignment */
        $config['dependencies']['services']['config'] = $config;
        $this->serviceManager = new ServiceManager($config['dependencies']);
        $this->serviceManager->setService('cache', $this->setUpCache());
    }

    private function setUpCache(): CacheItemPoolInterface
    {
        return new ArrayAdapter();
    }

    public function testThatThePostmarkTransportCanBeRetrieved(): void
    {
        self::assertTrue($this->serviceManager->has(PostmarkTransport::class));
        self::assertInstanceOf(PostmarkTransport::class, $this->serviceManager->get(PostmarkTransport::class));
    }

    public function testThatThePostmarkTransportCanBeRetrievedByTransportInterface(): void
    {
        self::assertTrue($this->serviceManager->has(TransportInterface::class));
        self::assertInstanceOf(PostmarkTransport::class, $this->serviceManager->get(TransportInterface::class));
    }

    public function testThatFromAddressValidatorCanBeRetrievedFromThePluginManager(): void
    {
        $plugins = $this->serviceManager->get(ValidatorPluginManager::class);
        self::assertTrue($plugins->has(FromAddressValidator::class));
        self::assertInstanceOf(FromAddressValidator::class, $plugins->get(FromAddressValidator::class));
    }

    public function testThatIsPermittedSenderValidatorCanBeRetrievedFromThePluginManager(): void
    {
        $plugins = $this->serviceManager->get(ValidatorPluginManager::class);
        self::assertTrue($plugins->has(IsPermittedSender::class));
        self::assertInstanceOf(IsPermittedSender::class, $plugins->get(IsPermittedSender::class));
    }
}
