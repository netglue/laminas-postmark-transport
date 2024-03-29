<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark;

use InvalidArgumentException;
use Laminas\Validator\EmailAddress;
use Postmark\PostmarkAdminClient;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

use function count;
use function explode;
use function in_array;
use function is_iterable;
use function is_string;
use function strtolower;
use function trim;

class PermittedSenders
{
    public const DOMAIN_LIST_CACHE_KEY = 'PostmarkDomains';
    public const SENDER_LIST_CACHE_KEY = 'PostmarkSenders';
    private const MAX_PER_REQUEST = 500;

    public function __construct(private PostmarkAdminClient $client, private CacheItemPoolInterface $cache)
    {
    }

    public function isPermittedSender(string $emailAddressOrHostname): bool
    {
        ['email' => $email, 'hostname' => $hostname] = $this->extractEmailAndHostname($emailAddressOrHostname);

        if ($email !== null && in_array($email, $this->senders(), true)) {
            return true;
        }

        return in_array($hostname, $this->domains(), true);
    }

    /** @return array{email: non-empty-lowercase-string|null, hostname: lowercase-string} */
    private function extractEmailAndHostname(string $emailAddressOrHostname): array
    {
        $emailAddressOrHostname = trim($emailAddressOrHostname);
        if (empty($emailAddressOrHostname)) {
            throw new InvalidArgumentException('A non empty string is required');
        }

        $emailValidator = new EmailAddress();

        if ($emailValidator->isValid($emailAddressOrHostname)) {
            $email = strtolower($emailAddressOrHostname);
            [, $hostname] = explode('@', $email);

            return [
                'email' => $email,
                'hostname' => $hostname,
            ];
        }

        $hostValidator = $emailValidator->getHostnameValidator();
        if ($hostValidator->isValid($emailAddressOrHostname)) {
            return [
                'email' => null,
                'hostname' => strtolower($emailAddressOrHostname),
            ];
        }

        throw new InvalidArgumentException(
            'The value provided is neither a valid email address, nor a valid hostname',
        );
    }

    /**
     * Returns a regular array of strings with each item representing a domain name
     *
     * @return list<string>
     *
     * @throws CacheException If any problems occur setting/getting items in the cache.
     */
    public function domains(): iterable
    {
        $item = $this->cache->getItem(self::DOMAIN_LIST_CACHE_KEY);
        if ($item->isHit()) {
            /** @var list<string> $list */
            $list = $item->get();

            return $list;
        }

        $domains = $this->retrieveDomainList();

        $item->set($domains);
        $this->cache->save($item);

        return $domains;
    }

    /** @return list<string> */
    private function retrieveDomainList(): array
    {
        $domains = [];
        while (true) {
            $response = $this->client->listDomains(self::MAX_PER_REQUEST, count($domains));
            $total = isset($response['TotalCount']) ? (int) $response['TotalCount'] : null;
            if ($total === null) {
                throw new RuntimeException('Expected the Postmark response to contain a "TotalCount" property');
            }

            if ($total === 0) {
                return [];
            }

            $domainList = $response['Domains'] ?? null;

            if (! is_iterable($domainList)) {
                throw new RuntimeException('Expected the Postmark response to contain an array in the "Domains" property');
            }

            /** @var array<string, string> $domain */
            foreach ($domainList as $domain) {
                $name = $domain['Name'] ?? null;
                if (! is_string($name) || $name === '') {
                    throw new RuntimeException('One of the domains in the list does not have a name');
                }

                $domains[] = strtolower($name);
            }

            if (count($domains) >= $total) {
                break;
            }
        }

        return $domains;
    }

    /**
     * Returns a regular array of strings with each item representing an email address
     *
     * @return list<string>
     *
     * @throws CacheException If any problems occur setting/getting items in the cache.
     */
    public function senders(): iterable
    {
        $item = $this->cache->getItem(self::SENDER_LIST_CACHE_KEY);
        if ($item->isHit()) {
            /** @var list<string> $list */
            $list = $item->get();

            return $list;
        }

        $senders = $this->retrieveSenderList();

        $item->set($senders);
        $this->cache->save($item);

        return $senders;
    }

    /** @return list<string> */
    private function retrieveSenderList(): array
    {
        $senders = [];
        while (true) {
            $response = $this->client->listSenderSignatures(self::MAX_PER_REQUEST, count($senders));
            $total = isset($response['TotalCount']) ? (int) $response['TotalCount'] : null;
            if ($total === null) {
                throw new RuntimeException('Expected the Postmark response to contain a "TotalCount" property');
            }

            if ($total === 0) {
                return [];
            }

            $senderList = $response['SenderSignatures'] ?? null;

            if (! is_iterable($senderList)) {
                throw new RuntimeException('Expected the Postmark response to contain an array in the "SenderSignatures" property');
            }

            /** @var array<string, string> $sender */
            foreach ($senderList as $sender) {
                $email = $sender['EmailAddress'] ?? null;
                if (! is_string($email) || $email === '') {
                    throw new RuntimeException('One of the senders in the list does not have an email address');
                }

                $senders[] = strtolower($email);
            }

            if (count($senders) >= $total) {
                break;
            }
        }

        return $senders;
    }
}
