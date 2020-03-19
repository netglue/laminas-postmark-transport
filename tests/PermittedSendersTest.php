<?php
declare(strict_types=1);

namespace Netglue\MailTest\Postmark;

use InvalidArgumentException;
use Laminas\Cache\Psr\CacheItemPool\CacheItemPoolDecorator;
use Laminas\Cache\Storage\Adapter\Memory;
use Laminas\Cache\Storage\Plugin\Serializer;
use Netglue\Mail\Postmark\PermittedSenders;
use PHPUnit\Framework\TestCase;
use Postmark\PostmarkAdminClient;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use RuntimeException;

class PermittedSendersTest extends TestCase
{
    /** @var CacheItemPoolDecorator */
    private $cache;
    /** @var PostmarkAdminClient|ObjectProphecy */
    private $client;

    protected function setUp() : void
    {
        parent::setUp();
        $this->client = $this->prophesize(PostmarkAdminClient::class);
        $adapter = new class extends Memory {
            public function __construct()
            {
                parent::__construct();
                $this->getCapabilities()->setStaticTtl($this->capabilityMarker, true);
            }
        };
        $adapter->addPlugin(new Serializer());
        $this->cache = new CacheItemPoolDecorator($adapter);
    }

    private function subject() : PermittedSenders
    {
        return new PermittedSenders(
            $this->client->reveal(),
            $this->cache
        );
    }

    public function testDomainRetrievalIsExceptionalWhenThereIsNoTotalCountInTheResponse() : void
    {
        $this->client->listDomains(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected the Postmark response to contain a "TotalCount" property');

        $this->subject()->domains();
    }

    public function testDomainRetrievalWillReturnAnEmptyListWhenTotalCountIsZero() : void
    {
        $this->client->listDomains(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn(['TotalCount' => 0]);

        $this->assertEquals([], $this->subject()->domains());
    }

    public function testExceptionThrownWhenResponseDoesNotHaveIterableDomainList() : void
    {
        $this->client->listDomains(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn(['TotalCount' => 1]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected the Postmark response to contain an array in the "Domains" property');

        $this->subject()->domains();
    }

    public function testExceptionThrownWhenResponseDomainListHasInvalidElement() : void
    {
        $this->client->listDomains(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn([
                'TotalCount' => 1,
                'Domains' => [['foo' => 'bar']],
            ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('One of the domains in the list does not have a name');

        $this->subject()->domains();
    }

    public function testThatValidDomainListWillBeNormalised() : void
    {
        $this->client->listDomains(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn([
                'TotalCount' => 2,
                'Domains' => [
                    ['Name' => 'example.com'],
                    ['Name' => 'WHATEVER.uk'],
                ],
            ]);
        $list = $this->subject()->domains();
        $this->assertContains('example.com', $list);
        $this->assertContains('whatever.uk', $list);
    }

    public function testThatDomainListIsCached() : void
    {
        $item = $this->cache->getItem(PermittedSenders::DOMAIN_LIST_CACHE_KEY);
        $this->assertFalse($item->isHit());
        $expect = [
            'example.com',
            'whatever.uk',
        ];
        $this->client->listDomains(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn([
                'TotalCount' => 2,
                'Domains' => [
                    ['Name' => 'example.com'],
                    ['Name' => 'WHATEVER.uk'],
                ],
            ]);
        $this->subject()->domains();
        $item = $this->cache->getItem(PermittedSenders::DOMAIN_LIST_CACHE_KEY);
        $this->assertTrue($item->isHit());
        $this->assertEquals($expect, $item->get());
        $this->assertEquals($expect, $this->subject()->domains());
    }

    public function testSenderRetrievalIsExceptionalWhenThereIsNoTotalCountInTheResponse() : void
    {
        $this->client->listSenderSignatures(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected the Postmark response to contain a "TotalCount" property');

        $this->subject()->senders();
    }

    public function testSenderRetrievalWillReturnAnEmptyListWhenTotalCountIsZero() : void
    {
        $this->client->listSenderSignatures(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn(['TotalCount' => 0]);

        $this->assertEquals([], $this->subject()->senders());
    }

    public function testExceptionThrownWhenResponseDoesNotHaveIterableSenderList() : void
    {
        $this->client->listSenderSignatures(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn(['TotalCount' => 1]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected the Postmark response to contain an array in the "SenderSignatures" property');

        $this->subject()->senders();
    }

    public function testExceptionThrownWhenResponseSenderListHasInvalidElement() : void
    {
        $this->client->listSenderSignatures(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn([
                'TotalCount' => 1,
                'SenderSignatures' => [['foo' => 'bar']],
            ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('One of the senders in the list does not have an email address');

        $this->subject()->senders();
    }

    public function testThatValidSenderListWillBeNormalised() : void
    {
        $this->client->listSenderSignatures(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn([
                'TotalCount' => 2,
                'SenderSignatures' => [
                    ['EmailAddress' => 'ME@example.com'],
                    ['EmailAddress' => 'you@WHATEVER.uk'],
                ],
            ]);
        $list = $this->subject()->senders();
        $this->assertContains('me@example.com', $list);
        $this->assertContains('you@whatever.uk', $list);
    }

    public function testThatSenderListIsCached() : void
    {
        $item = $this->cache->getItem(PermittedSenders::SENDER_LIST_CACHE_KEY);
        $this->assertFalse($item->isHit());
        $expect = [
            'me@example.com',
            'you@whatever.uk',
        ];
        $this->client->listSenderSignatures(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn([
                'TotalCount' => 2,
                'SenderSignatures' => [
                    ['EmailAddress' => 'me@example.com'],
                    ['EmailAddress' => 'you@WHATEVER.uk'],
                ],
            ]);
        $this->subject()->senders();
        $item = $this->cache->getItem(PermittedSenders::SENDER_LIST_CACHE_KEY);
        $this->assertTrue($item->isHit());
        $this->assertEquals($expect, $item->get());
        $this->assertEquals($expect, $this->subject()->senders());
    }

    public function testExceptionThrownTryingToMatchAnEmptyString() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A non empty string is required');
        $this->subject()->isPermittedSender('');
    }

    public function testExceptionThrownWhenTryingToMatchANonEmailAndNonHostname() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The value provided is neither a valid email address, nor a valid hostname');
        $this->subject()->isPermittedSender('what?');
    }

    public function testThatGivenAnEmailAddressTheSenderListWillBeConsulted() : void
    {
        $this->client->listSenderSignatures(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn([
                'TotalCount' => 1,
                'SenderSignatures' => [
                    ['EmailAddress' => 'me@example.com'],
                ],
            ]);
        $this->assertTrue($this->subject()->isPermittedSender('ME@ExamPlE.CoM'));
    }

    public function testThatDomainListIsConsultedWhenSignaturesAreEmpty() : void
    {
        $this->client->listSenderSignatures(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn(['TotalCount' => 0, 'SenderSignatures' => []]);
        $this->client->listDomains(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn(['TotalCount' => 1, 'Domains' => [['Name' => 'example.com']]]);
        $this->assertTrue($this->subject()->isPermittedSender('ME@ExamPlE.CoM'));
    }

    public function testThatOnlyDomainListIsConsultedWhenArgumentIsAHostname() : void
    {
        $this->client->listSenderSignatures(Argument::any())
            ->shouldNotBeCalled();
        $this->client->listDomains(Argument::type('integer'), Argument::type('integer'))
            ->shouldBeCalled()
            ->willReturn(['TotalCount' => 1, 'Domains' => [['Name' => 'example.com']]]);
        $this->assertTrue($this->subject()->isPermittedSender('ExamPlE.CoM'));
    }
}
