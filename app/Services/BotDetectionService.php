<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Config;

/**
 * Classifies analytics hits as human or automated.
 *
 * The same entry point serves live recording and re-classification of stored
 * rows, so a hit written today and a hit backfilled from last year are judged
 * by identical rules.
 */
class BotDetectionService
{
    /** @var array<string, bool> */
    private static array $networkCache = [];

    /** @var array<string, string|null> */
    private static array $userAgentCache = [];
    public const string REASON_BLOCKED_NETWORK = 'blocked_network';

    public const string REASON_CRAWLER_UA = 'crawler_ua';

    public const string REASON_NO_USER_AGENT = 'no_user_agent';

    /**
     * Assigned by {@see SessionChurnDetector}, which reads a user agent's
     * traffic as a whole. No single row carries enough evidence for it, so
     * detect() never returns this reason.
     */
    public const string REASON_SESSION_CHURN = 'session_churn';

    /**
     * Marks a row whose identifying fields were erased when the visitor's
     * account was deleted. The row is retained for analytics.
     */
    public const string ANONYMISED_SESSION_PREFIX = 'anonymized_';

    /**
     * Return the reason this hit is automated, or null when it looks human.
     *
     * Accepts both raw and subnet-anonymised addresses: the configured
     * prefixes are /24 or larger, so zeroing the final octet cannot change
     * the outcome.
     *
     * A hit whose session id carries the anonymisation marker belonged to a
     * signed-in visitor whose account has since been erased, which makes it
     * human by construction: no automated client holds an account. Its
     * identifying fields are gone, so the rules below could only ever read
     * that absence as guilt.
     */
    public static function detect(?string $userAgent, ?string $ipAddress, ?string $sessionId = null): ?string
    {
        if ($sessionId !== null && str_starts_with($sessionId, self::ANONYMISED_SESSION_PREFIX)) {
            return null;
        }

        if ($ipAddress !== null && self::isBlockedNetwork($ipAddress)) {
            return self::REASON_BLOCKED_NETWORK;
        }

        if ($userAgent === null || trim($userAgent) === '') {
            return self::REASON_NO_USER_AGENT;
        }

        return self::matchCrawlerUserAgent($userAgent);
    }

    /**
     * Drop memoised lookups so configuration changes take effect immediately.
     */
    public static function flushCache(): void
    {
        self::$networkCache = [];
        self::$userAgentCache = [];
    }

    private static function isBlockedNetwork(string $ipAddress): bool
    {
        if (array_key_exists($ipAddress, self::$networkCache)) {
            return self::$networkCache[$ipAddress];
        }

        $binary = @inet_pton($ipAddress);
        $matched = false;

        if ($binary !== false) {
            /** @var array<int, string> $networks */
            $networks = Config::get('analytics.bot_detection.blocked_networks', []);

            foreach ($networks as $network) {
                if (self::binaryInNetwork($binary, $network)) {
                    $matched = true;
                    break;
                }
            }
        }

        // Bounded so a long-running backfill over millions of distinct
        // addresses cannot grow the cache without limit.
        if (count(self::$networkCache) >= 100000) {
            self::$networkCache = [];
        }

        return self::$networkCache[$ipAddress] = $matched;
    }

    private static function binaryInNetwork(string $binary, string $network): bool
    {
        [$prefix, $length] = array_pad(explode('/', $network, 2), 2, null);

        $prefixBinary = @inet_pton((string) $prefix);

        if ($prefixBinary === false || strlen($prefixBinary) !== strlen($binary)) {
            return false;
        }

        $bits = $length === null ? strlen($binary) * 8 : (int) $length;
        $bits = max(0, min($bits, strlen($binary) * 8));

        $wholeBytes = intdiv($bits, 8);

        if ($wholeBytes > 0 && strncmp($binary, $prefixBinary, $wholeBytes) !== 0) {
            return false;
        }

        $remainingBits = $bits % 8;

        if ($remainingBits === 0) {
            return true;
        }

        $mask = ~((1 << (8 - $remainingBits)) - 1) & 0xFF;

        return (ord($binary[$wholeBytes]) & $mask) === (ord($prefixBinary[$wholeBytes]) & $mask);
    }

    private static function matchCrawlerUserAgent(string $userAgent): ?string
    {
        if (array_key_exists($userAgent, self::$userAgentCache)) {
            return self::$userAgentCache[$userAgent];
        }

        /** @var array<int, string> $patterns */
        $patterns = Config::get('analytics.bot_detection.crawler_user_agent_patterns', []);

        $reason = null;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $userAgent) === 1) {
                $reason = self::REASON_CRAWLER_UA;
                break;
            }
        }

        if (count(self::$userAgentCache) >= 50000) {
            self::$userAgentCache = [];
        }

        return self::$userAgentCache[$userAgent] = $reason;
    }
}
