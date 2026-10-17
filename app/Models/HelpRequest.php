<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpRequest extends Model
{
    protected $guarded = [];

    protected $casts = ['responded_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function research()
    {
        return $this->belongsTo(Research::class);
    }
}
