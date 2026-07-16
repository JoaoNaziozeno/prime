<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saved(function (Setting $setting) {
            Cache::forget(self::getCacheKey($setting->key));
        });

        static::deleted(function (Setting $setting) {
            Cache::forget(self::getCacheKey($setting->key));
        });
    }

    protected static function getCacheKey(string $key): string
    {
        $tenantId = function_exists('tenant') && tenant() ? tenant('id') : 'global';
        return "tenant_{$tenantId}_setting_{$key}";
    }

    /**
     * Get setting value by key with auto-casting
     */
    public static function get(string $key, $default = null)
    {
        $cacheKey = self::getCacheKey($key);

        $cached = Cache::remember($cacheKey, now()->addHours(24), function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            if (!$setting) {
                return [
                    'exists' => false,
                    'value' => $default,
                ];
            }

            return [
                'exists' => true,
                'value' => self::castValue($setting->value, $setting->type),
            ];
        });

        return $cached['value'];
    }

    /**
     * Set or update setting value
     */
    public static function set(string $key, $value, ?string $type = null, ?string $group = 'general'): self
    {
        if (is_null($type)) {
            $type = self::inferType($value);
        }

        $rawValue = $type === 'json' ? json_encode($value) : $value;

        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $rawValue,
                'type' => $type,
                'group' => $group,
            ]
        );
    }

    /**
     * Cast string value to native type
     */
    protected static function castValue($value, string $type)
    {
        if (is_null($value)) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float'   => (float) $value,
            'json'    => json_decode($value, true),
            default   => (string) $value,
        };
    }

    /**
     * Infer setting type based on variable type
     */
    protected static function inferType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_array($value) || is_object($value)) {
            return 'json';
        }
        return 'string';
    }
}
