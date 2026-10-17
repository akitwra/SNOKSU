<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $guarded = [];

    protected $attributes = ['status' => 'new'];

    const LABELS = ['new' => 'Новая', 'reviewing' => 'На рассмотрении', 'clarification' => 'Требуется уточнение', 'accepted' => 'Принята', 'rejected' => 'Не принята', 'withdrawn' => 'Отозвана'];

    protected $casts = ['beginner' => 'boolean', 'first_response_at' => 'datetime'];

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function task()
    {
        return $this->belongsTo(ResearchTask::class, 'research_task_id');
    }

    public function history()
    {
        return $this->hasMany(ApplicationHistory::class)->oldest();
    }

    public function research()
    {
        return $this->hasOne(Research::class);
    }

    public function getLabelAttribute()
    {
        return self::LABELS[$this->status];
    }
}
