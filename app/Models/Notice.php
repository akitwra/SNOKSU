<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    protected $guarded = [];

    protected $casts = ['read_at' => 'datetime'];

    public static function send(int $id, string $title, string $body, string $url = '/dashboard'): void
    {
        self::create(['user_id' => $id, 'title' => $title, 'body' => $body, 'url' => $url]);
    }

    public static function organizers(string $title, string $body, string $url = '/dashboard'): void
    {
        foreach (User::where('role', 'organizer')->pluck('id') as $id) {
            self::send($id, $title, $body, $url);
        }
    }
}
