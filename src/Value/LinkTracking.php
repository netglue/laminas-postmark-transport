<?php
declare(strict_types=1);

namespace Netglue\Mail\Postmark\Value;

use MyCLabs\Enum\Enum;

final class LinkTracking extends Enum
{
    public const NONE = 'None';
    public const BOTH = 'HtmlAndText';
    public const TEXT = 'TextOnly';
    public const HTML = 'HtmlOnly';

    public static function none() : self
    {
        return new static(self::NONE);
    }

    public static function htmlAndText() : self
    {
        return new static(self::BOTH);
    }

    public static function textOnly() : self
    {
        return new static(self::TEXT);
    }

    public static function htmlOnly() : self
    {
        return new static(self::HTML);
    }
}
