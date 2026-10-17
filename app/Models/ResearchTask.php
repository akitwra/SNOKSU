<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ResearchTask extends Model
{
    protected $guarded = [];

    protected $casts = ['is_open' => 'boolean', 'is_demo' => 'boolean'];

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function researches()
    {
        return $this->hasMany(Research::class);
    }

    public function getFreeSeatsAttribute()
    {
        return max(0, $this->capacity - DB::table('research_user')->join('researches', 'researches.id', '=', 'research_user.research_id')->where('researches.research_task_id', $this->id)->count());
    }

    public function getAcceptingAttribute()
    {
        return $this->is_open && $this->free_seats > 0 && $this->mentor?->mentorProfile?->available;
    }
}
