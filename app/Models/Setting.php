<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** All settings cached forever (cleared when saved) => zero queries per request */
    public static function allCached(): array
    {
        return Cache::rememberForever('settings.all', fn () => static::pluck('value', 'key')->toArray());
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('settings.all');
    }
}
