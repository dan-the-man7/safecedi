<?php
declare(strict_types=1);

namespace SafeCedi;

/**
 * The ten Phase 1 language rules.
 *
 * Each rule has the same shape as a row in the `rules` table
 * (code, category, pattern, weight, active) plus one optional extra:
 *
 *   exclude  - a regex tested against the ~50 characters BEFORE a match.
 *              If it matches, that hit is ignored. This is how
 *              "Never share your PIN" (a legitimate warning) does not
 *              fire the PIN-solicitation rule.
 *
 * Ask M2 to add a nullable `exclude_pattern` column to the rules table.
 * In Phase 2 Rules::starter() is replaced by a DB read returning the same shape;
 * Engine::evaluate() does not change.
 *
 * Apostrophes are written as [\x27\x{2019}] so both ' and the curly ’
 * (which phones produce) are matched.
 */
final class Rules
{
    public static function starter(): array
    {
        return [
            [
                'code' => 'LANG_URGENCY',
                'category' => 'language',
                'weight' => 15,
                'active' => true,
                'pattern' => '~\b(?:within\s+\d+\s*(?:hours?|hrs?|minutes?|mins?)|immediately|right\s+now|urgent(?:ly)?|last\s+warning|expires?\s+(?:today|soon)|act\s+now)\b~iu',
            ],
            [
                'code' => 'LANG_SIM_BLOCK',
                'category' => 'language',
                'weight' => 25,
                'active' => true,
                'pattern' => '~\b(?:sim|line|number|account|wallet|momo)\b[^.]{0,40}?\b(?:will\s+be|has\s+been|have\s+been|been)\s+(?:blocked|suspended|deactivated|barred|closed|frozen|locked)\b~iu',
            ],
            [
                'code' => 'LANG_PIN_REQUEST',
                'category' => 'language',
                'weight' => 35,
                'active' => true,
                'pattern' => '~\b(?:send|share|give|provide|reply\s+with|tell|confirm|verify)\b[^.]{0,30}?\b(?:pin|password|otp|passcode|one[- ]time\s+(?:code|password)|verification\s+code|secret\s+code)\b~iu',
                'exclude' => '~\b(?:never|do\s+not|don[\x27\x{2019}]t|won[\x27\x{2019}]t|will\s+not|should\s+not|shouldn[\x27\x{2019}]t)\b~iu',
            ],
            [
                'code' => 'LANG_DIAL_CODE',
                'category' => 'language',
                'weight' => 15,
                'active' => true,
                'pattern' => '~\*\d{2,4}[*\d]*#|\bdial\s+(?:this\s+|the\s+)?code\b~iu',
            ],
            [
                'code' => 'LANG_MISTAKEN_TRANSFER',
                'category' => 'language',
                'weight' => 30,
                'active' => true,
                'pattern' => '~\b(?:mistakenly|wrongly|accidentally)\s+(?:sent|transferred|credited|paid)\b|\b(?:sent|transferred|credited)\b[^.]{0,30}?\b(?:by\s+mistake|in\s+error|wrongly)\b|\bplease\s+(?:send|return|refund|reverse)\b[^.]{0,25}?\bback\b~iu',
            ],
            [
                'code' => 'LANG_PRIZE',
                'category' => 'language',
                'weight' => 25,
                'active' => true,
                'pattern' => '~\b(?:you[\x27\x{2019}]?(?:ve|\s+have)\s+(?:won|been\s+(?:selected|chosen))|congratulations|lucky\s+(?:draw|winner)|claim\s+(?:your\s+)?(?:prize|reward|gift)|jackpot|promo\s+winner)\b~iu',
            ],
            [
                'code' => 'LANG_AUTHORITY',
                'category' => 'language',
                'weight' => 15,
                'active' => true,
                'pattern' => '~\b(?:official|officer|agent|representative|department|headquarters)\b[^.]{0,30}?\b(?:mtn|telecel|vodafone|airteltigo|momo|mobile\s+money|bank\s+of\s+ghana|gra|police|cid)\b|\b(?:mtn|telecel|vodafone|airteltigo|momo|mobile\s+money)\b[^.]{0,30}?\b(?:official|officer|agent|representative|department|headquarters)\b~iu',
            ],
            [
                'code' => 'LANG_ACCOUNT_UPDATE',
                'category' => 'language',
                'weight' => 15,
                'active' => true,
                'pattern' => '~\b(?:update|verify|re-?register|upgrade|validate|confirm)\b[^.]{0,25}?\b(?:kyc|momo|account|wallet|sim|registration|ghana\s+card)\b~iu',
            ],
            [
                'code' => 'LANG_CLICK_CTA',
                'category' => 'language',
                'weight' => 10,
                'active' => true,
                'pattern' => '~\b(?:click|tap|follow|open|visit)\b[^.]{0,20}?\b(?:link|here|below|url)\b~iu',
            ],
            [
                'code' => 'LANG_ADVANCE_FEE',
                'category' => 'language',
                'weight' => 25,
                'active' => true,
                'pattern' => '~\b(?:processing|registration|clearance|activation|release|customs)\s+fee\b|\bpay\s+(?:a\s+)?(?:small\s+)?fee\b~iu',
            ],
        ];
    }
}
