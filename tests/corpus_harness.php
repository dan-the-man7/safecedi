<?php
/**
 * Corpus harness. Owner: M5 (Security, QA & Evidence).
 *
 * Runs every labelled message in the corpus through the real engine and
 * prints precision and recall. This is the number every rule change is
 * measured against, and the table that goes in the README.
 *
 * Usage (command line only):
 *   php tests/corpus_harness.php                       # default corpus
 *   php tests/corpus_harness.php path/to/corpus.csv
 *   php tests/corpus_harness.php path/to/corpus.csv --quiet   # numbers only
 *
 * Corpus format: CSV with a header row containing a label column and a text column.
 *   label: "scam", or "legit" / "legitimate"
 *   text:  the message (a column called "message" is also accepted)
 * Agree the final file name and columns with M2 (who owns the corpus) and
 * M3 (whose corpus_report.php reads the same file).
 *
 * What counts as "flagged": band Suspicious or Dangerous = predicted scam;
 * Looks Safe or Unclear = predicted legitimate. Agreed with M3 in Phase 01.
 *
 * Exit code: 0 if it ran, 1 if the corpus or engine is missing or malformed.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const FLAGGED_BANDS = ['Suspicious', 'Dangerous'];

$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$quiet = in_array('--quiet', $args, true);
$args = array_values(array_filter($args, static fn(string $a): bool => $a !== '--quiet'));
$corpusPath = $args[0] ?? $root . '/database/corpus.csv';

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

// The same entry point the website uses, so we measure what users get.
$enginePath = $root . '/engine/evaluate.php';
if (!is_file($enginePath)) {
    fail("Engine not found: {$enginePath}\n(M3's engine/evaluate.php must be merged first.)");
}
require_once $enginePath;
if (!function_exists('evaluate')) {
    fail("{$enginePath} does not define evaluate(string \$text): array.");
}

if (!is_file($corpusPath)) {
    fail("Corpus not found: {$corpusPath}\nPass the path as the first argument.");
}
$fh = fopen($corpusPath, 'r');
if ($fh === false) {
    fail("Cannot open {$corpusPath}");
}

$header = fgetcsv($fh, 0, ',', '"', '');
if ($header === false) {
    fail('The corpus is empty.');
}
// Strip a UTF-8 byte-order mark (Excel adds one) and normalise names.
$header = array_map(
    static fn($h): string => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))),
    $header
);
$labelCol = array_search('label', $header, true);
$textCol = array_search('text', $header, true);
if ($textCol === false) {
    $textCol = array_search('message', $header, true);
}
if ($labelCol === false || $textCol === false) {
    fail("The CSV needs a 'label' column and a 'text' (or 'message') column. Found: " . implode(', ', $header));
}

$tp = $fp = $tn = $fn = 0;
$mistakes = [];
$ruleFires = [];
$line = 1;
$skipped = 0;

while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
    $line++;
    if ($row === [null]) {
        continue; // blank line
    }
    $label = strtolower(trim((string) ($row[$labelCol] ?? '')));
    $text = (string) ($row[$textCol] ?? '');

    if ($label === 'scam') {
        $isScam = true;
    } elseif ($label === 'legit' || $label === 'legitimate') {
        $isScam = false;
    } else {
        fwrite(STDERR, "Line {$line}: unknown label '{$label}', skipped\n");
        $skipped++;
        continue;
    }

    $result = evaluate($text);
    $band = (string) ($result['band'] ?? '');
    $flagged = in_array($band, FLAGGED_BANDS, true);

    foreach ($result['signals'] ?? [] as $signal) {
        $rule = (string) ($signal['rule'] ?? '?');
        $ruleFires[$rule] ??= ['scam' => 0, 'legit' => 0];
        $ruleFires[$rule][$isScam ? 'scam' : 'legit']++;
    }

    if ($isScam && $flagged) {
        $tp++;
    } elseif (!$isScam && $flagged) {
        $fp++;
        $mistakes[] = ['FALSE POSITIVE', $line, $band, (int) ($result['score'] ?? 0), $text];
    } elseif ($isScam && !$flagged) {
        $fn++;
        $mistakes[] = ['MISSED SCAM', $line, $band, (int) ($result['score'] ?? 0), $text];
    } else {
        $tn++;
    }
}
fclose($fh);

$total = $tp + $fp + $tn + $fn;
if ($total === 0) {
    fail('No labelled messages found.');
}

$pct = static fn(int $num, int $den): string => $den > 0 ? sprintf('%.1f%%', 100 * $num / $den) : 'n/a';

echo "SafeCedi corpus results\n";
echo "-----------------------\n";
echo 'Corpus:            ' . $corpusPath . "\n";
echo 'Flagged means:     ' . implode(' or ', FLAGGED_BANDS) . "\n";
echo "Messages:          {$total} (" . ($tp + $fn) . ' scam, ' . ($fp + $tn) . " legitimate)" . ($skipped ? ", {$skipped} skipped" : '') . "\n\n";
echo "                   Flagged   Not flagged\n";
printf("Actually scam      %7d   %11d\n", $tp, $fn);
printf("Actually legit     %7d   %11d\n\n", $fp, $tn);
echo 'Precision:         ' . $pct($tp, $tp + $fp) . "   (of the messages we flagged, how many were scams)\n";
echo 'Recall:            ' . $pct($tp, $tp + $fn) . "   (of the scams, how many we flagged)\n";
echo 'Accuracy:          ' . $pct($tp + $tn, $total) . "\n";

if ($quiet) {
    exit(0);
}

if ($ruleFires !== []) {
    ksort($ruleFires);
    echo "\nRule fires (on scams / on legitimate messages)\n";
    foreach ($ruleFires as $rule => $c) {
        printf("  %-26s %3d / %d%s\n", $rule, $c['scam'], $c['legit'], $c['legit'] > 0 ? '   <- fires on legitimate messages' : '');
    }
}

if ($mistakes !== []) {
    echo "\nMistakes\n";
    foreach ($mistakes as [$kind, $ln, $band, $score, $text]) {
        $short = mb_substr(preg_replace('/\s+/u', ' ', $text) ?? $text, 0, 100);
        printf("  [%s] line %d, %s %d: %s\n", $kind, $ln, $band !== '' ? $band : 'no band', $score, $short);
    }
}

exit(0);
