<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\Department;
use App\Models\Event;
use App\Models\ResearchTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function home()
    {
        return view('home', ['tasks' => ResearchTask::with('mentor.mentorProfile')->where('is_open', true)->where('level', 'Без опыта')->orderBy('id')->take(3)->get(), 'events' => Event::where('is_published', true)->where('starts_at', '>', now())->orderBy('starts_at')->take(3)->get(), 'news' => Content::where('type', 'news')->where('is_published', true)->latest()->take(3)->get()]);
    }

    public function about()
    {
        return view('about', ['faqs' => Content::where('type', 'faq')->where('is_published', true)->orderBy('id')->get(), 'news' => Content::where('type', 'news')->where('is_published', true)->latest()->get(), 'documents' => Content::where('type', 'document')->where('is_published', true)->get(), 'team' => User::where('role', 'organizer')->where('is_demo', false)->get()]);
    }

    public function contacts()
    {
        return view('contacts', ['primary' => config('contacts.primary'), 'example' => config('contacts.example')]);
    }

    public function research(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:150', 'direction' => 'nullable|string|max:100', 'level' => 'nullable|string|max:100', 'work_type' => 'nullable|string|max:100', 'participation' => 'nullable|string|max:100', 'hours' => 'nullable|integer|min:1|max:40', 'open' => 'nullable|in:1', 'match' => 'nullable|in:1']);
        $q = ResearchTask::with('mentor.mentorProfile')->withCount('researches');
        if ($search = $r->input('q')) {
            $q->where(fn ($q) => $q->where('title', 'ilike', '%'.$search.'%')->orWhere('summary', 'ilike', '%'.$search.'%'));
        }
        foreach (['direction', 'level', 'work_type', 'participation'] as $field) {
            if ($r->filled($field)) {
                $q->where($field, $r->input($field));
            }
        }
        if ($r->filled('hours')) {
            $q->where('hours', '<=', (int) $r->hours);
        }
        if ($r->boolean('open')) {
            $q->where('is_open', true)->whereHas('mentor.mentorProfile', fn ($q) => $q->where('available', true))->whereRaw('capacity > (select count(*) from research_user join researches on researches.id = research_user.research_id where researches.research_task_id = research_tasks.id)');
        }

        return view('research.index', ['tasks' => $q->latest()->paginate(9)->withQueryString()]);
    }

    public function task(ResearchTask $task)
    {
        return view('research.show', compact('task'));
    }

    public function match()
    {
        return view('research.match');
    }

    public function mentors(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:150', 'department' => 'nullable|integer', 'beginners' => 'nullable|in:1']);
        $q = User::where('role', 'mentor')->whereHas('mentorProfile')->with('mentorProfile.department');
        if ($r->filled('q')) {
            $q->where(fn ($q) => $q->where('name', 'ilike', '%'.$r->q.'%')->orWhereHas('mentorProfile', fn ($q) => $q->where('interests', 'ilike', '%'.$r->q.'%')));
        }if ($r->filled('department')) {
            $q->whereHas('mentorProfile', fn ($q) => $q->where('department_id', $r->department));
        }if ($r->boolean('beginners')) {
            $q->whereHas('mentorProfile', fn ($q) => $q->where('beginners', true));
        }

        return view('mentors.index', ['mentors' => $q->paginate(9)->withQueryString(), 'departments' => Department::all()]);
    }

    public function mentor(User $mentor)
    {
        abort_unless($mentor->role === 'mentor' && $mentor->mentorProfile, 404);

        return view('mentors.show', ['mentor' => $mentor, 'tasks' => ResearchTask::where('mentor_id', $mentor->id)->get()]);
    }

    public function events(Request $r)
    {
        $r->validate(['month' => 'nullable|date_format:Y-m', 'type' => 'nullable|string|max:100', 'view' => 'nullable|in:list,calendar']);
        $month = $r->filled('month') ? Carbon::createFromFormat('!Y-m', $r->month) : now()->startOfMonth();
        $q = Event::where('is_published', true);
        if ($r->filled('type')) {
            $q->where('type', $r->type);
        }if ($r->input('view') === 'calendar') {
            $q->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
        } else {
            $q->where('starts_at', '>', now());
        }

        return view('events.index', ['events' => $r->input('view') === 'calendar' ? $q->orderBy('starts_at')->get() : $q->orderBy('starts_at')->paginate(30)->withQueryString(), 'month' => $month]);
    }

    public function event(Event $event)
    {
        abort_unless($event->is_published, 404);

        return view('events.show', compact('event'));
    }

    public function conferences(Request $request)
    {
        return $this->conferenceList($request, false);
    }

    public function announcements(Request $request)
    {
        return $this->conferenceList($request, true);
    }

    private function conferenceList(Request $request, bool $announcements)
    {
        $request->validate(['q' => 'nullable|string|max:150']);
        $query = Event::where('is_published', true)->where('type', 'Конференция');
        if ($announcements) {
            $query->where('starts_at', '>', now());
        }
        if ($request->filled('q')) {
            $query->where(fn ($q) => $q->where('title', 'ilike', '%'.$request->q.'%')->orWhere('description', 'ilike', '%'.$request->q.'%'));
        }

        return view('conferences.index', ['conferences' => $query->orderBy('starts_at', $announcements ? 'asc' : 'desc')->paginate(12)->withQueryString(), 'announcements' => $announcements]);
    }

    public function knowledge()
    {
        return redirect('/conferences', 301);
    }

    public function content(Content $content)
    {
        abort_unless($content->is_published, 404);

        return view('knowledge.show', compact('content'));
    }

    public function template(string $kind)
    {
        $templates = ['plan' => ['План исследования', "1. Исследовательский вопрос\n2. Почему это интересно\n3. Что уже известно: проверяемые источники\n4. Метод и ограничения\n5. Данные и этика\n6. Этапы и сроки, согласованные с наставником\n7. Ожидаемый результат\n8. Критерии завершения"], 'presentation' => ['Структура выступления', "1. Вопрос и контекст\n2. Метод\n3. Данные\n4. Основной результат\n5. Ограничения\n6. Выводы\n7. Источники\n\nУточните регламент и оформление конкретного мероприятия."], 'report' => ['Отчёт об исследовании', "1. Цель\n2. Выполненная работа\n3. Результаты\n4. Ограничения\n5. Обратная связь наставника\n6. Следующий шаг\n7. Ссылки на материалы\n\nЭто универсальная заготовка, не официальный стандарт КГУ."]];
        abort_unless(isset($templates[$kind]), 404);

        return response('# '.$templates[$kind][0]."\n\n".$templates[$kind][1], 200, ['Content-Type' => 'text/markdown; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$kind.'.md"']);
    }
}
