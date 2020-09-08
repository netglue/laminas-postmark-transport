<?php
declare(strict_types=1);

namespace Netglue\MailTest\Postmark\Value;

use Netglue\Mail\Postmark\Value\LinkTracking;
use PHPUnit\Framework\TestCase;

class LinkTrackingTest extends TestCase
{
    public function testValuesAreAsExpected() : void
    {
        $data = [
            'None' => [LinkTracking::none(), 'None'],
            'HtmlAndText' => [LinkTracking::htmlAndText(), 'HtmlAndText'],
            'TextOnly' => [LinkTracking::textOnly(), 'TextOnly'],
            'HtmlOnly' => [LinkTracking::htmlOnly(), 'HtmlOnly'],
        ];
        foreach ($data as $datum) {
            self::assertSame($datum[1], $datum[0]->getValue());
        }
    }
}
