<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    public function register(Request $r, Event $event)
    {
        DB::transaction(function () use ($r, $event) {
            $e = Event::lockForUpdate()->findOrFail($event->id);
            abort_unless($e->is_published, 404);
            if ($e->isRegistered($r->user())) {
                throw ValidationException::withMessages(['event' => 'Вы уже записаны на это мероприятие.']);
            }if ($e->starts_at->isPast() || $e->free_seats < 1) {
                throw ValidationException::withMessages(['event' => 'Запись завершена или свободных мест больше нет.']);
            }$e->registrations()->create(['user_id' => $r->user()->id]);
            Notice::send($r->user()->id, 'Вы записаны на мероприятие', $e->title, "/events/$e->id");
        }, 3);

        return back()->with('success', 'Запись подтверждена. Мероприятие добавлено в личный кабинет.');
    }

    public function cancel(Request $r, Event $event)
    {
        $event->registrations()->where('user_id', $r->user()->id)->delete();

        return back()->with('success', 'Участие отменено.');
    }
}
