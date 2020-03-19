<?php
declare(strict_types=1);

namespace Netglue\Mail\Postmark\Validator;

use Laminas\Mail\Message;
use Netglue\Mail\Message\KeyValueMetadata;
use Netglue\Mail\Validator\IsMessage;
use function assert;
use function is_scalar;
use function strlen;

class MetaDataValidator extends IsMessage
{
    public const MAX_METADATA_KEY_LENGTH = 20;
    public const MAX_METADATA_VALUE_LENGTH = 80;

    public const KEY_LENGTH_EXCEEDED = 'MetaDataKeyTooLong';
    public const VALUE_LENGTH_EXCEEDED = 'MetaDataValueTooLong';

    /** @var string[] */
    protected $messageTemplates = [
        self::NOT_MESSAGE => 'Expected a Message object',
        self::KEY_LENGTH_EXCEEDED => 'The metadata key "%key%" is too long. It is %length% characters but should not exceed %max%',
        self::VALUE_LENGTH_EXCEEDED => 'The metadata value for "%key%" is too long. It is %length% characters but should not exceed %max%',
    ];

    /** @var string|null */
    protected $key;

    /** @var int|null */
    protected $length;

    /** @var int|null */
    protected $max;

    /** @var string[] */
    protected $messageVariables = [
        'key' => 'key',
        'length' => 'length',
        'max' => 'max',
    ];

    /** @inheritDoc */
    public function isValid($value) : bool
    {
        if (! parent::isValid($value)) {
            return false;
        }

        assert($value instanceof Message);

        if (! $value instanceof KeyValueMetadata) {
            return true;
        }

        foreach ($value->getMetaData() as $metaKey => $metaValue) {
            if ($this->validateMetaData($metaKey, $metaValue)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /** @param mixed $value */
    private function validateMetaData(string $key, $value) : bool
    {
        $this->key = $key;
        if (strlen($key) > self::MAX_METADATA_KEY_LENGTH) {
            $this->max = self::MAX_METADATA_KEY_LENGTH;
            $this->length = strlen($key);
            $this->error(self::KEY_LENGTH_EXCEEDED);

            return false;
        }

        if (is_scalar($value) && strlen((string) $value) > self::MAX_METADATA_VALUE_LENGTH) {
            $this->max = self::MAX_METADATA_VALUE_LENGTH;
            $this->length = strlen((string) $value);
            $this->error(self::VALUE_LENGTH_EXCEEDED);

            return false;
        }

        return true;
    }
}
