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
use function sprintf;
use function strtolower;
use function trim;

class PermittedSenders
{
    public const DOMAIN_LIST_CACHE_KEY = 'PostmarkDomains';
    public const SENDER_LIST_CACHE_KEY = 'PostmarkSenders';
    private const MAX_PER_REQUEST = 500;

    /** @var PostmarkAdminClient */
    private $client;

    /** @var CacheItemPoolInterface */
    private $cache;

    public function __construct(PostmarkAdminClient $client, CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
        $this->client = $client;
    }

    public function isPermittedSender(string $emailAddressOrHostname) : bool
    {
        ['email' => $email, 'hostname' => $hostname] = $this->extractEmailAndHostname($emailAddressOrHostname);

        if ($email && in_array($email, $this->senders(), true)) {
            return true;
        }

        return $hostname && in_array($hostname, $this->domains(), true);
    }

    /** @return string[] */
    private function extractEmailAndHostname(string $emailAddressOrHostname) : array
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

        throw new InvalidArgumentException(sprintf(
            'The value provided is neither a valid email address, nor a valid hostname'
        ));
    }

    /**
     * Returns a regular array of strings with each item representing a domain name
     *
     * @return string[]
     *
     * @throws CacheException If any problems occur setting/getting items in the cache.
     */
    public function domains() : iterable
    {
        $item = $this->cache->getItem(self::DOMAIN_LIST_CACHE_KEY);
        if ($item->isHit()) {
            return $item->get();
        }

        $domains = $this->retrieveDomainList();

        $item->set($domains);
        $this->cache->save($item);

        return $domains;
    }

    /** @return string[] */
    private function retrieveDomainList() : array
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

            foreach ($domainList as $domain) {
                $name = $domain['Name'] ?? null;
                if (empty($name)) {
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
     * @return string[]
     *
     * @throws CacheException If any problems occur setting/getting items in the cache.
     */
    public function senders() : iterable
    {
        $item = $this->cache->getItem(self::SENDER_LIST_CACHE_KEY);
        if ($item->isHit()) {
            return $item->get();
        }

        $senders = $this->retrieveSenderList();

        $item->set($senders);
        $this->cache->save($item);

        return $senders;
    }

    /** @return string[] */
    private function retrieveSenderList() : array
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

            foreach ($senderList as $sender) {
                $email = $sender['EmailAddress'] ?? null;
                if (empty($email)) {
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
