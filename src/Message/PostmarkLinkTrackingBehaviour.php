<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Message;

use Netglue\Mail\Postmark\Value\LinkTracking;

trait PostmarkLinkTrackingBehaviour
{
    /** @var LinkTracking */
    private $linkTracking;

    public function setLinkTrackingMode(LinkTracking $tracking): void
    {
        $this->linkTracking = $tracking;
    }

    public function linkTracking(): LinkTracking
    {
        if (! $this->linkTracking) {
            return new LinkTracking(PostmarkMessage::DEFAULT_TRACKING_MODE);
        }

        return $this->linkTracking;
    }
}
