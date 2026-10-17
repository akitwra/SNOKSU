<?php

namespace App\Http\Controllers;

use App\Models\MentorTime;
use App\Models\Notice;
use App\Models\Research;
use App\Models\ResearchTask;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResearchController extends Controller
{
    private function access(Research $research, bool $staff = false)
    {
        $u = auth()->user();
        $manager = $u->role === 'organizer' || ($u->role === 'mentor' && $research->mentor_id === $u->id);
        abort_unless($research->canAccess($u) && (! $staff || $manager), 403);
    }

    public function show(Research $research)
    {
        $this->access($research);

        return view('dashboard.research', ['research' => $research->load('mentor', 'participants', 'stages', 'comments.user', 'materials.user')]);
    }

    public function comment(Request $r, Research $research)
    {
        $this->access($research);
        $d = $r->validate(['body' => 'required|string|min:2|max:4000']);
        $research->comments()->create($d + ['user_id' => $r->user()->id]);
        $ids = $research->participants->pluck('id')->push($research->mentor_id)->unique();
        foreach ($ids as $id) {
            if ($id !== $r->user()->id) {
                Notice::send($id, 'Комментарий в исследовании', $research->title, "/workspace/$research->id");
            }
        }

        return back()->with('success', 'Комментарий опубликован в закрытом пространстве исследования.');
    }

    public function material(Request $r, Research $research)
    {
        $this->access($research);
        $d = $r->validate(['title' => 'required|string|max:190', 'url' => 'required|url:http,https|max:2000']);
        $research->materials()->create($d + ['user_id' => $r->user()->id]);

        return back()->with('success', 'Ссылка на материал сохранена.');
    }

    public function stage(Request $r, Research $research)
    {
        $this->access($research, true);
        $d = $r->validate(['title' => 'required|string|max:190', 'description' => 'nullable|string|max:4000', 'due_at' => 'nullable|date']);
        $research->stages()->create($d);

        return back()->with('success', 'Этап добавлен.');
    }

    public function updateStage(Request $r, Stage $stage)
    {
        $research = $stage->research;
        $this->access($research);
        $d = $r->validate(['status' => 'required|in:planned,in_progress,submitted,completed', 'due_at' => 'nullable|date']);
        if ($d['status'] === 'completed') {
            abort_unless($r->user()->role === 'mentor' && $research->mentor_id === $r->user()->id, 403);
        }
        $manager = $r->user()->role === 'organizer' || ($r->user()->role === 'mentor' && $research->mentor_id === $r->user()->id);
        if (! $manager) {
            abort_unless($d['status'] === 'submitted' && $stage->status !== 'completed', 403);
            unset($d['due_at']);
        } $stage->update($d + ['completed_at' => $d['status'] === 'completed' ? now() : null]);
        foreach ($research->participants->pluck('id')->push($research->mentor_id)->unique() as $id) {
            Notice::send($id, 'Обновлён этап исследования', $stage->title, "/workspace/$research->id");
        }

        return back()->with('success', 'Состояние этапа сохранено.');
    }

    public function update(Request $r, Research $research)
    {
        $this->access($research, true);
        $d = $r->validate(['status' => 'required|in:active,paused,completed', 'result_type' => 'required|in:'.implode(',', config('society.result_types')), 'manuscript_status' => 'nullable|in:prepared,submitted,accepted,published', 'assessment' => 'nullable|string|max:4000']);
        if ($r->user()->role !== 'mentor') {
            unset($d['assessment']);
        }if ($d['status'] === 'completed' && $research->stages()->where('status', '!=', 'completed')->exists()) {
            return back()->withErrors(['status' => 'Сначала завершите все этапы исследования.']);
        }if ($d['result_type'] !== 'Рукопись') {
            $d['manuscript_status'] = null;
        }$research->update($d);

        return back()->with('success', 'Исследование обновлено.');
    }

    public function participant(Request $r, Research $research)
    {
        $this->access($research, true);
        $d = $r->validate(['email' => 'required|email']);
        $student = User::where('email', $d['email'])->where('role', 'student')->first();
        if (! $student) {
            return back()->withErrors(['email' => 'Студент с такой почтой не найден.']);
        }DB::transaction(function () use ($research, $student) {
            if ($research->participants()->where('users.id', $student->id)->exists()) {
                return;
            }if ($research->research_task_id) {
                $task = ResearchTask::lockForUpdate()->findOrFail($research->research_task_id);
                if ($task->free_seats < 1) {
                    throw ValidationException::withMessages(['email' => 'Свободных мест в задаче больше нет.']);
                }if (Research::where('research_task_id', $task->id)->whereHas('participants', fn ($q) => $q->where('users.id', $student->id))->exists()) {
                    throw ValidationException::withMessages(['email' => 'Студент уже участвует в этой задаче.']);
                }
            }$research->participants()->syncWithoutDetaching([$student->id]);
            $research->update(['publication_consent' => false, 'is_public' => false]);
        });
        Notice::send($student->id, 'Вы добавлены в исследование', $research->title, "/workspace/$research->id");

        return back()->with('success', 'Участник добавлен.');
    }

    public function time(Request $r, Research $research)
    {
        $this->access($research);
        abort_unless($r->user()->role === 'mentor' && $research->mentor_id === $r->user()->id, 403);
        $d = $r->validate(['worked_on' => 'required|date|before_or_equal:today', 'minutes' => 'required|integer|min:1|max:1440', 'note' => 'required|string|max:1000']);
        MentorTime::create($d + ['research_id' => $research->id, 'user_id' => $r->user()->id]);

        return back()->with('success', 'Затраченное время сохранено.');
    }
}
