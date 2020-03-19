<?php
declare(strict_types=1);

namespace Netglue\MailTest\Postmark\Message;

use Netglue\Mail\Postmark\Message\PostmarkMessage;
use Netglue\Mail\Postmark\Value\LinkTracking;
use PHPUnit\Framework\TestCase;

class PostmarkMessageTest extends TestCase
{
    public function testMetaCanBeAdded() : void
    {
        $message = new PostmarkMessage();
        $message->addMetaData('foo', 'bar');
        $this->assertEquals(['foo' => 'bar'], $message->getMetaData());
    }

    public function testLinkTrackingHasADefault() : void
    {
        $message = new PostmarkMessage();
        $this->assertNotNull($message->linkTracking());
    }

    public function testLinkTrackingModeCanBeChanged() : void
    {
        $mode = LinkTracking::htmlOnly();
        $message = new PostmarkMessage();
        $message->setLinkTrackingMode($mode);
        $this->assertEquals($mode, $message->linkTracking());
    }
}
