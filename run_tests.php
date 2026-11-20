<?php
declare(strict_types=1);

// Run with:  php tests/run_tests.php
require __DIR__ . '/../src/Rules.php';
require __DIR__ . '/../src/Engine.php';
require __DIR__ . '/../src/SsrfGuard.php';

use SafeCedi\Engine;
use SafeCedi\Rules;
use SafeCedi\SsrfGuard;

$passed = 0;
$failed = 0;
function check(string $name, bool $cond): void
{
    global $passed, $failed;
    if ($cond) { $passed++; echo "  ok    $name\n"; }
    else       { $failed++; echo "  FAIL  $name\n"; }
}
function codes(array $result): array
{
    return array_column($result['signals'], 'code');
}

$rules = Rules::starter();
const FLAG_THRESHOLD = 30; // provisional; Phase 2 justifies the real bands from corpus results

echo "\n== Rules: structure ==\n";
check('exactly ten rules', count($rules) === 10);
check('rule codes are unique', count(array_unique(array_column($rules, 'code'))) === 10);
$allCompile = true;
foreach ($rules as $r) {
    if (@preg_match($r['pattern'], '') === false) { $allCompile = false; echo "    bad pattern: {$r['code']}\n"; }
    if (isset($r['exclude']) && @preg_match($r['exclude'], '') === false) { $allCompile = false; echo "    bad exclude: {$r['code']}\n"; }
}
check('every pattern compiles', $allCompile);

echo "\n== Rules: each rule fires on its own example ==\n";
$positives = [
    'LANG_URGENCY'           => 'Reply within 2 hours or lose it',
    'LANG_SIM_BLOCK'         => 'Your SIM has been blocked',
    'LANG_PIN_REQUEST'       => 'Please send your PIN to this number',
    'LANG_DIAL_CODE'         => 'Dial *567# now',
    'LANG_MISTAKEN_TRANSFER' => 'Money was sent to you by mistake',
    'LANG_PRIZE'             => 'You have won a brand new car',
    'LANG_AUTHORITY'         => 'This is an MTN official speaking',
    'LANG_ACCOUNT_UPDATE'    => 'Update your MoMo account today',
    'LANG_CLICK_CTA'         => 'Click the link below',
    'LANG_ADVANCE_FEE'       => 'Send the clearance fee first',
];
foreach ($positives as $code => $text) {
    check("$code fires", in_array($code, codes(Engine::evaluate($text, $rules)), true));
}

echo "\n== Rules: false-positive guards ==\n";
check('"Never share your PIN" does not fire PIN rule',
    !in_array('LANG_PIN_REQUEST', codes(Engine::evaluate('Never share your PIN with anyone.', $rules)), true));
check('"We will never ask you to send your PIN" does not fire',
    !in_array('LANG_PIN_REQUEST', codes(Engine::evaluate('We will never ask you to send your PIN.', $rules)), true));
check('"Do not share your OTP" does not fire',
    !in_array('LANG_PIN_REQUEST', codes(Engine::evaluate('Do not share your OTP with anyone.', $rules)), true));
check('"Send your PIN now" still fires',
    in_array('LANG_PIN_REQUEST', codes(Engine::evaluate('Send your PIN now', $rules)), true));
check('warning first, real ask second: still fires',
    in_array('LANG_PIN_REQUEST', codes(Engine::evaluate('Never share your PIN. Send your PIN now.', $rules)), true));
check('curly apostrophe: "You’ve won"',
    in_array('LANG_PRIZE', codes(Engine::evaluate("You\u{2019}ve won GHS 5,000", $rules)), true));

echo "\n== Engine: properties ==\n";
$scam1 = 'Dear customer, your MTN SIM will be blocked within 24 hours. Dial *123*456# and send your PIN to verify.';
$a = Engine::evaluate($scam1, $rules);
$b = Engine::evaluate($scam1, $rules);
check('pure: same input gives identical output', $a === $b);
check('scam 1 scores high (>= 60)', $a['score'] >= 60);
check('scam 1 carries evidence for SIM block + PIN + urgency',
    count(array_intersect(['LANG_SIM_BLOCK', 'LANG_PIN_REQUEST', 'LANG_URGENCY'], codes($a))) === 3);
check('score capped at 100',
    Engine::evaluate($scam1 . ' You have won! Pay a small fee. I mistakenly sent money. Update your account. Click the link below. Official MTN agent.', $rules)['score'] <= 100);
check('empty text scores 0', Engine::evaluate('', $rules)['score'] === 0);
check('repeating a trigger does not inflate score',
    Engine::evaluate('urgent urgent urgent urgent', $rules)['score'] === 15);

$offsetText = 'xx Congratulations you are lucky';
$o = Engine::evaluate($offsetText, $rules);
$sig = $o['signals'][0] ?? null;
check('signal offsets point at the snippet in the original text',
    $sig !== null && mb_substr($offsetText, $sig['start'], $sig['length']) === $sig['snippet']);
$u = Engine::evaluate("Ama\u{2019}s cedi \u{20B5} urgent", $rules);
check('offsets stay correct after multibyte characters',
    isset($u['signals'][0]) && mb_substr("Ama\u{2019}s cedi \u{20B5} urgent", $u['signals'][0]['start'], $u['signals'][0]['length']) === $u['signals'][0]['snippet']);
check('invalid UTF-8 does not crash', is_array(Engine::evaluate("urgent \xB1\x31 bad bytes", $rules)));
check('very long input is truncated, not fatal', is_array(Engine::evaluate(str_repeat('a ', 50000) . 'urgent', $rules)));
$inactive = $rules; foreach ($inactive as &$r) { $r['active'] = false; } unset($r);
check('inactive rules are skipped', Engine::evaluate($scam1, $inactive)['score'] === 0);

echo "\n== Engine: sample scams are flagged, legitimate messages are not ==\n";
$scams = [
    $scam1,
    'I mistakenly sent GHS 500 to your number. Please send it back urgently.',
    "Congratulations! You've won GHS 10,000 in the MTN promo. Pay a small fee to claim your prize.",
];
$legit = [
    'Your MTN MoMo balance is GHS 45.20. Never share your PIN with anyone.',
    'Cash In received for GHS 100.00 from KWAME MENSAH. Current Balance GHS 120.00. Ref: 12345.',
    'Your parcel is out for delivery today. Our rider will call you on arrival.',
    'Dear customer, your account was credited with GHS 200.00. Dial *171# to check your balance.',
];
foreach ($scams as $i => $t) { check('scam #' . ($i + 1) . ' flagged', Engine::evaluate($t, $rules)['score'] >= FLAG_THRESHOLD); }
foreach ($legit as $i => $t) { check('legit #' . ($i + 1) . ' not flagged', Engine::evaluate($t, $rules)['score'] < FLAG_THRESHOLD); }

echo "\n== SSRF guard (stub resolver, runs offline) ==\n";
$dns = [
    'good.test'      => ['93.184.216.34'],
    'localhost'      => ['127.0.0.1'],
    'evil.test'      => ['93.184.216.34', '10.0.0.1'],  // one bad IP among good ones
    'v6doc.test'     => ['2001:db8::1'],
    'v6local.test'   => ['fe80::1'],
    'metadata.test'  => ['169.254.169.254'],
];
$stub = static fn(string $h): array => $dns[$h] ?? [];

$ok = SsrfGuard::check('https://good.test/path?x=1', $stub);
check('public https URL allowed', $ok['ok'] === true && $ok['host'] === 'good.test' && $ok['port'] === 443);
check('uppercase scheme allowed', SsrfGuard::check('HTTP://good.test', $stub)['ok'] === true);
check('pinned resolve entry built', SsrfGuard::pinnedResolveEntry($ok) === 'good.test:443:93.184.216.34');

$refused = [
    'loopback literal 127.0.0.1'     => 'http://127.0.0.1/',
    'loopback 127.1.2.3'             => 'http://127.1.2.3/',
    'cloud metadata 169.254.169.254' => 'http://169.254.169.254/latest/meta-data/',
    'private 10.0.0.5'               => 'http://10.0.0.5/',
    'private 192.168.1.1'            => 'http://192.168.1.1/',
    'private 172.16.0.1'             => 'http://172.16.0.1/',
    'CGNAT 100.64.0.1'               => 'http://100.64.0.1/',
    'unspecified 0.0.0.0'            => 'http://0.0.0.0/',
    'IPv6 loopback [::1]'            => 'http://[::1]/',
    'IPv4-mapped IPv6 loopback'      => 'http://[::ffff:127.0.0.1]/',
    'decimal-encoded 2130706433'     => 'http://2130706433/',
    'hex-encoded 0x7f.0.0.1'         => 'http://0x7f.0.0.1/',
    'localhost (resolves to 127.0.0.1)' => 'http://localhost/',
    'localhost with trailing dot'    => 'http://localhost./',
    'name resolving to metadata IP'  => 'http://metadata.test/',
    'mixed good + private DNS answer'=> 'http://evil.test/',
    'IPv6 documentation range'       => 'http://v6doc.test/',
    'IPv6 link-local'                => 'http://v6local.test/',
    'ftp scheme'                     => 'ftp://good.test/file',
    'file scheme'                    => 'file:///etc/passwd',
    'gopher scheme'                  => 'gopher://good.test/',
    'userinfo trick'                 => 'http://trusted.com@good.test/',
    'non-standard port 8080'         => 'http://good.test:8080/',
    'port 22'                        => 'http://good.test:22/',
    'does not resolve'               => 'http://nxdomain.test/',
    'empty string'                   => '',
    'whitespace inside URL'          => 'http://good.test/a b',
    'no scheme'                      => 'good.test/path',
];
foreach ($refused as $label => $url) {
    $r = SsrfGuard::check($url, $stub);
    check("refuses $label" . ($r['ok'] ? '' : "  [{$r['reason']}]"), $r['ok'] === false);
}
check('constants: 5 hops max', SsrfGuard::MAX_HOPS === 5);
check('unparseable IP string is blocked (fail closed)', SsrfGuard::isBlockedIp('not-an-ip') === true);
check('public IPv6 allowed', SsrfGuard::isBlockedIp('2606:4700:4700::1111') === false);

echo "\n";
echo "Passed: $passed   Failed: $failed\n";
exit($failed === 0 ? 0 : 1);
