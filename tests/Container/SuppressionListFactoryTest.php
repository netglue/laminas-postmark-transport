<?php

declare(strict_types=1);

namespace Netglue\MailTest\Postmark\Container;

use Netglue\Mail\Postmark\Container\SuppressionListFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Postmark\PostmarkClient;
use Psr\Container\ContainerInterface;
use RuntimeException;

class SuppressionListFactoryTest extends TestCase
{
    /** @var MockObject|ContainerInterface */
    private $container;

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
            ->willReturnMap([
                ['config', $config],
                [PostmarkClient::class, $this->createMock(PostmarkClient::class)],
            ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A cache service must be defined in your container configuration');
        (new SuppressionListFactory())($this->container);
    }
}
