<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MentorProfile extends Model
{
    protected $guarded = [];

    protected $casts = ['beginners' => 'boolean', 'available' => 'boolean', 'is_demo' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
