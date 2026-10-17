<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    protected $guarded = [];

    protected $casts = ['due_at' => 'date', 'completed_at' => 'datetime'];

    public function research()
    {
        return $this->belongsTo(Research::class);
    }
}
