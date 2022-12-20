<?php

declare(strict_types=1);

namespace Netglue\MailTest\Postmark\Validator;

use Netglue\Mail\Postmark\SuppressionList;
use Netglue\Mail\Postmark\Validator\NotSuppressed;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NotSuppressedTest extends TestCase
{
    /** @var SuppressionList&MockObject */
    private $list;
    private NotSuppressed $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->list = $this->createMock(SuppressionList::class);
        $this->validator = new NotSuppressed($this->list);
    }

    public function testNonEmailIsInvalid(): void
    {
        self::assertFalse($this->validator->isValid('foo'));
        self::assertArrayHasKey(NotSuppressed::NOT_VALID_EMAIL, $this->validator->getMessages());
    }

    public function testNonStringValueIsInvalid(): void
    {
        self::assertFalse($this->validator->isValid(['not' => 'a string']));
        self::assertArrayHasKey(NotSuppressed::NOT_STRING, $this->validator->getMessages());
    }

    public function testInvalidWhenEmailIsSuppressed(): void
    {
        $this->list
            ->expects(self::once())
            ->method('isSuppressed')
            ->with('me@example.com')
            ->willReturn(true);
        self::assertFalse($this->validator->isValid('me@example.com'));
        self::assertArrayHasKey(NotSuppressed::IS_SUPPRESSED, $this->validator->getMessages());
        self::assertStringContainsString(
            'me@example.com',
            $this->validator->getMessages()[NotSuppressed::IS_SUPPRESSED],
        );
    }

    public function testIsValidWhenEmailIsNotSuppressed(): void
    {
        $this->list
            ->expects(self::once())
            ->method('isSuppressed')
            ->with('me@example.com')
            ->willReturn(false);
        self::assertTrue($this->validator->isValid('me@example.com'));
    }
}
