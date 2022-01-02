<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Value;

use MyCLabs\Enum\Enum;

/**
 * @psalm-immutable
 * @extends Enum<string>
 */
final class LinkTracking extends Enum
{
    public const NONE = 'None';
    public const BOTH = 'HtmlAndText';
    public const TEXT = 'TextOnly';
    public const HTML = 'HtmlOnly';

    public static function none(): self
    {
        return new self(self::NONE);
    }

    public static function htmlAndText(): self
    {
        return new self(self::BOTH);
    }

    public static function textOnly(): self
    {
        return new self(self::TEXT);
    }

    public static function htmlOnly(): self
    {
        return new self(self::HTML);
    }
}
