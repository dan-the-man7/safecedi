<?php
declare(strict_types=1);

namespace SafeCedi;

/**
 * SSRF guard. Decides whether the server may fetch a URL a stranger gave us.
 *
 * Phase 1: this is only the DECISION function. Nothing fetches anything yet.
 * Phase 3's redirect-chain resolver calls check() on EVERY hop (not just the
 * first), and must connect to the validated IP (see pinnedResolveEntry) so
 * DNS cannot change between "check" and "fetch" (DNS rebinding).
 *
 * Rules, in order:
 *   1. Only http / https.
 *   2. No user:pass@ in the URL (classic "trusted.com@evil.com" trick).
 *   3. Only ports 80 and 443.
 *   4. Hosts written as decimal / hex / octal numbers (2130706433, 0x7f.1)
 *      are refused outright. Real sites are never written that way.
 *   5. Resolve the hostname, then EVERY resulting IP must be public.
 *      If even one is private/loopback/link-local, refuse (fail closed).
 *   6. Anything unparseable or unresolvable is refused.
 *
 * The resolver is injectable so tests run offline and deterministically.
 */
final class SsrfGuard
{
    public const MAX_HOPS = 5;
    public const MAX_BYTES = 262144;     // 256 KB response cap (used in Phase 3)
    public const TIMEOUT_SECONDS = 5;    // hard timeout per request (used in Phase 3)

    private const ALLOWED_SCHEMES = ['http', 'https'];
    private const ALLOWED_PORTS = [80, 443];

    private const BLOCKED_CIDRS = [
        // IPv4
        '0.0.0.0/8',        // "this" network
        '10.0.0.0/8',       // private
        '100.64.0.0/10',    // carrier-grade NAT
        '127.0.0.0/8',      // loopback
        '169.254.0.0/16',   // link-local (cloud metadata lives at 169.254.169.254)
        '172.16.0.0/12',    // private
        '192.0.0.0/24',     // IETF protocol assignments
        '192.0.2.0/24',     // documentation
        '192.168.0.0/16',   // private
        '198.18.0.0/15',    // benchmarking
        '198.51.100.0/24',  // documentation
        '203.0.113.0/24',   // documentation
        '224.0.0.0/4',      // multicast
        '240.0.0.0/4',      // reserved
        // IPv6
        '::/128',           // unspecified
        '::1/128',          // loopback
        '::ffff:0:0/96',    // IPv4-mapped (could smuggle 127.0.0.1)
        '64:ff9b::/96',     // NAT64
        '100::/64',         // discard
        '2001:db8::/32',    // documentation
        'fc00::/7',         // unique local (private)
        'fe80::/10',        // link-local
        'ff00::/8',         // multicast
    ];

    /**
     * @param callable|null $resolver fn(string $host): string[]  (list of IPs)
     * @return array{ok:bool,reason:string,host:?string,port:?int,scheme:?string,ips:string[]}
     */
    public static function check(string $url, ?callable $resolver = null): array
    {
        $resolver ??= [self::class, 'defaultResolver'];
        $url = trim($url);

        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return self::refuse('malformed_url');
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return self::refuse('malformed_url');
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return self::refuse('scheme_not_allowed');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return self::refuse('userinfo_not_allowed');
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (!in_array($port, self::ALLOWED_PORTS, true)) {
            return self::refuse('port_not_allowed');
        }

        $host = strtolower(rtrim(trim($parts['host'], '[]'), '.'));
        if ($host === '' || strlen($host) > 253) {
            return self::refuse('malformed_host');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $ips = [$host];
        } elseif (preg_match('~^(?:(?:0x[0-9a-f]+|\d+)\.?)+$~i', $host)) {
            return self::refuse('numeric_host_form', $host);
        } else {
            $ips = $resolver($host);
            if (!is_array($ips) || $ips === []) {
                return self::refuse('unresolvable', $host);
            }
        }

        foreach ($ips as $ip) {
            if (self::isBlockedIp((string) $ip)) {
                return self::refuse('blocked_address', $host, [(string) $ip]);
            }
        }

        return [
            'ok' => true,
            'reason' => 'ok',
            'host' => $host,
            'port' => $port,
            'scheme' => $scheme,
            'ips' => array_values($ips),
        ];
    }

    /** True if the IP is in any blocked range. Unparseable => blocked (fail closed). */
    public static function isBlockedIp(string $ip): bool
    {
        if (@inet_pton($ip) === false) {
            return true;
        }
        foreach (self::BLOCKED_CIDRS as $cidr) {
            if (self::inCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    /**
     * For Phase 3: value for CURLOPT_RESOLVE so cURL connects to the IP we
     * validated instead of resolving the name a second time.
     */
    public static function pinnedResolveEntry(array $checkResult): string
    {
        $ip = $checkResult['ips'][0];
        if (str_contains($ip, ':')) {
            $ip = '[' . $ip . ']';
        }
        return $checkResult['host'] . ':' . $checkResult['port'] . ':' . $ip;
    }

    /** @return string[] */
    public static function defaultResolver(string $host): array
    {
        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $r) {
                if (isset($r['ip'])) {
                    $ips[] = $r['ip'];
                } elseif (isset($r['ipv6'])) {
                    $ips[] = $r['ipv6'];
                }
            }
        }
        return array_values(array_unique($ips));
    }

    private static function inCidr(string $ip, string $cidr): bool
    {
        [$net, $bits] = explode('/', $cidr);
        $ipBin = inet_pton($ip);
        $netBin = inet_pton($net);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
            return false; // different IP families never match
        }
        $bits = (int) $bits;
        $fullBytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($netBin, 0, $fullBytes)) {
            return false;
        }
        if ($remainder === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainder)) & 0xFF;
        return (ord($ipBin[$fullBytes]) & $mask) === (ord($netBin[$fullBytes]) & $mask);
    }

    private static function refuse(string $reason, ?string $host = null, array $ips = []): array
    {
        return ['ok' => false, 'reason' => $reason, 'host' => $host, 'port' => null, 'scheme' => null, 'ips' => $ips];
    }
}
