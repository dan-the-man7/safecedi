<?php
/**
 * Deployment health check  (owner: M1)
 *
 * Answers the Phase 01 hosting questions with evidence instead of hope:
 *   - Is PHP new enough, with the extensions we need?
 *   - Is the site actually on HTTPS?
 *   - Can PHP reach MySQL and see the users table?
 *   - Can the HOST make outbound HTTP and HTTPS requests? (Plan B's link
 *     resolver and Safe Browsing both die without this — verify on day two.)
 *   - Does the Safe Browsing key work?
 *
 * Every outbound URL here is a fixed constant chosen by us, never user
 * input, so this file is not an SSRF risk. (M3's resolver is a different
 * story — it must go through the SSRF guard.)
 *
 * Run from a shell:   php ops/healthcheck.php
 * Run on the host:    https://YOUR-SITE/healthcheck.php?token=HEALTHCHECK_TOKEN
 */
declare(strict_types=1);

const HC_PASS = 'PASS';
const HC_WARN = 'WARN';
const HC_FAIL = 'FAIL';
const HC_SKIP = 'SKIP';

/** @return list<array{0:string,1:string,2:string}> [status, check, detail] */
function run_health_checks(bool $isWeb): array
{
    $results = [];
    $add = static function (string $status, string $check, string $detail) use (&$results): void {
        $results[] = [$status, $check, $detail];
    };

    // 1. PHP
    $add(version_compare(PHP_VERSION, '8.1.0', '>=') ? HC_PASS : HC_FAIL,
        'PHP version', PHP_VERSION . ' (need 8.1+)');

    foreach (['pdo_mysql', 'curl', 'openssl', 'mbstring'] as $ext) {
        $add(extension_loaded($ext) ? HC_PASS : HC_FAIL, "Extension {$ext}",
            extension_loaded($ext) ? 'loaded' : 'MISSING — ask the host to enable it');
    }

    // 2. Configuration sanity
    $env = config('APP_ENV', 'production');
    $add(HC_PASS, 'APP_ENV', $env);
    if ($env === 'production') {
        $add(config_bool('APP_DEBUG') ? HC_FAIL : HC_PASS, 'APP_DEBUG off in production',
            config_bool('APP_DEBUG') ? 'APP_DEBUG=true on a production host — set it to false' : 'ok');
        $add(config_bool('FORCE_HTTPS') ? HC_PASS : HC_WARN, 'FORCE_HTTPS',
            config_bool('FORCE_HTTPS') ? 'on' : 'off — turn on once the certificate works');
    }

    // 3. HTTPS
    if ($isWeb) {
        $add(is_https() ? HC_PASS : HC_FAIL, 'Request arrived over HTTPS',
            is_https() ? 'yes' : 'no — enable SSL in the hosting panel');
    } else {
        $add(HC_SKIP, 'Request arrived over HTTPS', 'run the web version on the host to check this');
    }

    // 4. Writable log directory
    $logDir = dirname(log_path());
    $add(is_writable($logDir) ? HC_PASS : HC_FAIL, 'storage/logs writable',
        is_writable($logDir) ? 'ok' : 'not writable — errors cannot be logged');

    // 5. Database
    try {
        $count = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $add(HC_PASS, 'MySQL connection', 'connected; users table has ' . $count . ' row(s)');
    } catch (Throwable $e) {
        $add(HC_FAIL, 'MySQL connection', $e->getMessage());
    }

    // 6. Outbound requests from the host
    if (extension_loaded('curl')) {
        $r = http_probe('GET', 'https://www.google.com/generate_204');
        $add($r['status'] === 204 ? HC_PASS : HC_FAIL, 'Outbound HTTPS (port 443)',
            $r['error'] ?? 'HTTP ' . $r['status'] . ' in ' . $r['ms'] . ' ms');

        $r = http_probe('GET', 'http://example.com/');
        $add($r['status'] >= 200 && $r['status'] < 400 ? HC_PASS : HC_FAIL, 'Outbound plain HTTP (port 80)',
            ($r['error'] ?? 'HTTP ' . $r['status'] . ' in ' . $r['ms'] . ' ms')
            . ' — scam links are often http://, so the resolver needs this');

        $add(...safe_browsing_check());
    } else {
        $add(HC_SKIP, 'Outbound requests', 'curl extension missing');
    }

    return $results;
}

/**
 * One request to a FIXED url. No redirects followed, short timeouts,
 * response size capped.
 *
 * @return array{status:int, error:?string, ms:int, body:string}
 */
function http_probe(string $method, string $url, ?string $jsonBody = null): array
{
    $ch = curl_init($url);
    $body = '';
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_USERAGENT      => 'SafeCedi-healthcheck/1.0',
        CURLOPT_WRITEFUNCTION  => static function ($ch, string $chunk) use (&$body): int {
            if (strlen($body) < 65536) {
                $body .= $chunk;
            }
            return strlen($chunk);
        },
    ]);
    if ($jsonBody !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $start = microtime(true);
    $ok    = curl_exec($ch);
    $ms    = (int) round((microtime(true) - $start) * 1000);
    $error = $ok === false ? 'request failed: ' . curl_error($ch) : null;
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    return ['status' => $status, 'error' => $error, 'ms' => $ms, 'body' => $body];
}

/**
 * Ask Safe Browsing about Google's official test URL, which is always
 * reported as malware. A match proves the key AND the outbound path work.
 *
 * @return array{0:string,1:string,2:string}
 */
function safe_browsing_check(): array
{
    $name = 'Safe Browsing API key';
    $key  = config('SAFE_BROWSING_API_KEY');
    if ($key === null) {
        return [HC_WARN, $name, 'SAFE_BROWSING_API_KEY not set in .env'];
    }

    $payload = json_encode([
        'client'     => ['clientId' => 'safecedi', 'clientVersion' => '0.1'],
        'threatInfo' => [
            'threatTypes'      => ['MALWARE', 'SOCIAL_ENGINEERING'],
            'platformTypes'    => ['ANY_PLATFORM'],
            'threatEntryTypes' => ['URL'],
            'threatEntries'    => [['url' => 'http://malware.testing.google.test/testing/malware/']],
        ],
    ]);

    $r = http_probe('POST',
        'https://safebrowsing.googleapis.com/v4/threatMatches:find?key=' . rawurlencode($key), $payload);

    // Belt and braces: make sure the key can never end up in output or logs.
    $redact = static fn(string $s): string => str_replace([$key, rawurlencode($key)], '[key]', $s);

    if ($r['error'] !== null) {
        return [HC_FAIL, $name, $redact($r['error'])];
    }
    if ($r['status'] !== 200) {
        $msg = json_decode($r['body'], true)['error']['message'] ?? 'no detail';
        return [HC_FAIL, $name, 'HTTP ' . $r['status'] . ': ' . $redact((string) $msg)];
    }
    $matches = json_decode($r['body'], true)['matches'] ?? [];
    return $matches
        ? [HC_PASS, $name, 'key works; test URL flagged as ' . ($matches[0]['threatType'] ?? 'threat') . " ({$r['ms']} ms)"]
        : [HC_WARN, $name, 'key accepted but test URL not flagged — check the threat types'];
}

function format_health_report(array $results): string
{
    $out = "SafeCedi health check — " . gmdate('Y-m-d H:i:s') . " UTC\n\n";
    foreach ($results as [$status, $check, $detail]) {
        $out .= sprintf("[%s] %-34s %s\n", $status, $check, $detail);
    }
    $fails = count(array_filter($results, static fn($r) => $r[0] === HC_FAIL));
    $out .= "\n" . ($fails === 0 ? 'No failures.' : "{$fails} check(s) FAILED.") . "\n";
    return $out;
}
