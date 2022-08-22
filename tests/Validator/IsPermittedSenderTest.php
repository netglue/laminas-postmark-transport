<?php

declare(strict_types=1);

namespace Netglue\MailTest\Postmark\Validator;

use Netglue\Mail\Postmark\PermittedSenders;
use Netglue\Mail\Postmark\Validator\IsPermittedSender;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IsPermittedSenderTest extends TestCase
{
    /** @var PermittedSenders&MockObject */
    private $permittedSenders;
    /** @var IsPermittedSender */
    private $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permittedSenders = $this->createMock(PermittedSenders::class);
        $this->validator = new IsPermittedSender($this->permittedSenders);
    }

    public function testNonEmailIsInvalid(): void
    {
        self::assertFalse($this->validator->isValid('foo'));
        self::assertArrayHasKey(IsPermittedSender::NOT_VALID_EMAIL, $this->validator->getMessages());
    }

    public function testNonStringValueIsInvalid(): void
    {
        self::assertFalse($this->validator->isValid(['not' => 'a string']));
        self::assertArrayHasKey(IsPermittedSender::NOT_STRING, $this->validator->getMessages());
    }

    public function testInvalidWhenEmailIsNotAPermittedSender(): void
    {
        $this->permittedSenders
            ->expects(self::once())
            ->method('isPermittedSender')
            ->with('me@example.com')
            ->willReturn(false);
        self::assertFalse($this->validator->isValid('me@example.com'));
        self::assertArrayHasKey(IsPermittedSender::NOT_PERMITTED, $this->validator->getMessages());
        $error = $this->validator->getMessages()[IsPermittedSender::NOT_PERMITTED];
        self::assertStringContainsString('me@example.com', $error);
    }

    public function testIsValidWhenEmailIsPermittedSender(): void
    {
        $this->permittedSenders
            ->expects(self::once())
            ->method('isPermittedSender')
            ->with('me@example.com')
            ->willReturn(true);
        self::assertTrue($this->validator->isValid('me@example.com'));
    }
}
