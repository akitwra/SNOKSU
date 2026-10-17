<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $guarded = [];

    protected $casts = ['starts_at' => 'datetime', 'beginners' => 'boolean', 'is_demo' => 'boolean', 'is_published' => 'boolean'];

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function getFreeSeatsAttribute()
    {
        return max(0, $this->capacity - $this->registrations()->count());
    }

    public function isRegistered(?User $u): bool
    {
        return $u && $this->registrations()->where('user_id', $u->id)->exists();
    }
}
