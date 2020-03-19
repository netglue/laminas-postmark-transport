<?php
declare(strict_types=1);

namespace Netglue\MailTest\Postmark\Container;

use Netglue\Mail\Postmark\Container\PermittedSendersFactory;
use PHPUnit\Framework\TestCase;
use Postmark\PostmarkAdminClient;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

class PermittedSendersFactoryTest extends TestCase
{
    /** @var ObjectProphecy|ContainerInterface */
    private $container;

    protected function setUp() : void
    {
        parent::setUp();
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function testThatAnExceptionIsThrownWhenThereIsNoCacheServiceConfigured() : void
    {
        $config = ['postmark' => []];
        $this->container->get('config')->willReturn($config)->shouldBeCalled();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('In order to use the permitted senders helper, a cache service must be defined');
        (new PermittedSendersFactory())($this->container->reveal());
    }

    public function testExceptionThrownWhenDefinedCacheIsNotAvailable() : void
    {
        $config = ['postmark' => ['cache_service' => 'foo']];
        $this->container->get('config')->willReturn($config)->shouldBeCalled();
        $this->container->has('foo')->shouldBeCalled()->willReturn(false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('In order to use the permitted senders helper, a cache service must be defined');
        (new PermittedSendersFactory())($this->container->reveal());
    }

    public function testHelperCanBeConstructed() : void
    {
        $cache = $this->prophesize(CacheItemPoolInterface::class)->reveal();
        $client = $this->prophesize(PostmarkAdminClient::class)->reveal();

        $config = ['postmark' => ['cache_service' => 'foo']];
        $this->container->get('config')->willReturn($config)->shouldBeCalled();
        $this->container->has('foo')->shouldBeCalled()->willReturn(true);
        $this->container->get('foo')->willReturn($cache)->shouldBeCalled();
        $this->container->get(PostmarkAdminClient::class)->willReturn($client)->shouldBeCalled();
        (new PermittedSendersFactory())($this->container->reveal());
        $this->addToAssertionCount(1);
    }
}
