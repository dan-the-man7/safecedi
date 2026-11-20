<?php
declare(strict_types=1);

// Run with:  php tests/corpus_report.php [path/to/corpus.csv] [threshold]
//
// Expects a CSV with a header row: label,text
//   label = scam | legit
// (M5 owns the official harness; this is M3's quick check while tuning rules.
//  Agree the column names with M2 and adjust the two lines marked ADJUST.)

require __DIR__ . '/../src/Rules.php';
require __DIR__ . '/../src/Engine.php';

use SafeCedi\Engine;
use SafeCedi\Rules;

$path = $argv[1] ?? __DIR__ . '/../corpus/corpus.csv';
$threshold = (int) ($argv[2] ?? 30);

if (!is_file($path)) {
    fwrite(STDERR, "Corpus not found: $path\n");
    exit(1);
}

$rules = Rules::starter();
$tp = $fp = $fn = $tn = 0;
$misses = [];

$fh = fopen($path, 'r');
$header = fgetcsv($fh, 0, ',', '"', '');
$labelCol = array_search('label', $header, true); // ADJUST if M2 names it differently
$textCol  = array_search('text', $header, true);  // ADJUST
if ($labelCol === false || $textCol === false) {
    fwrite(STDERR, "CSV needs 'label' and 'text' columns. Found: " . implode(',', $header) . "\n");
    exit(1);
}

while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
    if (!isset($row[$labelCol], $row[$textCol])) { continue; }
    $isScam = strtolower(trim($row[$labelCol])) === 'scam';
    $result = Engine::evaluate($row[$textCol], $rules);
    $flagged = $result['score'] >= $threshold;

    if ($isScam && $flagged)       { $tp++; }
    elseif (!$isScam && $flagged)  { $fp++; $misses[] = ['FALSE POSITIVE', $result['score'], $row[$textCol]]; }
    elseif ($isScam && !$flagged)  { $fn++; $misses[] = ['MISSED SCAM', $result['score'], $row[$textCol]]; }
    else                           { $tn++; }
}
fclose($fh);

$precision = ($tp + $fp) > 0 ? $tp / ($tp + $fp) : 0.0;
$recall    = ($tp + $fn) > 0 ? $tp / ($tp + $fn) : 0.0;

printf("Engine %s, threshold %d\n", Engine::VERSION, $threshold);
printf("TP=%d  FP=%d  FN=%d  TN=%d\n", $tp, $fp, $fn, $tn);
printf("Precision: %.1f%%   Recall: %.1f%%\n\n", $precision * 100, $recall * 100);

foreach ($misses as [$kind, $score, $text]) {
    printf("[%s] score=%d  %s\n", $kind, $score, mb_substr($text, 0, 110));
}
