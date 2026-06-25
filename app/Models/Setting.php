<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Simple key/value application settings store.
 *
 * Used to hold integration credentials (Email / SMS / WhatsApp) that are edited
 * from the admin UI instead of the .env file. Secret values are encrypted at
 * rest. All rows are cached together so reads are cheap; the cache is cleared
 * whenever a value is written.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'is_encrypted'];

    protected $casts = ['is_encrypted' => 'boolean'];

    public const CACHE_KEY = 'app.settings.all';

    /** @return array<string, array{value: ?string, group: string, is_encrypted: bool}> */
    protected static function cached(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->get()
            ->keyBy('key')
            ->map(fn (self $s) => [
                'value' => $s->value,
                'group' => $s->group,
                'is_encrypted' => (bool) $s->is_encrypted,
            ])
            ->toArray());
    }

    /** Read a setting, decrypting secrets transparently. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::cached()[$key] ?? null;

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

    /** Whether a non-empty value is stored for the key. */
    public static function has(string $key): bool
    {
        $row = static::cached()[$key] ?? null;

        return $row !== null && $row['value'] !== null && $row['value'] !== '';
    }

    /** Create or update a setting and bust the cache. */
    public static function set(string $key, ?string $value, string $group = 'general', bool $encrypted = false): void
    {
        $stored = ($encrypted && $value !== null && $value !== '')
            ? Crypt::encryptString($value)
            : $value;

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'group' => $group, 'is_encrypted' => $encrypted],
        );

        Cache::forget(self::CACHE_KEY);
    }
}
