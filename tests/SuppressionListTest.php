<?php

declare(strict_types=1);

namespace Netglue\MailTest\Postmark;

use Netglue\Mail\Postmark\Exception\NotAnEmailAddress;
use Netglue\Mail\Postmark\SuppressionList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Postmark\PostmarkClient;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use UnexpectedValueException;

class SuppressionListTest extends TestCase
{
    private CacheItemPoolInterface $cache;
    /** @var MockObject&PostmarkClient */
    private $client;
    private SuppressionList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PostmarkClient::class);
        $this->cache = new ArrayAdapter();
        $this->list = new SuppressionList($this->client, $this->cache);
    }

    public function testAnInvalidEmailAddressWillCauseAnExceptionWhenCheckingForSuppression(): void
    {
        $this->expectException(NotAnEmailAddress::class);
        $this->list->isSuppressed('bad-news');
    }

    public function testThatWhenAnEmailIsPresentInTheCacheTheApiWillNotBeQueried(): void
    {
        $item = $this->cache->getItem(SuppressionList::SUPPRESSION_LIST_CACHE_KEY);
        $item->set(['me@example.com']);
        $this->cache->save($item);

        $this->client->expects(self::never())->method('getSuppressions');

        self::assertTrue($this->list->isSuppressed('me@example.com'));
    }

    public function testPositiveSuppression(): void
    {
        $this->client
            ->expects(self::once())
            ->method('getSuppressions')
            ->with(null, null, null, null, 'me@example.com')
            ->willReturn([
                'Suppressions' => [
                    ['EmailAddress' => 'me@example.com'],
                ],
            ]);
        self::assertTrue($this->list->isSuppressed('me@example.com'));
    }

    public function testNegativeSuppression(): void
    {
        $this->client
            ->expects(self::once())
            ->method('getSuppressions')
            ->with(null, null, null, null, 'me@example.com')
            ->willReturn([
                'Suppressions' => [],
            ]);
        self::assertFalse($this->list->isSuppressed('me@example.com'));
    }

    public function testThatSuppressedEmailsFoundInTheApiAreCached(): void
    {
        $expect = ['me@example.com'];
        $item = $this->cache->getItem(SuppressionList::SUPPRESSION_LIST_CACHE_KEY);
        self::assertNotEquals($expect, $item->get());

        $this->client
            ->expects(self::once())
            ->method('getSuppressions')
            ->with(null, null, null, null, 'me@example.com')
            ->willReturn([
                'Suppressions' => [
                    ['EmailAddress' => 'me@example.com'],
                ],
            ]);

        self::assertTrue($this->list->isSuppressed('me@example.com'));
        $item = $this->cache->getItem(SuppressionList::SUPPRESSION_LIST_CACHE_KEY);
        self::assertEquals($expect, $item->get());
    }

    public function testThatAnExceptionIsThrownWhenTheApiResponseDoesNotContainAnIterableInTheSuppressionsKey(): void
    {
        $this->client
            ->expects(self::once())
            ->method('getSuppressions')
            ->with(null, null, null, null, 'me@example.com')
            ->willReturn(['Suppressions' => 'foo']);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Expected the Suppression list from the API to contain an array in its "Suppressions" property');
        $this->list->isSuppressed('me@example.com');
    }

    public function testAnExceptionIsThrownWhenASuppressionItemDoesNotContainAnEmailAddress(): void
    {
        $this->client
            ->expects(self::once())
            ->method('getSuppressions')
            ->with(null, null, null, null, 'me@example.com')
            ->willReturn(['Suppressions' => [['foo' => 'bar']]]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Expected each suppression item to have a string EmailAddress attribute');
        $this->list->isSuppressed('me@example.com');
    }

    public function testThatSeedingTheCacheBehavesAsExpected(): void
    {
        $this->client
            ->expects(self::once())
            ->method('getSuppressions')
            ->with(null, null, null, null, null)
            ->willReturn([
                'Suppressions' => [
                    ['EmailAddress' => 'me@example.com'],
                    ['EmailAddress' => 'you@example.com'],
                ],
            ]);

        $this->list->seedSuppressionListCache();

        $expect = [
            'me@example.com',
            'you@example.com',
        ];

        $item = $this->cache->getItem(SuppressionList::SUPPRESSION_LIST_CACHE_KEY);
        self::assertEquals($expect, $item->get());
    }
}
