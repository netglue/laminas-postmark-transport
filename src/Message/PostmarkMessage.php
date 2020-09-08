<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Message;

use Laminas\Mail\Message;
use Netglue\Mail\Message\KeyValueMetadata;
use Netglue\Mail\Message\KeyValueMetadataBehaviour;
use Netglue\Mail\Message\OpenTracking;
use Netglue\Mail\Message\OpenTrackingBehaviour;
use Netglue\Mail\Message\TaggableMessage;
use Netglue\Mail\Message\TaggableMessageBehaviour;
use Netglue\Mail\Postmark\Value\LinkTracking;

class PostmarkMessage extends Message implements TaggableMessage, KeyValueMetadata, OpenTracking, PostmarkLinkTracking
{
    use OpenTrackingBehaviour;
    use TaggableMessageBehaviour;
    use KeyValueMetadataBehaviour;
    use PostmarkLinkTrackingBehaviour;

    public const DEFAULT_TRACKING_MODE = LinkTracking::HTML;
}
