<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Message;

use Netglue\Mail\Postmark\Value\LinkTracking as LinkTrackingValue;

interface PostmarkLinkTracking
{
    public function linkTracking(): LinkTrackingValue;
}
