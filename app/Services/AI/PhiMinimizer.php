<?php

namespace App\Services\AI;

/**
 * PHI minimization (PRD §8: "No real PHI is sent to external AI without
 * minimization"). Strips the obvious direct identifiers — emails, phone
 * numbers, long digit runs (MRNs/SSNs), and any explicitly supplied names —
 * before text leaves our servers for Gemini/OpenAI.
 *
 * This is a pragmatic best-effort scrubber, not a certified de-identifier.
 * It runs only when config('services.ai.minimize_phi') is true.
 */
class PhiMinimizer
{
    /** Replace direct identifiers in a single string. */
    public static function scrub(string $text, array $names = []): string
    {
        if (! config('services.ai.minimize_phi', true)) {
            return $text;
        }

        // Email addresses.
        $text = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/u', '[EMAIL]', $text);

        // Phone numbers / long digit runs (7+ digits, allowing separators).
        $text = preg_replace('/\+?\d[\d\s().-]{6,}\d/u', '[CONTACT]', $text);

        // Explicitly known names (patient/provider) — whole word, case-insensitive.
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                foreach (preg_split('/\s+/', $name) as $part) {
                    if (mb_strlen($part) >= 3) {
                        $text = preg_replace('/\b'.preg_quote($part, '/').'\b/iu', '[NAME]', $text);
                    }
                }
            }
        }

        return $text;
    }

    /** Recursively scrub all string values in an array (e.g. intake responses). */
    public static function scrubArray(array $data, array $names = []): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = self::scrub($value, $names);
            } elseif (is_array($value)) {
                $data[$key] = self::scrubArray($value, $names);
            }
        }

        return $data;
    }
}
