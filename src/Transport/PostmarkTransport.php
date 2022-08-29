<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark\Transport;

use Laminas\Mail\Address\AddressInterface;
use Laminas\Mail\AddressList;
use Laminas\Mail\Header\HeaderInterface;
use Laminas\Mail\Message;
use Laminas\Mail\Transport\TransportInterface;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Mime;
use Laminas\Validator\ValidatorInterface;
use Netglue\Mail\Exception\InvalidArgument;
use Netglue\Mail\Message\KeyValueMetadata;
use Netglue\Mail\Message\OpenTracking;
use Netglue\Mail\Message\TaggableMessage;
use Netglue\Mail\Postmark\Exception\MessageValidationFailure;
use Netglue\Mail\Postmark\Message\PostmarkLinkTracking;
use Postmark\Models\PostmarkAttachment;
use Postmark\PostmarkClient;

use function array_filter;
use function array_map;
use function assert;
use function base64_encode;
use function implode;
use function in_array;
use function is_string;
use function iterator_to_array;

class PostmarkTransport implements TransportInterface
{
    public const MAX_RECIPIENT_COUNT = 50;

    private PostmarkClient $client;

    private ValidatorInterface $messageValidator;

    public function __construct(PostmarkClient $client, ValidatorInterface $messageValidator)
    {
        $this->client = $client;
        $this->messageValidator = $messageValidator;
    }

    public function send(Message $message): void
    {
        if (! $this->messageValidator->isValid($message)) {
            throw MessageValidationFailure::withValidator($this->messageValidator);
        }

        $body = $message->getBody();

        $textContent = $htmlContent = null;

        if (is_string($body)) {
            $textContent = $body;
        }

        if ($body instanceof MimeMessage) {
            $textContent = $this->extractTextBody($body);
            $htmlContent = $this->extractHtmlBody($body);
        }

        $to = $this->toAddressList($message->getTo());
        assert(is_string($to));

        $this->client->sendEmail(
            $this->fromAddress($message),
            $to,
            (string) $message->getSubject(),
            $htmlContent,
            $textContent,
            $this->getTag($message),
            $message instanceof OpenTracking ? $message->shouldTrackOpens() : true,
            $this->replyTo($message),
            $this->toAddressList($message->getCc()),
            $this->toAddressList($message->getBcc()),
            $this->extractHeaders($message),
            $this->extractAttachments($message),
            $this->linkTrackingDirective($message),
            $this->extractMetadata($message),
        );
    }

    private function fromAddress(Message $message): string
    {
        $from = $message->getFrom();
        $fromAddress = $from->rewind();
        if (! $fromAddress instanceof AddressInterface) {
            throw new InvalidArgument(
                'A from address has not been specified',
            );
        }

        return $fromAddress->toString();
    }

    private function toAddressList(AddressList $list): ?string
    {
        $emails = array_map(
            static fn (AddressInterface $address): string => $address->toString(),
            iterator_to_array($list),
        );

        $value = implode(',', $emails);

        return empty($value) ? null : $value;
    }

    private function extractHtmlBody(MimeMessage $message): ?string
    {
        foreach ($message->getParts() as $part) {
            if ($part->type === 'text/html' && $part->disposition !== Mime::DISPOSITION_ATTACHMENT) {
                return $part->getRawContent();
            }
        }

        return null;
    }

    private function extractTextBody(MimeMessage $message): ?string
    {
        foreach ($message->getParts() as $part) {
            if ($part->type === 'text/plain' && $part->disposition !== Mime::DISPOSITION_ATTACHMENT) {
                return $part->getRawContent();
            }
        }

        return null;
    }

    private function getTag(Message $message): ?string
    {
        return $message instanceof TaggableMessage ? $message->getTag() : null;
    }

    private function replyTo(Message $message): ?string
    {
        $list = iterator_to_array($message->getReplyTo(), false);
        $address = $list[0] ?? null;

        return $address instanceof AddressInterface ? $address->toString() : null;
    }

    /** @return string[] */
    private function extractHeaders(Message $message): ?array
    {
        $filtered = $this->filterHeaders($message);
        /**
         * The client can't handle multiple headers with the same name
         */
        $headers = [];
        foreach ($filtered as $header) {
            $headers[$header->getFieldName()] = $header->getFieldValue();
        }

        return $headers === [] ? null : $headers;
    }

    /** @return HeaderInterface[] */
    private function filterHeaders(Message $message): array
    {
        $headersToStrip = [
            'Bcc',
            'Cc',
            'From',
            'Reply-To',
            'Sender',
            'Subject',
            'To',
            'Date',
            'Content-Type',
        ];

        return array_filter(
            iterator_to_array($message->getHeaders(), false),
            static fn (HeaderInterface $header): bool => ! in_array($header->getFieldName(), $headersToStrip, true)
        );
    }

    /** @return list<PostmarkAttachment>|null */
    private function extractAttachments(Message $message): ?array
    {
        $body = $message->getBody();
        if (! $body instanceof MimeMessage) {
            return null;
        }

        $data = [];
        foreach ($body->getParts() as $part) {
            if ($part->disposition !== Mime::DISPOSITION_ATTACHMENT) {
                continue;
            }

            $postmarkAttachment = PostmarkAttachment::fromBase64EncodedData(
                base64_encode($part->getRawContent()),
                $part->filename,
                $part->type,
                $part->getId(),
            );
            assert($postmarkAttachment instanceof PostmarkAttachment);
            $data[] = $postmarkAttachment;
        }

        return $data === [] ? null : $data;
    }

    /** @return mixed[] */
    private function extractMetadata(Message $message): ?array
    {
        if (! $message instanceof KeyValueMetadata) {
            return null;
        }

        $data = $message->getMetaData();

        return $data === [] ? null : $data;
    }

    private function linkTrackingDirective(Message $message): ?string
    {
        if (! $message instanceof PostmarkLinkTracking) {
            return null;
        }

        return $message->linkTracking()->getValue();
    }
}
