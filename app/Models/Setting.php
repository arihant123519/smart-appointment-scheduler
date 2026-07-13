<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Simple key/value application settings store.
 *
 * Used to hold integration credentials (Email / SMS / WhatsApp / Payments)
 * that are edited from the admin UI instead of the .env file. Secret values
 * are encrypted at rest. Rows are cached per clinic so reads are cheap; the
 * cache is cleared whenever a value for that clinic is written.
 *
 * Integration credentials are clinic-scoped (`clinic_id` set) — each clinic
 * admin edits only their own clinic's row via Settings → Integrations, and a
 * system admin can view/edit any clinic's row there too. A handful of
 * settings remain intentionally global (`clinic_id` null) — e.g. appointment
 * notification lead-times — and keep using the unscoped get()/set()/has().
 */
class Setting extends Model
{
    protected $fillable = ['clinic_id', 'key', 'value', 'group', 'is_encrypted'];

    protected $casts = ['is_encrypted' => 'boolean'];

    public const CACHE_KEY = 'app.settings.all';

    /** @return array<string, array{value: ?string, group: string, is_encrypted: bool}> */
    protected static function cached(?int $clinicId = null): array
    {
        $cacheKey = $clinicId === null ? self::CACHE_KEY : self::CACHE_KEY.'.clinic.'.$clinicId;

        return Cache::rememberForever($cacheKey, fn () => static::query()
            ->where('clinic_id', $clinicId)
            ->get()
            ->keyBy('key')
            ->map(fn (self $s) => [
                'value' => $s->value,
                'group' => $s->group,
                'is_encrypted' => (bool) $s->is_encrypted,
            ])
            ->toArray());
    }

    /** Read a global (non-clinic-scoped) setting, decrypting secrets transparently. */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::resolve(null, $key, $default);
    }

    /** Whether a non-empty value is stored for the (global) key. */
    public static function has(string $key): bool
    {
        return self::exists(null, $key);
    }

    /** Create or update a global setting and bust its cache. */
    public static function set(string $key, ?string $value, string $group = 'general', bool $encrypted = false): void
    {
        self::write(null, $key, $value, $group, $encrypted);
    }

    /** Read a clinic-scoped setting, decrypting secrets transparently. */
    public static function getForClinic(int $clinicId, string $key, mixed $default = null): mixed
    {
        return self::resolve($clinicId, $key, $default);
    }

    /** Whether a non-empty value is stored for this clinic + key. */
    public static function hasForClinic(int $clinicId, string $key): bool
    {
        return self::exists($clinicId, $key);
    }

    /** Create or update a clinic-scoped setting and bust that clinic's cache. */
    public static function setForClinic(int $clinicId, string $key, ?string $value, string $group = 'general', bool $encrypted = false): void
    {
        self::write($clinicId, $key, $value, $group, $encrypted);
    }

    /**
     * Find which clinic a shared secret belongs to (webhook authentication):
     * scans every clinic's stored value for this key and returns the first
     * clinic whose (decrypted) value matches, or null if none do. Used by
     * inbound webhooks (Gupshup, SMS, Razorpay) which arrive with no session
     * to identify the clinic from otherwise.
     */
    public static function resolveClinicBySecret(string $key, string $provided): ?int
    {
        if ($provided === '') {
            return null;
        }

        foreach (static::query()->whereNotNull('clinic_id')->where('key', $key)->get() as $row) {
            $value = $row->value;
            if ($row->is_encrypted && $value) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (\Throwable) {
                    continue;
                }
            }
            if ($value && hash_equals($value, $provided)) {
                return $row->clinic_id;
            }
        }

        return null;
    }

    private static function resolve(?int $clinicId, string $key, mixed $default): mixed
    {
        $row = static::cached($clinicId)[$key] ?? null;

        if (! $row || $row['value'] === null || $row['value'] === '') {
            return $default;
        }

        if ($row['is_encrypted']) {
            try {
                return Crypt::decryptString($row['value']);
            } catch (\Throwable) {
                return $default;
            }
        }

        return $row['value'];
    }

    private static function exists(?int $clinicId, string $key): bool
    {
        $row = static::cached($clinicId)[$key] ?? null;

        return $row !== null && $row['value'] !== null && $row['value'] !== '';
    }

    private static function write(?int $clinicId, string $key, ?string $value, string $group, bool $encrypted): void
    {
        $stored = ($encrypted && $value !== null && $value !== '')
            ? Crypt::encryptString($value)
            : $value;

        static::updateOrCreate(
            ['clinic_id' => $clinicId, 'key' => $key],
            ['value' => $stored, 'group' => $group, 'is_encrypted' => $encrypted],
        );

        $cacheKey = $clinicId === null ? self::CACHE_KEY : self::CACHE_KEY.'.clinic.'.$clinicId;
        Cache::forget($cacheKey);
    }
}
