<?php

declare(strict_types=1);

namespace App\Support;

class HostAddresses
{
    private const ATTEMPTS = 3;

    private const RETRY_DELAY_MICROSECONDS = 250_000;

    /**
     * Resolves the A and AAAA addresses of a host. Resolver failures such as
     * SERVFAIL are transient, so they are retried before giving up with an
     * empty list.
     *
     * @return list<string>
     */
    public static function resolve(string $host): array
    {
        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            if (is_array($records)) {
                return self::addresses($records);
            }

            if ($attempt < self::ATTEMPTS) {
                usleep(self::RETRY_DELAY_MICROSECONDS * $attempt);
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return list<string>
     */
    private static function addresses(array $records): array
    {
        $addresses = [];
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip) && $ip !== '') {
                $addresses[] = $ip;
            }
        }

        return $addresses;
    }
}
