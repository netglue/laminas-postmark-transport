<?php

declare(strict_types=1);

namespace Netglue\MailTest\Postmark\Container;

use Netglue\Mail\Postmark\Container\PermittedSendersFactory;
use Netglue\Mail\Postmark\PermittedSenders;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Postmark\PostmarkAdminClient;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

class PermittedSendersFactoryTest extends TestCase
{
    /** @var MockObject&ContainerInterface */
    private ContainerInterface $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->createMock(ContainerInterface::class);
    }

    public function testThatAnExceptionIsThrownWhenThereIsNoCacheServiceConfigured(): void
    {
        $config = ['postmark' => []];
        $this->container
            ->expects(self::atLeastOnce())
            ->method('get')
            ->with('config')
            ->willReturn($config);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A cache service must be defined in your container configuration');
        (new PermittedSendersFactory())($this->container);
    }

    public function testExceptionThrownWhenDefinedCacheIsNotAvailable(): void
    {
        $config = ['postmark' => ['cache_service' => 'foo']];
        $this->container
            ->expects(self::atLeastOnce())
            ->method('get')
            ->with('config')
            ->willReturn($config);
        $this->container
            ->expects(self::atLeastOnce())
            ->method('has')
            ->with('foo')
            ->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A cache service must be defined in your container configuration');
        (new PermittedSendersFactory())($this->container);
    }

    public function testHelperCanBeConstructed(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $client = $this->createMock(PostmarkAdminClient::class);

        $config = ['postmark' => ['cache_service' => 'foo']];
        $this->container
            ->expects(self::atLeastOnce())
            ->method('get')
            ->willReturnMap([
                ['config', $config],
                ['foo', $cache],
                [PostmarkAdminClient::class, $client],
            ]);
        $this->container
            ->expects(self::atLeastOnce())
            ->method('has')
            ->with('foo')
            ->willReturn(true);
        $senders = (new PermittedSendersFactory())($this->container);
        self::assertInstanceOf(PermittedSenders::class, $senders);
    }
}
