<?php
declare(strict_types=1);

namespace SafeCedi;

/**
 * The rule engine.
 *
 * evaluate() is a PURE function: same text + same rules => same result.
 * No database, no clock, no network, no globals. That is what makes it
 * testable against the corpus and re-runnable on old submissions (Phase 3).
 *
 * Result shape (this is what M4's evidence panel and M2's verdict_signals use):
 *   [
 *     'score'          => int 0..100,
 *     'raw_score'      => int (sum of weights before the cap),
 *     'signals'        => [ ['code','category','weight','snippet','start','length'], ... ],
 *     'engine_version' => string,
 *   ]
 *
 * 'start' and 'length' are CHARACTER offsets into the (truncated) original
 * text, so M4 can highlight the matched snippet inline.
 *
 * Each rule fires at most once, so repeating "urgent urgent urgent" cannot
 * inflate the score.
 */
final class Engine
{
    public const VERSION = '0.1.0-phase1';
    public const MAX_INPUT_CHARS = 2000;   // bounds regex work on hostile input
    public const MAX_SCORE = 100;

    /** @param array<int,array<string,mixed>> $rules */
    public static function evaluate(string $text, array $rules): array
    {
        // Scrub invalid UTF-8 so the /u regexes never silently fail.
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        $text = mb_substr($text, 0, self::MAX_INPUT_CHARS, 'UTF-8');

        $signals = [];
        $raw = 0;

        foreach ($rules as $rule) {
            if (empty($rule['active'])) {
                continue;
            }
            $hit = self::applyRule($text, $rule);
            if ($hit === null) {
                continue;
            }
            $signals[] = $hit;
            $raw += $hit['weight'];
        }

        return [
            'score' => min(self::MAX_SCORE, $raw),
            'raw_score' => $raw,
            'signals' => $signals,
            'engine_version' => self::VERSION,
        ];
    }

    /** @return array<string,mixed>|null */
    private static function applyRule(string $text, array $rule): ?array
    {
        $n = @preg_match_all($rule['pattern'], $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        if ($n === false || $n === 0) {
            return null; // bad pattern or no match: fail quiet, never crash the page
        }

        foreach ($matches as $m) {
            [$snippet, $byteOffset] = $m[0];

            // Context check: ignore hits preceded by a negation like "never".
            if (!empty($rule['exclude'])) {
                $before = mb_strcut($text, max(0, $byteOffset - 50), min(50, $byteOffset), 'UTF-8');
                // Only the current sentence counts: a "never" in an earlier
                // sentence must not cancel a real ask in this one.
                if (preg_match('~[^.!?\n]*$~u', $before, $tail) === 1) {
                    $before = $tail[0];
                }
                if (@preg_match($rule['exclude'], $before) === 1) {
                    continue;
                }
            }

            return [
                'code' => (string) $rule['code'],
                'category' => (string) $rule['category'],
                'weight' => (int) $rule['weight'],
                'snippet' => $snippet,
                'start' => mb_strlen(substr($text, 0, $byteOffset), 'UTF-8'),
                'length' => mb_strlen($snippet, 'UTF-8'),
            ];
        }

        return null;
    }
}
