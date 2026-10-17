<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $attributes = ['role' => 'student'];

    protected $fillable = ['name', 'email', 'password', 'program', 'year', 'interests', 'skills'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime', 'is_demo' => 'boolean'];
    }

    public function mentorProfile()
    {
        return $this->hasOne(MentorProfile::class);
    }

    public function notices()
    {
        return $this->hasMany(Notice::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function researches()
    {
        return $this->belongsToMany(Research::class);
    }

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['mentor', 'organizer']);
    }

    public function roleLabel(): string
    {
        return ['student' => 'Студент', 'mentor' => 'Наставник', 'organizer' => 'Организатор'][$this->role];
    }
}
