<?php

declare(strict_types=1);

namespace Netglue\MailTest\Postmark\Validator;

use Laminas\Mail\Message;
use Netglue\Mail\Postmark\PermittedSenders;
use Netglue\Mail\Postmark\Validator\FromAddressValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FromAddressValidatorTest extends TestCase
{
    /** @var PermittedSenders&MockObject */
    private $permittedSenders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permittedSenders = $this->createMock(PermittedSenders::class);
    }

    private function subject(): FromAddressValidator
    {
        return new FromAddressValidator($this->permittedSenders);
    }

    public function testNonEmailIsInvalid(): void
    {
        $v = $this->subject();
        self::assertFalse($v->isValid('foo'));
        self::assertArrayHasKey(FromAddressValidator::NOT_MESSAGE, $v->getMessages());
    }

    public function testMessageWithoutFromIsInvalid(): void
    {
        $v = $this->subject();
        self::assertFalse($v->isValid(new Message()));
        self::assertArrayHasKey(FromAddressValidator::MISSING_FROM, $v->getMessages());
    }

    public function testInvalidWhenFromIsNotAPermittedSender(): void
    {
        $message = new Message();
        $message->setFrom('me@example.com');
        $this->permittedSenders
            ->expects(self::atLeastOnce())
            ->method('isPermittedSender')
            ->with('me@example.com')
            ->willReturn(false);
        $v = $this->subject();
        self::assertFalse($v->isValid($message));
        self::assertArrayHasKey(FromAddressValidator::NOT_PERMITTED, $v->getMessages());
        $error = $v->getMessages()[FromAddressValidator::NOT_PERMITTED];
        self::assertIsString($error);
        self::assertStringContainsString('me@example.com', $error);
    }

    public function testIsValidWhenFromIsPermittedSender(): void
    {
        $message = new Message();
        $message->setFrom('me@example.com');
        $this->permittedSenders
            ->expects(self::atLeastOnce())
            ->method('isPermittedSender')
            ->with('me@example.com')
            ->willReturn(true);
        $v = $this->subject();
        self::assertTrue($v->isValid($message));
    }
}
