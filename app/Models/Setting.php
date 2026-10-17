<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public static function valueOf(string $key, ?string $default = null): ?string
    {
        return self::find($key)?->value ?? $default;
    }
}
