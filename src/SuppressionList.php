<?php

declare(strict_types=1);

namespace Netglue\Mail\Postmark;

use Laminas\Validator\EmailAddress;
use Netglue\Mail\Postmark\Exception\NotAnEmailAddress;
use Postmark\PostmarkClient;
use Psr\Cache\CacheItemPoolInterface;
use UnexpectedValueException;

use function in_array;
use function is_iterable;
use function is_string;
use function strtolower;

class SuppressionList
{
    public const SUPPRESSION_LIST_CACHE_KEY = 'PostmarkSuppressionList';

    /** @var PostmarkClient */
    private $client;
    /** @var CacheItemPoolInterface */
    private $cache;
    /** @var EmailAddress */
    private $validator;

    public function __construct(PostmarkClient $client, CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
        $this->client = $client;
        $this->validator = new EmailAddress();
    }

    public function seedSuppressionListCache(): void
    {
        $this->saveList($this->remoteList());
    }

    public function isSuppressed(string $emailAddress): bool
    {
        $emailAddress = strtolower($emailAddress);
        if (! $this->validator->isValid($emailAddress)) {
            throw NotAnEmailAddress::withString($emailAddress);
        }

        $list = $this->getList();
        if (in_array($emailAddress, $list, true)) {
            return true;
        }

        if ($this->queryList($emailAddress)) {
            $list[] = $emailAddress;
            $this->saveList($list);

            return true;
        }

        return false;
    }

    /**
     * Query the API for the given email address.
     *
     * If the email address is found in the remote suppression list return true, otherwise return false.
     */
    private function queryList(string $emailAddress): bool
    {
        return in_array($emailAddress, $this->remoteList($emailAddress), true);
    }

    /** @return string[] */
    private function remoteList(?string $emailQuery = null): array
    {
        $results = [];
        $response = $this->client->getSuppressions(null, null, null, null, $emailQuery);
        $responseList = $response['Suppressions'] ?? [];
        if (! is_iterable($responseList)) {
            throw new UnexpectedValueException('Expected the Suppression list from the API to contain an array in its "Suppressions" property');
        }

        foreach ($responseList as $suppression) {
            $email = $suppression['EmailAddress'] ?? null;
            if (empty($email) || ! is_string($email)) {
                throw new UnexpectedValueException('Expected each suppression item to have a string EmailAddress attribute');
            }

            $results[] = strtolower($email);
        }

        return $results;
    }

    /** @return string[] */
    private function getList(): array
    {
        $item = $this->cache->getItem(self::SUPPRESSION_LIST_CACHE_KEY);

        return $item->isHit() ? $item->get() : [];
    }

    /** @param string[] $list */
    private function saveList(array $list): void
    {
        $item = $this->cache->getItem(self::SUPPRESSION_LIST_CACHE_KEY);
        $item->set($list);
        $this->cache->save($item);
    }
}
