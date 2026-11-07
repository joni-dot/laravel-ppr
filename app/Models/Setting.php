<?php

namespace App\Models;

use App\Enums\SettingKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Settings that admins can change in the app, stored as simple key/value pairs.
 *
 * @property string $key
 * @property string|null $value
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public static function get(SettingKey $key): ?string
    {
        return static::query()->whereKey($key->value)->value('value');
    }

    /**
     * A whole-number setting, falling back to its default until an admin saves one.
     */
    public static function integer(SettingKey $key): int
    {
        return (int) (static::get($key) ?? $key->defaultValue());
    }

    public static function put(SettingKey $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key->value], ['value' => $value]);
    }
}
