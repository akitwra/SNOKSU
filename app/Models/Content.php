<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    protected $guarded = [];

    protected $casts = ['is_published' => 'boolean', 'is_demo' => 'boolean'];
}
