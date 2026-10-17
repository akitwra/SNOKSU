<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Research extends Model
{
    protected $guarded = [];

    protected $table = 'researches';

    protected $casts = ['publication_consent' => 'boolean', 'is_public' => 'boolean'];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function participants()
    {
        return $this->belongsToMany(User::class)->withPivot('publication_consent');
    }

    public function stages()
    {
        return $this->hasMany(Stage::class)->orderBy('id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->oldest();
    }

    public function materials()
    {
        return $this->hasMany(Material::class);
    }

    public function canAccess(User $user): bool
    {
        return $user->role === 'organizer' || $this->mentor_id === $user->id || $this->participants()->where('users.id', $user->id)->exists();
    }
}
