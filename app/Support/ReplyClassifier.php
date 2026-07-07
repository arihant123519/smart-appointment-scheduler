<?php

namespace App\Support;

/**
 * Classifies a short WhatsApp reply as yes/no for FlowEngine's `branch_yes_no`
 * nodes. Deliberately simple keyword matching, not an AI call — a synchronous
 * webhook request classifying a 1-3 word reply doesn't need a provider
 * round-trip, and this is reliable-by-default rather than "AI with a fallback."
 */
class ReplyClassifier
{
    /** Checked first — negation wins over an incidental positive word (e.g. "not sure"). */
    private const NO_WORDS = [
        'no', 'nope', 'nah', 'not', "can't", 'cant', "don't", 'dont', 'change', 'different',
        'reschedule', 'another', 'unable', 'wrong', 'n',
    ];

    private const YES_WORDS = [
        'yes', 'yeah', 'yep', 'yup', 'sure', 'ok', 'okay', 'confirm', 'confirmed',
        'works', 'good', 'fine', 'great', 'perfect', 'alright', 'correct', 'right', 'y',
    ];

    /** @return string|null 'yes' | 'no' | null (unclear) */
    public static function classifyYesNo(string $text): ?string
    {
        $normalized = strtolower(trim($text));

        if ($normalized === '') {
            return null;
        }

        if (self::containsAny($normalized, self::NO_WORDS)) {
            return 'no';
        }

        if (self::containsAny($normalized, self::YES_WORDS)) {
            return 'yes';
        }

        return null;
    }

    /** @param  array<int, string>  $words */
    private static function containsAny(string $normalized, array $words): bool
    {
        foreach ($words as $word) {
            if (preg_match('/(?<![a-z])'.preg_quote($word, '/').'(?![a-z])/i', $normalized)) {
                return true;
            }
        }

        return false;
    }
}
