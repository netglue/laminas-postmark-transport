<?php

declare(strict_types=1);

namespace Netglue\MailTest\Postmark\Validator;

use Laminas\Mail\Message;
use Netglue\Mail\Message\KeyValueMetadata;
use Netglue\Mail\Message\KeyValueMetadataBehaviour;
use Netglue\Mail\Postmark\Validator\MetaDataValidator;
use PHPUnit\Framework\TestCase;

use function assert;
use function method_exists;
use function sprintf;
use function str_repeat;

class MetaDataValidatorTest extends TestCase
{
    private MetaDataValidator $validator;

    /** @var Message&KeyValueMetadata */
    private $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new MetaDataValidator();
        $this->message = new class extends Message implements KeyValueMetadata {
            use KeyValueMetadataBehaviour;
        };
    }

    public function testNonMessageIsInvalid(): void
    {
        self::assertFalse($this->validator->isValid('oof'));
        self::assertArrayHasKey(MetaDataValidator::NOT_MESSAGE, $this->validator->getMessages());
    }

    public function testMessageNotImplementingMetaDataInterfaceIsValid(): void
    {
        $msg = new Message();
        self::assertTrue($this->validator->isValid($msg));
    }

    public function testEmptyMetadataIsValid(): void
    {
        self::assertTrue($this->validator->isValid($this->message));
    }

    public function testAcceptableMetaData(): void
    {
        $meta = [
            'string' => 'string',
            'boolean' => true,
            'integer' => 100,
            'float' => 0.123,
        ];
        assert(method_exists($this->message, 'setMetaData'));
        $this->message->setMetaData($meta);
        self::assertTrue($this->validator->isValid($this->message));
    }

    public function testThatMetaKeyExceedingMaxLengthIsInvalid(): void
    {
        $key = str_repeat('a', MetaDataValidator::MAX_METADATA_KEY_LENGTH + 1);
        assert(method_exists($this->message, 'setMetaData'));
        $this->message->setMetaData([$key => 'value']);
        self::assertFalse($this->validator->isValid($this->message));
        self::assertArrayHasKey(MetaDataValidator::KEY_LENGTH_EXCEEDED, $this->validator->getMessages());
        $message = $this->validator->getMessages()[MetaDataValidator::KEY_LENGTH_EXCEEDED];
        self::assertStringContainsString(
            sprintf(
                'It is %d characters but should not exceed %d',
                MetaDataValidator::MAX_METADATA_KEY_LENGTH + 1,
                MetaDataValidator::MAX_METADATA_KEY_LENGTH,
            ),
            $message,
        );
        self::assertStringContainsString(
            sprintf('"%s"', $key),
            $message,
        );
    }

    public function testThatMetaDataValueExceedingMaxLengthIsInvalid(): void
    {
        $value = str_repeat('a', MetaDataValidator::MAX_METADATA_VALUE_LENGTH + 1);
        assert(method_exists($this->message, 'setMetaData'));
        $this->message->setMetaData(['key' => $value]);
        self::assertFalse($this->validator->isValid($this->message));
        self::assertArrayHasKey(MetaDataValidator::VALUE_LENGTH_EXCEEDED, $this->validator->getMessages());
        $message = $this->validator->getMessages()[MetaDataValidator::VALUE_LENGTH_EXCEEDED];
        self::assertStringContainsString(
            sprintf(
                'It is %d characters but should not exceed %d',
                MetaDataValidator::MAX_METADATA_VALUE_LENGTH + 1,
                MetaDataValidator::MAX_METADATA_VALUE_LENGTH,
            ),
            $message,
        );

        self::assertStringContainsString(
            sprintf('"%s"', 'key'),
            $message,
        );
    }
}
