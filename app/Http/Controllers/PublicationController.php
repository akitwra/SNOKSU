<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Models\Research;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicationController extends Controller
{
    public function consent(Request $r, Research $research)
    {
        abort_unless($research->participants()->where('users.id', $r->user()->id)->exists(), 403);
        $r->validate(['consent' => 'required|boolean']);
        DB::transaction(function () use ($r, $research) {
            $research = Research::lockForUpdate()->findOrFail($research->id);
            $research->participants()->updateExistingPivot($r->user()->id, ['publication_consent' => $r->boolean('consent')]);
            $all = $research->participants()->count() > 0 && ! $research->participants()->wherePivot('publication_consent', false)->exists();
            $research->update(['publication_consent' => $all, 'is_public' => $research->is_public && $all]);
        });

        return back()->with('success', $r->boolean('consent') ? 'Согласие на отдельную публичную карточку сохранено.' : 'Согласие отозвано. Публичная карточка скрыта.');
    }

    public function publish(Request $r, Research $research)
    {
        abort_unless($r->user()->role === 'organizer', 403);
        $d = $r->validate(['public_summary' => 'required|string|min:30|max:10000', 'publish' => 'required|boolean']);
        DB::transaction(function () use ($r, $research, $d) {
            $research = Research::lockForUpdate()->findOrFail($research->id);
            if ($r->boolean('publish') && ($research->status !== 'completed' || ! $research->publication_consent)) {
                throw ValidationException::withMessages(['publish' => 'Публикация доступна после завершения исследования и согласия всех участников.']);
            }$research->update(['public_summary' => $d['public_summary'], 'is_public' => $r->boolean('publish'), 'published_at' => $r->boolean('publish') ? now() : null]);
            foreach ($research->participants->pluck('id')->push($research->mentor_id)->unique() as $id) {
                Notice::send($id, $r->boolean('publish') ? 'Результат опубликован' : 'Публичная карточка скрыта', $research->title, "/workspace/$research->id");
            }
        });

        return back()->with('success', 'Состояние отдельной публичной карточки сохранено. Закрытые материалы не публикуются.');
    }

    public function show(Research $research)
    {
        abort_unless($research->is_public && $research->publication_consent, 404);

        return view('research.result', compact('research'));
    }
}
