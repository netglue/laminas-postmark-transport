# Postmark Mail Transport for Laminas

![PHPUnit Test Suite](https://github.com/netglue/laminas-postmark-transport/workflows/Continuous%20Integration/badge.svg)
[![Type Coverage](https://shepherd.dev/github/netglue/laminas-postmark-transport/coverage.svg)](https://shepherd.dev/github/netglue/laminas-postmark-transport)

### Introduction

This lib provides a mail transport for use with [Laminas\Mail](https://github.com/laminas/laminas-mail) and [Postmark’s transactional email service](https://postmarkapp.com). It also provides email message validators to help make sure that messages you send via Postmark are acceptable - I'm quite pleased with the validator that checks the from address is listed in the verified postmark domains on your account, or amongst the [configured sender signatures](https://postmarkapp.com/manual#step-2-set-up-the-address-you-plan-to-send-from).

### Installation

```bash
composer require netglue/laminas-postmark-transport
```

If you are using this in an app built with Expressive/Mezzio then the config provider should get injected automatically for you during installation by way of the [Laminas component installer](https://docs.laminas.dev/laminas-component-installer/). If you're using Laminas MVC then you might have to take extra steps to get the default config fired up.

One of the dependencies is a tiny package [netglue/psr-container-postmark](https://github.com/netglue/psr-container-postmark) - this is what's used to configure the official Postmark API client so you can find some additional info in the README in that lib for configuring your account and server tokens for the service.

### Usage

By default, assuming use of Laminas Service Manager for your DIC, you will be able to retrieve your transport using either:
```php
$transport = $container->get(\Laminas\Mail\Transport\TransportInterface::class);
// or
$transport = $container->get(\Netglue\Mail\Postmark\Transport\PostmarkTransport::class);
```

As with all mail transports, you'll only interact with it to send a `Laminas\Mail\Message`:

```php
$message = new Message();
// ... Set To, From, Subject etc...
$transport->send($message);
```

Read up on the docs specific to [sending mail with Laminas components here](https://docs.laminas.dev/laminas-mail/)...

The main reason that I put this lib together is so that I could validate messages _before_ dispatching them to the API to surface common errors such as an invalid sender address, too many recipients etc. The transport accepts a validator in its constructor arguments that is used to enforce the constraints placed on sending mail with Postmark specifically.

There is a default validator chain that you can inspect covering most of these constraints and a shipped FromAddressValidator that is not used by default. The reason this one isn't initially enabled is because it makes API calls to the account endpoints to retrieve the configured sending domains and email addresses and then checks that the From header of the message is amongst the verified senders.

So, to use this validator, you'd need to:

 - Configure the access token for the account API
 - Inject a different validator chain into the transport
 - Provide the name of a PSR-6 compatible cache that can be retrieved from the container

Hopefully the following configuration should illuminate what's required...

```php
'postmark' => [
    'server_token' => 'Your "server" token for sending mail',
    'account_token' => 'Token for the account API', // Required when using the sender validator
    'message_validator' => \MyApp\Validator\CustomMessageValidationChain::class,
    'cache_service' => \MyApp\PsrCacheItemPool::class, // The cache to use for the domain and sender signatures list.
],
```

Finally, this lib also has a dependency on [netglue/laminas-mail-utils](https://github.com/netglue/laminas-mail-utils) which is primarily a collection of more generic validators for mail messages and message behaviours that are intended for use with other vendors such as SparkPost, MailGun etc. If/when you build your custom validator chain, there are a bunch of validators there that might be useful.
