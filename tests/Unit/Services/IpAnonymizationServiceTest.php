<?php

declare(strict_types=1);

use App\Services\IpAnonymizationService;
use Illuminate\Support\Facades\Config;

describe('IP anonymization detection', function () {
    test('detects subnet anonymized IPv4 addresses', function () {
        expect(IpAnonymizationService::isAnonymized('192.168.1.0'))->toBeTrue()
            ->and(IpAnonymizationService::isAnonymized('10.0.0.0'))->toBeTrue();
    });

    test('detects subnet anonymized IPv6 addresses', function () {
        expect(IpAnonymizationService::isAnonymized('2001:db8::'))->toBeTrue();
    });

    test('detects hash anonymized addresses', function () {
        expect(IpAnonymizationService::isAnonymized('hash_abc123def456'))->toBeTrue()
            ->and(IpAnonymizationService::isAnonymized('hash_xyz'))->toBeTrue();
    });

    test('detects fully anonymized addresses', function () {
        expect(IpAnonymizationService::isAnonymized('***'))->toBeTrue();
    });

    test('does not detect non-anonymized IPv4 addresses', function () {
        expect(IpAnonymizationService::isAnonymized('192.168.1.123'))->toBeFalse()
            ->and(IpAnonymizationService::isAnonymized('8.8.8.8'))->toBeFalse();
    });

    test('does not detect non-anonymized IPv6 addresses', function () {
        expect(IpAnonymizationService::isAnonymized('2001:db8::1'))->toBeFalse();
    });
});

describe('subnet anonymization', function () {
    test('anonymizes IPv4 by zeroing last octet', function () {
        expect(IpAnonymizationService::anonymizeBySubnet('192.168.1.123'))->toBe('192.168.1.0')
            ->and(IpAnonymizationService::anonymizeBySubnet('10.0.0.255'))->toBe('10.0.0.0')
            ->and(IpAnonymizationService::anonymizeBySubnet('172.16.254.1'))->toBe('172.16.254.0');
    });

    test('anonymizes IPv6 by zeroing last 64 bits', function () {
        $result = IpAnonymizationService::anonymizeBySubnet('2001:db8::1');

        // The result should have the last 64 bits zeroed
        expect($result)->toContain('2001:db8');
    });

    test('handles already anonymized IPv4', function () {
        expect(IpAnonymizationService::anonymizeBySubnet('192.168.1.0'))->toBe('192.168.1.0');
    });

    test('handles edge case IP addresses', function () {
        expect(IpAnonymizationService::anonymizeBySubnet('0.0.0.0'))->toBe('0.0.0.0')
            ->and(IpAnonymizationService::anonymizeBySubnet('255.255.255.255'))->toBe('255.255.255.0');
    });
});

describe('hash anonymization', function () {
    test('creates consistent hash for same IP', function () {
        $ip = '192.168.1.123';
        $hash1 = IpAnonymizationService::anonymizeByHash($ip);
        $hash2 = IpAnonymizationService::anonymizeByHash($ip);

        expect($hash1)->toBe($hash2)
            ->and($hash1)->toStartWith('hash_')
            ->and(strlen($hash1))->toBe(17); // 'hash_' + 12 chars
    });

    test('creates different hashes for different IPs', function () {
        $hash1 = IpAnonymizationService::anonymizeByHash('192.168.1.1');
        $hash2 = IpAnonymizationService::anonymizeByHash('192.168.1.2');

        expect($hash1)->not->toBe($hash2);
    });

    test('hash format is correct', function () {
        $hash = IpAnonymizationService::anonymizeByHash('10.0.0.1');

        expect($hash)->toStartWith('hash_')
            ->and($hash)->toMatch('/^hash_[a-f0-9]{12}$/');
    });
});

describe('anonymization with method selection', function () {
    test('uses subnet method when specified', function () {
        $result = IpAnonymizationService::anonymize('192.168.1.123', 'subnet');

        expect($result)->toBe('192.168.1.0');
    });

    test('uses hash method when specified', function () {
        $result = IpAnonymizationService::anonymize('192.168.1.123', 'hash');

        expect($result)->toStartWith('hash_');
    });

    test('uses full anonymization when specified', function () {
        $result = IpAnonymizationService::anonymize('192.168.1.123', 'full');

        expect($result)->toBe('***');
    });

    test('returns original IP for unknown method', function () {
        $result = IpAnonymizationService::anonymize('192.168.1.123', 'unknown');

        expect($result)->toBe('192.168.1.123');
    });

    test('handles null IP address', function () {
        expect(IpAnonymizationService::anonymize(null))->toBeNull();
    });
});

describe('configuration-based anonymization', function () {
    test('respects global anonymization setting when enabled', function () {
        Config::set('audit.privacy.anonymize_ip_addresses', true);
        Config::set('audit.privacy.ip_anonymization_method', 'subnet');

        $result = IpAnonymizationService::getAnonymizedIpAddress('192.168.1.123');

        expect($result)->toBe('192.168.1.0');
    });

    test('returns original IP when global anonymization disabled', function () {
        Config::set('audit.privacy.anonymize_ip_addresses', false);

        $result = IpAnonymizationService::getAnonymizedIpAddress('192.168.1.123');

        expect($result)->toBe('192.168.1.123');
    });

    test('handles null IP with global setting', function () {
        Config::set('audit.privacy.anonymize_ip_addresses', true);

        expect(IpAnonymizationService::getAnonymizedIpAddress(null))->toBeNull();
    });

    test('uses configured method from config', function () {
        Config::set('audit.privacy.anonymize_ip_addresses', true);
        Config::set('audit.privacy.ip_anonymization_method', 'hash');

        $result = IpAnonymizationService::getAnonymizedIpAddress('192.168.1.123');

        expect($result)->toStartWith('hash_');
    });
});

describe('GDPR compliance scenarios', function () {
    test('anonymizes user IP for data retention', function () {
        $userIp = '203.0.113.45';
        $anonymized = IpAnonymizationService::anonymize($userIp, 'subnet');

        expect($anonymized)->toBe('203.0.113.0')
            ->and($anonymized)->not->toBe($userIp);
    });

    test('allows analytics while protecting privacy', function () {
        $ip1 = '192.168.1.100';
        $ip2 = '192.168.1.200';

        $anon1 = IpAnonymizationService::anonymize($ip1, 'subnet');
        $anon2 = IpAnonymizationService::anonymize($ip2, 'subnet');

        // Both should map to same subnet for analytics
        expect($anon1)->toBe($anon2)
            ->and($anon1)->toBe('192.168.1.0');
    });

    test('hash method provides unique tracking without exposing IP', function () {
        $ip = '192.168.1.123';
        $hash = IpAnonymizationService::anonymize($ip, 'hash');

        // Hash should be consistent but not reversible
        expect($hash)->toStartWith('hash_')
            ->and($hash)->not->toContain('192')
            ->and($hash)->not->toContain('168');
    });
});

describe('edge cases and error handling', function () {
    test('handles localhost addresses', function () {
        expect(IpAnonymizationService::anonymizeBySubnet('127.0.0.1'))->toBe('127.0.0.0')
            ->and(IpAnonymizationService::anonymizeBySubnet('::1'))->toBeString();
    });

    test('handles private network addresses', function () {
        expect(IpAnonymizationService::anonymizeBySubnet('10.0.0.1'))->toBe('10.0.0.0')
            ->and(IpAnonymizationService::anonymizeBySubnet('172.16.0.1'))->toBe('172.16.0.0')
            ->and(IpAnonymizationService::anonymizeBySubnet('192.168.0.1'))->toBe('192.168.0.0');
    });

    test('handles invalid IP addresses gracefully', function () {
        $invalid = 'not-an-ip';

        expect(IpAnonymizationService::anonymizeBySubnet($invalid))->toBe($invalid);
    });

    test('handles empty string', function () {
        Config::set('audit.privacy.anonymize_ip_addresses', true);

        // Empty string is treated as falsy and returns null
        expect(IpAnonymizationService::anonymize(''))->toBeNull();
    });
});

describe('real-world IP addresses', function () {
    test('anonymizes common public IPs', function () {
        // Google DNS
        expect(IpAnonymizationService::anonymize('8.8.8.8', 'subnet'))->toBe('8.8.8.0');

        // Cloudflare DNS
        expect(IpAnonymizationService::anonymize('1.1.1.1', 'subnet'))->toBe('1.1.1.0');
    });

    test('anonymizes IPv6 addresses correctly', function () {
        $ipv6 = '2001:4860:4860::8888'; // Google DNS IPv6
        $result = IpAnonymizationService::anonymizeBySubnet($ipv6);

        expect($result)->toBeString()
            ->and($result)->toContain('2001:4860');
    });
});
