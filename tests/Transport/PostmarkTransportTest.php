<?php

declare(strict_types=1);

namespace Netglue\MailTest\Postmark\Transport;

use Generator;
use Laminas\Mail\Message as LaminasMessage;
use Laminas\Mime\Message;
use Laminas\Mime\Mime;
use Laminas\Mime\Part;
use Laminas\Validator\ValidatorChain;
use Netglue\Mail\Exception\InvalidArgument;
use Netglue\Mail\Postmark\Exception\MessageValidationFailure;
use Netglue\Mail\Postmark\Message\PostmarkMessage;
use Netglue\Mail\Postmark\Transport\PostmarkTransport;
use Netglue\Mail\Postmark\Validator\MessageValidator;
use Netglue\Mail\Postmark\Value\LinkTracking;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Postmark\Models\PostmarkAttachment;
use Postmark\PostmarkClient;

use function fopen;
use function is_array;
use function reset;
use function sprintf;

class PostmarkTransportTest extends TestCase
{
    /** @var PostmarkClient&MockObject */
    private $client;

    private MessageValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PostmarkClient::class);
        $this->validator = new MessageValidator();
    }

    private function transport(): PostmarkTransport
    {
        return new PostmarkTransport($this->client, $this->validator);
    }

    private function networkTransport(): PostmarkTransport
    {
        $client = new PostmarkClient('POSTMARK_API_TEST');

        return new PostmarkTransport($client, new ValidatorChain());
    }

    private function messageWillNotBeSent(): void
    {
        $this->client
            ->expects(self::never())
            ->method('sendEmail');
    }

    public function testThatAnInvalidMessageIsExceptional(): void
    {
        $message = new PostmarkMessage();
        $this->messageWillNotBeSent();
        $this->expectException(MessageValidationFailure::class);
        $this->expectExceptionMessage('The message provided does not pass Postmark validation rules');
        $this->transport()->send($message);
    }

    public function testThatAFromAddressIsRequired(): void
    {
        $message = new PostmarkMessage();
        $message->setTo('you@example.com');
        $transport = new PostmarkTransport($this->client, new ValidatorChain());
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('A from address has not been specified');
        $transport->send($message);
    }

    /** @return Generator<string, array{0: PostmarkMessage}> */
    public function getMessage(): Generator
    {
        $textContent = 'Text Body';
        $htmlContent = '<p>HTML Body</p>';

        $textPart = new Part();
        $textPart->type = Mime::TYPE_TEXT;
        $textPart->charset = 'utf-8';
        $textPart->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
        $textPart->setContent($textContent);

        $htmlPart = new Part();
        $htmlPart->type = Mime::TYPE_HTML;
        $htmlPart->charset = 'utf-8';
        $htmlPart->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
        $htmlPart->setContent($htmlContent);

        $file = new Part(fopen(__DIR__ . '/asset/text.txt', 'rb'));
        $file->type = 'text/plain';
        $file->filename = 'whatever.txt';
        $file->disposition = Mime::DISPOSITION_ATTACHMENT;
        $file->encoding = Mime::ENCODING_BASE64;

        $mime = new Message();
        $mime->addPart($textPart);
        $mime->addPart($htmlPart);
        $mime->addPart($file);

        $meta = ['key' => 'value'];
        $message = new PostmarkMessage();
        $message->addTo('to@example.com');
        $message->addCc('cc@example.com');
        $message->addCc('cc2@example.com');
        $message->addBcc('bcc@example.com');
        $message->setFrom('from@example.com');
        $message->addReplyTo('reply@example.com');
        $message->setSubject('Subject');
        $message->setTag('TAG');
        $message->setMetaData($meta);
        $message->setLinkTrackingMode(LinkTracking::htmlOnly());
        $message->getHeaders()->addHeaderLine('X-Foo', 'bar');
        $message->trackOpens(false);
        $message->setBody($mime);

        yield 'Message 1' => [$message];
    }

    /** @param mixed[] $headers */
    private function assertHeaderArrayContainsHeaderName(array $headers, string $headerName): void
    {
        self::assertArrayHasKey($headerName, $headers, sprintf(
            'The header named %s was not not found in the input',
            $headerName,
        ));
    }

    /**
     * @param mixed[] $headers
     * @param mixed   $expect
     */
    private function assertHeaderEqualsValue(array $headers, string $headerName, $expect): void
    {
        $this->assertHeaderArrayContainsHeaderName($headers, $headerName);
        self::assertEquals($expect, $headers[$headerName]);
    }

    /** @dataProvider getMessage */
    public function testClientIsProvidedWithExpectedValues(PostmarkMessage $message): void
    {
        $this->client
            ->expects(self::once())
            ->method('sendEmail')
            ->with(
                self::equalTo('<from@example.com>'),
                self::equalTo('<to@example.com>'),
                self::equalTo('Subject'),
                self::equalTo('<p>HTML Body</p>'),
                self::equalTo('Text Body'),
                self::equalTo('TAG'),
                self::isFalse(),
                self::equalTo('<reply@example.com>'),
                self::equalTo('<cc@example.com>,<cc2@example.com>'),
                self::equalTo('<bcc@example.com>'),
                self::callback(function (array $headers): bool {
                    $this->assertHeaderEqualsValue($headers, 'X-Foo', 'bar');

                    return true;
                }),
                self::callback(function ($files) {
                    $this->assertIsArray($files);
                    $this->assertCount(1, $files);
                    $first = reset($files);
                    $this->assertInstanceOf(PostmarkAttachment::class, $first);

                    return true;
                }),
                self::equalTo(LinkTracking::HTML),
                self::equalTo(['key' => 'value']),
            );

        $this->transport()->send($message);
    }

    public function testThatARegularPlainTextMessageIsOk(): void
    {
        $message = new LaminasMessage();
        $message->setFrom('a@example.com');
        $message->setTo('b@example.com');
        $message->setSubject('Foo');
        $message->setBody('Text');

        $this->client
            ->expects(self::once())
            ->method('sendEmail')
            ->with(
                '<a@example.com>',
                '<b@example.com>',
                'Foo',
                null,
                'Text',
                null,
                true,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
            );

        $this->transport()->send($message);
    }

    public function testThatARegularHtmlMessageIsOk(): void
    {
        $message = new LaminasMessage();
        $message->setFrom('a@example.com');
        $message->setTo('b@example.com');
        $message->setSubject('Foo');

        $htmlContent = '<p>HTML Body</p>';

        $htmlPart = new Part();
        $htmlPart->type = Mime::TYPE_HTML;
        $htmlPart->charset = 'utf-8';
        $htmlPart->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
        $htmlPart->setContent($htmlContent);

        $mime = new Message();
        $mime->addPart($htmlPart);

        $message->setBody($mime);

        $this->client
            ->expects(self::once())
            ->method('sendEmail')
            ->with(
                '<a@example.com>',
                '<b@example.com>',
                'Foo',
                '<p>HTML Body</p>',
                null,
                null,
                true,
                null,
                null,
                null,
                self::isType('array'),
                null,
                null,
                null,
            );

        $this->transport()->send($message);
    }

    public function testThatAMimeEncodedPlainTextMessageIsOk(): void
    {
        $message = new LaminasMessage();
        $message->setFrom('a@example.com');
        $message->setTo('b@example.com');
        $message->setSubject('Foo');

        $part = new Part();
        $part->type = Mime::TYPE_TEXT;
        $part->charset = 'utf-8';
        $part->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
        $part->setContent('Some Text');

        $mime = new Message();
        $mime->addPart($part);

        $message->setBody($mime);

        $this->client
            ->expects(self::once())
            ->method('sendEmail')
            ->with(
                '<a@example.com>',
                '<b@example.com>',
                'Foo',
                null,
                'Some Text',
                null,
                true,
                null,
                null,
                null,
                self::isType('array'),
                null,
                null,
                null,
            );

        $this->transport()->send($message);
    }

    public function testThatTheContentTypeHeaderIsStripped(): void
    {
        $message = new LaminasMessage();
        $message->setFrom('a@example.com');
        $message->setTo('b@example.com');
        $message->setSubject('Foo');

        $part = new Part();
        $part->type = Mime::TYPE_TEXT;
        $part->charset = 'utf-8';
        $part->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
        $part->setContent('Some Text');

        $mime = new Message();
        $mime->addPart($part);

        $message->setBody($mime);

        $headers = $message->getHeaders();
        self::assertTrue($headers->has('Date'));

        $this->client
            ->expects(self::once())
            ->method('sendEmail')
            ->with(
                '<a@example.com>',
                '<b@example.com>',
                'Foo',
                null,
                'Some Text',
                null,
                true,
                null,
                null,
                null,
                self::callback(function (array $headerArray): bool {
                    $this->assertArrayNotHasKey('Content-Type', $headerArray);

                    return true;
                }),
                null,
                null,
                null,
            );

        $this->transport()->send($message);
    }

    public function testThatTheDateHeaderIsStripped(): void
    {
        $message = new LaminasMessage();
        $message->setFrom('a@example.com');
        $message->setTo('b@example.com');
        $message->setSubject('Foo');
        $message->setBody('Text');

        $headers = $message->getHeaders();
        self::assertTrue($headers->has('Date'));

        $this->client
            ->expects(self::once())
            ->method('sendEmail')
            ->with(
                '<a@example.com>',
                '<b@example.com>',
                'Foo',
                null,
                'Text',
                null,
                true,
                null,
                null,
                null,
                self::callback(function ($headerArray) {
                    if (is_array($headerArray)) {
                        $this->assertArrayNotHasKey('Date', $headerArray);
                    }

                    return true;
                }),
                null,
                null,
                null,
            );

        $this->transport()->send($message);
    }

    public function testThatTheReplyToHeaderIsStripped(): void
    {
        $message = new LaminasMessage();
        $message->setFrom('a@example.com');
        $message->setTo('b@example.com');
        $message->setSubject('Foo');
        $message->setBody('Text');

        self::assertFalse($message->getHeaders()->has('Reply-To'));

        $message->setReplyTo('c@example.com');

        self::assertTrue($message->getHeaders()->has('Reply-To'));

        $this->client
            ->expects(self::once())
            ->method('sendEmail')
            ->with(
                '<a@example.com>',
                '<b@example.com>',
                'Foo',
                null,
                'Text',
                null,
                true,
                '<c@example.com>',
                null,
                null,
                self::callback(function ($headerArray) {
                    if (is_array($headerArray)) {
                        $this->assertArrayNotHasKey('Reply-To', $headerArray);
                    }

                    return true;
                }),
                null,
                null,
                null,
            );

        $this->transport()->send($message);
    }

    public function testThatPlainTextMessageCanBeSent(): void
    {
        $transport = $this->networkTransport();

        $message = new LaminasMessage();
        $message->setFrom('a@example.com');
        $message->setTo('b@example.com');
        $message->setSubject('Foo');
        $message->setBody('Text');

        $transport->send($message);
        self::assertTrue(true);
    }

    /** @dataProvider getMessage */
    public function testThatAFullFeaturedMessageCanBeSent(PostmarkMessage $message): void
    {
        $transport = $this->networkTransport();
        $transport->send($message);
        self::assertTrue(true);
    }

    public function testThatMultipartMimeEncodedContentIsNotSentToApiClient(): void
    {
        $markup = '<p class="foo">L’bar</p>';

        $message = new LaminasMessage();
        $message->setFrom('a@example.com');
        $message->setTo('b@example.com');
        $message->setSubject('Foo');

        $part = new Part();
        $part->type = Mime::TYPE_HTML;
        $part->charset = 'utf-8';
        $part->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
        $part->setContent($markup);

        $mime = new Message();
        $mime->addPart($part);

        $message->setBody($mime);

        $headers = $message->getHeaders();
        self::assertTrue($headers->has('Date'));

        $this->client
            ->expects(self::once())
            ->method('sendEmail')
            ->with(
                '<a@example.com>',
                '<b@example.com>',
                'Foo',
                $markup,
                null,
                null,
                true,
                null,
                null,
                null,
                self::isType('array'),
                null,
                null,
                null,
            );

        $this->transport()->send($message);
    }
}
