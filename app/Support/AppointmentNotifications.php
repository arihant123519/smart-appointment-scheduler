<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Configuration for the Appointment Notifications feature.
 *
 * Two pieces, both stored as JSON in the settings store:
 *  - Lead times: when (and on which channel) to remind before a Booked /
 *    Confirmed appointment. Each entry is {hours, email, whatsapp}.
 *  - Status notifications: whether a status change fires an immediate email /
 *    WhatsApp message.
 *
 * The dispatch is performed by {@see \App\Services\AppointmentNotificationService};
 * this class only reads / writes the configuration.
 */
class AppointmentNotifications
{
    public const LEAD_TIMES_KEY = 'appointments.lead_times';
    public const STATUS_KEY = 'appointments.status_notify';
    public const REPEAT_KEY = 'appointments.repeat';

    // --- Lead times --------------------------------------------------------

    /**
     * @return array<int, array{hours: int, email: bool, whatsapp: bool}>
     */
    public static function leadTimes(): array
    {
        $raw = Setting::get(self::LEAD_TIMES_KEY);

        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_map([self::class, 'normalizeLeadTime'], $decoded));
            }
        }

        return self::defaultLeadTimes();
    }

    /**
     * @param  array<int, array<string, mixed>>  $leadTimes
     */
    public static function saveLeadTimes(array $leadTimes): void
    {
        $clean = [];
        foreach ($leadTimes as $lt) {
            $lt = self::normalizeLeadTime($lt);

            // Keep only rows with a positive lead time and at least one channel.
            if ($lt['hours'] > 0 && ($lt['email'] || $lt['whatsapp'])) {
                $clean[] = $lt;
            }
        }

        Setting::set(self::LEAD_TIMES_KEY, json_encode(array_values($clean)), 'appointments');
    }

    /**
     * @param  array<string, mixed>  $lt
     * @return array{hours: int, email: bool, whatsapp: bool}
     */
    public static function normalizeLeadTime(array $lt): array
    {
        return [
            'hours' => max(0, (int) ($lt['hours'] ?? 0)),
            'email' => filter_var($lt['email'] ?? false, FILTER_VALIDATE_BOOL),
            'whatsapp' => filter_var($lt['whatsapp'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    /**
     * @return array<int, array{hours: int, email: bool, whatsapp: bool}>
     */
    public static function defaultLeadTimes(): array
    {
        // WhatsApp-only by default so we don't duplicate the legacy email
        // reminder cadence (`reminders:send`). Admins can enable email per row.
        return [
            ['hours' => 24, 'email' => false, 'whatsapp' => true],
            ['hours' => 2, 'email' => false, 'whatsapp' => true],
        ];
    }

    // --- Status-change notifications --------------------------------------

    /**
     * @return array{enabled: bool, email: bool, whatsapp: bool}
     */
    public static function statusNotify(): array
    {
        $raw = Setting::get(self::STATUS_KEY);

        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return self::normalizeStatus($decoded);
            }
        }

        return ['enabled' => true, 'email' => true, 'whatsapp' => true];
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    public static function saveStatusNotify(array $cfg): void
    {
        Setting::set(self::STATUS_KEY, json_encode(self::normalizeStatus($cfg)), 'appointments');
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array{enabled: bool, email: bool, whatsapp: bool}
     */
    public static function normalizeStatus(array $cfg): array
    {
        return [
            'enabled' => filter_var($cfg['enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'email' => filter_var($cfg['email'] ?? false, FILTER_VALIDATE_BOOL),
            'whatsapp' => filter_var($cfg['whatsapp'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    // --- Repeat reminder ("every N hours until the appointment") -----------

    /**
     * @return array{enabled: bool, hours: int, email: bool, whatsapp: bool}
     */
    public static function repeat(): array
    {
        $raw = Setting::get(self::REPEAT_KEY);

        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return self::normalizeRepeat($decoded);
            }
        }

        return ['enabled' => false, 'hours' => 1, 'email' => false, 'whatsapp' => true];
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    public static function saveRepeat(array $cfg): void
    {
        Setting::set(self::REPEAT_KEY, json_encode(self::normalizeRepeat($cfg)), 'appointments');
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array{enabled: bool, hours: int, email: bool, whatsapp: bool}
     */
    public static function normalizeRepeat(array $cfg): array
    {
        return [
            'enabled' => filter_var($cfg['enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'hours' => max(0, (int) ($cfg['hours'] ?? 0)),
            'email' => filter_var($cfg['email'] ?? false, FILTER_VALIDATE_BOOL),
            'whatsapp' => filter_var($cfg['whatsapp'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }
}
