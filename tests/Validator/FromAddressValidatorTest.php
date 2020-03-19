<?php
declare(strict_types=1);

namespace Netglue\MailTest\Postmark\Validator;

use Laminas\Mail\Message;
use Netglue\Mail\Postmark\PermittedSenders;
use Netglue\Mail\Postmark\Validator\FromAddressValidator;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;

class FromAddressValidatorTest extends TestCase
{
    /** @var PermittedSenders|ObjectProphecy */
    private $permittedSenders;

    protected function setUp() : void
    {
        parent::setUp();
        $this->permittedSenders = $this->prophesize(PermittedSenders::class);
    }

    private function subject() : FromAddressValidator
    {
        return new FromAddressValidator($this->permittedSenders->reveal());
    }

    public function testNonEmailIsInvalid() : void
    {
        $v = $this->subject();
        $this->assertFalse($v->isValid('foo'));
        $this->assertArrayHasKey(FromAddressValidator::NOT_MESSAGE, $v->getMessages());
    }

    public function testMessageWithoutFromIsInvalid() : void
    {
        $v = $this->subject();
        $this->assertFalse($v->isValid(new Message()));
        $this->assertArrayHasKey(FromAddressValidator::MISSING_FROM, $v->getMessages());
    }

    public function testInvalidWhenFromIsNotAPermittedSender() : void
    {
        $message = new Message();
        $message->setFrom('me@example.com');
        $this->permittedSenders->isPermittedSender('me@example.com')
            ->shouldBeCalled()
            ->willReturn(false);
        $v = $this->subject();
        $this->assertFalse($v->isValid($message));
        $this->assertArrayHasKey(FromAddressValidator::NOT_PERMITTED, $v->getMessages());
        $error = $v->getMessages()[FromAddressValidator::NOT_PERMITTED];
        $this->assertStringContainsString('me@example.com', $error);
    }

    public function testIsValidWhenFromIsPermittedSender() : void
    {
        $message = new Message();
        $message->setFrom('me@example.com');
        $this->permittedSenders->isPermittedSender('me@example.com')
            ->shouldBeCalled()
            ->willReturn(true);
        $v = $this->subject();
        $this->assertTrue($v->isValid($message));
    }
}
