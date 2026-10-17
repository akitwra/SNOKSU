<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\HelpRequest;
use App\Models\Notice;
use App\Models\Research;
use App\Models\ResearchTask;
use App\Models\User;
use App\Services\ApplicationWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $r)
    {
        $u = $r->user();
        $aq = Application::with('user', 'mentor')->latest();
        $rq = Research::with('mentor', 'stages')->latest();
        if ($u->role === 'student') {
            $aq->where('user_id', $u->id);
            $rq->whereHas('participants', fn ($q) => $q->where('users.id', $u->id));
        } elseif ($u->role === 'mentor') {
            $aq->where('mentor_id', $u->id);
            $rq->where('mentor_id', $u->id);
        }if ($r->filled('status') && isset(Application::LABELS[$r->status])) {
            $aq->where('status', $r->status);
        }

        return view('dashboard.index', ['applications' => $aq->get(), 'researches' => $rq->get(), 'helpRequests' => $u->role === 'organizer' ? HelpRequest::with('user')->latest()->get() : HelpRequest::where('user_id', $u->id)->latest()->get(), 'notices' => $u->notices()->latest()->take(40)->get(), 'registrations' => $u->registrations()->with('event')->latest()->get()]);
    }

    public function mentorProfile(Request $r)
    {
        abort_unless($r->user()->role === 'mentor', 403);
        $d = $r->validate(['available' => 'required|boolean', 'beginners' => 'required|boolean', 'interests' => 'required|string|max:4000', 'support' => 'required|string|max:4000']);
        $r->user()->mentorProfile()->firstOrFail()->update($d);

        return back()->with('success', 'Профиль наставника и доступность обновлены.');
    }

    public function assign(Request $r, Application $application)
    {
        abort_unless($r->user()->role === 'organizer', 403);
        $d = $r->validate(['coordinator_id' => 'required|integer']);
        $coordinator = User::where('role', 'organizer')->find($d['coordinator_id']);
        if (! $coordinator) {
            return back()->withErrors(['coordinator_id' => 'Выберите организатора СНО.']);
        }DB::transaction(function () use ($application, $r, $coordinator) {
            $a = Application::lockForUpdate()->findOrFail($application->id);
            $a->update(['coordinator_id' => $coordinator->id]);
            $a->history()->create(['actor_id' => $r->user()->id, 'from_status' => $a->status, 'to_status' => $a->status, 'note' => 'Назначен ответственный координатор: '.$coordinator->name]);
            Notice::send($coordinator->id, 'Вам назначена заявка', $a->title, "/applications/$a->id");
        });

        return back()->with('success', 'Ответственный координатор назначен.');
    }

    public function profile(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:120', 'program' => 'nullable|string|max:190', 'year' => 'nullable|integer|min:1|max:6', 'interests' => 'nullable|string|max:2000', 'skills' => 'nullable|string|max:2000']);
        $r->user()->update($data);

        return back()->with('success', 'Профиль сохранён.');
    }

    public function idea()
    {
        abort_unless(auth()->user()->role === 'student', 403);

        return view('research.idea');
    }

    public function submitIdea(Request $r, ApplicationWorkflow $workflow)
    {
        $data = $r->validate(['title' => 'required|string|max:190', 'message' => 'required|string|min:30|max:8000', 'direction' => 'required|in:'.implode(',', config('society.directions')), 'support' => 'required|string|max:2000', 'beginner' => 'required|boolean']);
        $a = $workflow->submit($r->user(), $data);

        return redirect("/applications/$a->id")->with('success', 'Идея сохранена и поступила организатору СНО.');
    }

    public function apply(Request $r, ResearchTask $task, ApplicationWorkflow $workflow)
    {
        $data = $r->validate(['message' => 'required|string|min:15|max:4000', 'beginner' => 'required|boolean']);
        $a = $workflow->submit($r->user(), $data, $task);

        return redirect("/applications/$a->id")->with('success', 'Заявка сохранена и поступила организатору СНО.');
    }

    public function application(Application $application)
    {
        $u = auth()->user();
        abort_unless($u->role === 'organizer' || $application->user_id === $u->id || ($u->role === 'mentor' && $application->mentor_id === $u->id), 403);

        return view('dashboard.application', ['application' => $application->load('history.actor', 'user', 'mentor', 'research'), 'coordinators' => User::where('role', 'organizer')->get(), 'mentors' => User::where('role', 'mentor')->whereHas('mentorProfile', fn ($q) => $q->where('available', true))->get()]);
    }

    public function transition(Request $r, Application $application, ApplicationWorkflow $workflow)
    {
        $d = $r->validate(['status' => 'required|in:'.implode(',', array_keys(Application::LABELS)), 'note' => 'nullable|string|max:4000', 'mentor_id' => 'nullable|integer']);
        $workflow->transition($application, $r->user(), $d['status'], $d['note'] ?? '', $d['mentor_id'] ?? null);

        return back()->with('success', 'Решение сохранено. Участники получили уведомления.');
    }

    public function help(Request $r)
    {
        $d = $r->validate(['body' => 'required|string|min:10|max:4000', 'research_id' => 'nullable|exists:researches,id', 'kind' => 'required|in:help,pause']);
        if (! empty($d['research_id'])) {
            $research = Research::findOrFail($d['research_id']);
            abort_unless($research->canAccess($r->user()), 403);
            if ($d['kind'] === 'pause' && $research->status === 'active') {
                $research->update(['status' => 'pause_requested']);
            }
        }HelpRequest::create($d + ['user_id' => $r->user()->id]);
        Notice::organizers('Обращение за помощью', $d['body'], '/dashboard?tab=help');

        return back()->with('success', 'Обращение сохранено. Координатор увидит его в своём кабинете.');
    }

    public function answer(Request $r, HelpRequest $help)
    {
        abort_unless($r->user()->role === 'organizer', 403);
        $d = $r->validate(['response' => 'required|string|min:10|max:4000']);
        $help->update($d + ['status' => 'answered', 'responded_at' => now()]);
        Notice::send($help->user_id, 'Ответ координатора', $d['response'], '/dashboard?tab=help');

        return back()->with('success', 'Ответ сохранён и отправлен во внутренние уведомления.');
    }

    public function read(Request $r)
    {
        $r->user()->notices()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Уведомления отмечены прочитанными.');
    }
}
