<?php
declare(strict_types=1);

namespace Netglue\MailTest\Postmark\Transport;

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
use PHPUnit\Framework\TestCase;
use Postmark\Models\PostmarkAttachment;
use Postmark\PostmarkClient;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use function assert;
use function fopen;
use function is_array;
use function reset;
use function sprintf;

class PostmarkTransportTest extends TestCase
{
    /** @var PostmarkClient|ObjectProphecy */
    private $client;

    /** @var MessageValidator */
    private $validator;

    protected function setUp() : void
    {
        parent::setUp();
        $this->client = $this->prophesize(PostmarkClient::class);
        $this->validator = new MessageValidator();
    }

    private function transport() : PostmarkTransport
    {
        return new PostmarkTransport($this->client->reveal(), $this->validator);
    }

    private function networkTransport() : PostmarkTransport
    {
        $client = new PostmarkClient('POSTMARK_API_TEST');

        return new PostmarkTransport($client, new ValidatorChain());
    }

    private function messageWillNotBeSent() : void
    {
        $this->client->sendEmail(Argument::any())->shouldNotBeCalled();
    }

    public function testThatAnInvalidMessageIsExceptional() : void
    {
        $message = new PostmarkMessage();
        $this->messageWillNotBeSent();
        $this->expectException(MessageValidationFailure::class);
        $this->expectExceptionMessage('The message provided does not pass Postmark validation rules');
        $this->transport()->send($message);
    }

    public function testThatAFromAddressIsRequired() : void
    {
        $message = new PostmarkMessage();
        $transport = new PostmarkTransport($this->client->reveal(), new ValidatorChain());
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('A from address has not been specified');
        $transport->send($message);
    }

    /** @return PostmarkMessage[] */
    public function getMessage() : iterable
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
    private function assertHeaderArrayContainsHeaderName(array $headers, string $headerName) : void
    {
        $this->assertArrayHasKey($headerName, $headers, sprintf(
            'The header named %s was not not found in the input',
            $headerName
        ));
    }

    /**
     * @param mixed[] $headers
     * @param mixed   $expect
     */
    private function assertHeaderEqualsValue(array $headers, string $headerName, $expect) : void
    {
        $this->assertHeaderArrayContainsHeaderName($headers, $headerName);
        $this->assertEquals($expect, $headers[$headerName]);
    }

    /** @dataProvider getMessage */
    public function testClientIsProvidedWithExpectedValues(PostmarkMessage $message) : void
    {
        $this->client->sendEmail(
            '<from@example.com>',
            '<to@example.com>',
            'Subject',
            '<p>HTML Body</p>',
            'Text Body',
            'TAG',
            false,
            '<reply@example.com>',
            '<cc@example.com>,<cc2@example.com>',
            '<bcc@example.com>',
            Argument::that(function ($headers) {
                $this->assertHeaderEqualsValue($headers, 'X-Foo', 'bar');

                return true;
            }),
            Argument::that(function ($files) {
                $this->assertIsArray($files);
                $this->assertCount(1, $files);
                $first = reset($files);
                $this->assertInstanceOf(PostmarkAttachment::class, $first);
                assert($first instanceof PostmarkAttachment);

                return true;
            }),
            LinkTracking::HTML,
            ['key' => 'value']
        )->shouldBeCalled();

        $this->transport()->send($message);
    }

    public function testThatARegularPlainTextMessageIsOk() : void
    {
        $message = new LaminasMessage();
        $message->setFrom('a@example.com');
        $message->setTo('b@example.com');
        $message->setSubject('Foo');
        $message->setBody('Text');

        $this->client->sendEmail(
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
            null
        )->shouldBeCalled();

        $this->transport()->send($message);
    }

    public function testThatARegularHtmlMessageIsOk() : void
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

        $this->client->sendEmail(
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
            Argument::type('array'),
            null,
            null,
            null
        )->shouldBeCalled();

        $this->transport()->send($message);
    }

    public function testThatAMimeEncodedPlainTextMessageIsOk() : void
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

        $this->client->sendEmail(
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
            Argument::type('array'),
            null,
            null,
            null
        )->shouldBeCalled();

        $this->transport()->send($message);
    }

    public function testThatTheContentTypeHeaderIsStripped() : void
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
        $this->assertTrue($headers->has('Date'));

        $this->client->sendEmail(
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
            Argument::that(function ($headerArray) {
                $this->assertArrayNotHasKey('Content-Type', $headerArray);

                return true;
            }),
            null,
            null,
            null
        )->shouldBeCalled();

        $this->transport()->send($message);
    }

    public function testThatTheDateHeaderIsStripped() : void
    {
        $message = new LaminasMessage();
        $message->setFrom('a@example.com');
        $message->setTo('b@example.com');
        $message->setSubject('Foo');
        $message->setBody('Text');

        $headers = $message->getHeaders();
        $this->assertTrue($headers->has('Date'));

        $this->client->sendEmail(
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
            Argument::that(function ($headerArray) {
                if (is_array($headerArray)) {
                    $this->assertArrayNotHasKey('Date', $headerArray);
                }

                return true;
            }),
            null,
            null,
            null
        )->shouldBeCalled();

        $this->transport()->send($message);
    }

    public function testThatTheReplyToHeaderIsStripped() : void
    {
        $message = new LaminasMessage();
        $message->setFrom('a@example.com');
        $message->setTo('b@example.com');
        $message->setSubject('Foo');
        $message->setBody('Text');

        $this->assertFalse($message->getHeaders()->has('Reply-To'));

        $message->setReplyTo('c@example.com');

        $this->assertTrue($message->getHeaders()->has('Reply-To'));

        $this->client->sendEmail(
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
            Argument::that(function ($headerArray) {
                if (is_array($headerArray)) {
                    $this->assertArrayNotHasKey('Reply-To', $headerArray);
                }

                return true;
            }),
            null,
            null,
            null
        )->shouldBeCalled();

        $this->transport()->send($message);
    }

    public function testThatPlainTextMessageCanBeSent() : void
    {
        $transport = $this->networkTransport();

        $message = new LaminasMessage();
        $message->setFrom('a@example.com');
        $message->setTo('b@example.com');
        $message->setSubject('Foo');
        $message->setBody('Text');

        $transport->send($message);
        $this->addToAssertionCount(1);
    }

    /** @dataProvider getMessage */
    public function testThatAFullFeaturedMessageCanBeSent(PostmarkMessage $message) : void
    {
        $transport = $this->networkTransport();
        $transport->send($message);
        $this->addToAssertionCount(1);
    }

    public function testThatMultipartMimeEncodedContentIsNotSentToApiClient() : void
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
        $this->assertTrue($headers->has('Date'));

        $this->client->sendEmail(
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
            Argument::any(),
            null,
            null,
            null
        )->shouldBeCalled();

        $this->transport()->send($message);
    }
}
