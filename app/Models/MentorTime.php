<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MentorTime extends Model
{
    protected $guarded = [];

    protected $casts = ['worked_on' => 'date'];
}
