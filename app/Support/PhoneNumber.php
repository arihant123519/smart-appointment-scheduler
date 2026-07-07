<?php

namespace App\Support;

/**
 * Normalizes a phone number to digits-only (country code, no `+`/spaces/dashes) —
 * the format Gupshup expects for `source`/`destination` and the format used to
 * match an inbound webhook sender back to a User or WhatsappConversation.
 */
class PhoneNumber
{
    public static function normalize(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }
}
