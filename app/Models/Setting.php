<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * App-level key-value settings, cached so reads (e.g. feature toggles checked in
 * scheduled commands and requests) don't hit the DB every time.
 */
class Setting extends Model
{
    /** @var list<string> */
    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'settings.all';

    /**
     * @return array<string, string|null>
     */
    private static function cached(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::cached()[$key] ?? $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default ? '1' : '0');

        return $value === '1' || $value === 'true';
    }

    public static function set(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }
}
