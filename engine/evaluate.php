<?php
declare(strict_types=1);

/** Owner: M3. The entry point the app calls (docs/integration-contract.md). */

require_once __DIR__ . '/Rules.php';
require_once __DIR__ . '/Engine.php';

use SafeCedi\Engine;
use SafeCedi\Rules;

/** Provisional bands. Phase 2 justifies the cut-offs from corpus results. */
function score_band(int $score): string
{
    return match (true) {
        $score >= 60 => 'Dangerous',
        $score >= 30 => 'Suspicious',
        $score >= 15 => 'Unclear',
        default      => 'Looks Safe',
    };
}

function evaluate(string $text): array
{
    $r = Engine::evaluate($text, Rules::starter());
    $r['band'] = score_band($r['score']);
    foreach ($r['signals'] as &$s) {
        $s['rule']  = $s['code'];
        $s['match'] = $s['snippet'];
    }
    unset($s);
    return $r;
}