<?php
declare(strict_types=1);

namespace Netglue\MailTest\Postmark\Validator;

use Laminas\Mail\Message;
use Netglue\Mail\Message\KeyValueMetadata;
use Netglue\Mail\Message\KeyValueMetadataBehaviour;
use Netglue\Mail\Postmark\Validator\MetaDataValidator;
use PHPUnit\Framework\TestCase;
use function sprintf;
use function str_repeat;

class MetaDataValidatorTest extends TestCase
{
    /** @var MetaDataValidator */
    private $validator;

    /** @var Message|KeyValueMetadata */
    private $message;

    protected function setUp() : void
    {
        parent::setUp();
        $this->validator = new MetaDataValidator();
        $this->message = new class extends Message implements KeyValueMetadata {
            use KeyValueMetadataBehaviour;
        };
    }

    public function testNonMessageIsInvalid() : void
    {
        $this->assertFalse($this->validator->isValid('oof'));
        $this->assertArrayHasKey(MetaDataValidator::NOT_MESSAGE, $this->validator->getMessages());
    }

    public function testMessageNotImplementingMetaDataInterfaceIsValid() : void
    {
        $msg = new Message();
        $this->assertTrue($this->validator->isValid($msg));
    }

    public function testEmptyMetadataIsValid() : void
    {
        $this->assertTrue($this->validator->isValid($this->message));
    }

    public function testAcceptableMetaData() : void
    {
        $meta = [
            'string' => 'string',
            'boolean' => true,
            'integer' => 100,
            'float' => 0.123,
        ];
        $this->message->setMetaData($meta);
        $this->assertTrue($this->validator->isValid($this->message));
    }

    public function testThatMetaKeyExceedingMaxLengthIsInvalid() : void
    {
        $key = str_repeat('a', MetaDataValidator::MAX_METADATA_KEY_LENGTH + 1);
        $this->message->setMetaData([$key => 'value']);
        $this->assertFalse($this->validator->isValid($this->message));
        $this->assertArrayHasKey(MetaDataValidator::KEY_LENGTH_EXCEEDED, $this->validator->getMessages());
        $message = $this->validator->getMessages()[MetaDataValidator::KEY_LENGTH_EXCEEDED];
        $this->assertStringContainsString(
            sprintf(
                'It is %d characters but should not exceed %d',
                MetaDataValidator::MAX_METADATA_KEY_LENGTH + 1,
                MetaDataValidator::MAX_METADATA_KEY_LENGTH
            ),
            $message
        );
        $this->assertStringContainsString(
            sprintf('"%s"', $key),
            $message
        );
    }

    public function testThatMetaDataValueExceedingMaxLengthIsInvalid() : void
    {
        $value = str_repeat('a', MetaDataValidator::MAX_METADATA_VALUE_LENGTH + 1);
        $this->message->setMetaData(['key' => $value]);
        $this->assertFalse($this->validator->isValid($this->message));
        $this->assertArrayHasKey(MetaDataValidator::VALUE_LENGTH_EXCEEDED, $this->validator->getMessages());
        $message = $this->validator->getMessages()[MetaDataValidator::VALUE_LENGTH_EXCEEDED];
        $this->assertStringContainsString(
            sprintf(
                'It is %d characters but should not exceed %d',
                MetaDataValidator::MAX_METADATA_VALUE_LENGTH + 1,
                MetaDataValidator::MAX_METADATA_VALUE_LENGTH
            ),
            $message
        );

        $this->assertStringContainsString(
            sprintf('"%s"', 'key'),
            $message
        );
    }
}
