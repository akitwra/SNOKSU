<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Content;
use App\Models\Department;
use App\Models\Event;
use App\Models\MentorProfile;
use App\Models\MentorTime;
use App\Models\Research;
use App\Models\ResearchTask;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    private function authorizeSection(string $section)
    {
        abort_unless(isset($this->definitions()[$section]), 404);
        abort_unless(auth()->user()->role === 'organizer' || (auth()->user()->role === 'mentor' && $section === 'tasks'), 403);
    }

    public function definitions(): array
    {
        return [
            'tasks' => ['title' => 'Исследовательские задачи', 'class' => ResearchTask::class, 'fields' => [
                'title' => ['Название', 'text', 'required|string|max:190'], 'summary' => ['Краткое описание', 'textarea', 'required|string|max:1000'], 'mentor_id' => ['Наставник', 'mentor', 'required|exists:users,id'], 'direction' => ['Направление', 'directions', 'required|string|max:100'], 'level' => ['Подготовка', 'levels', 'required|string|max:100'], 'work_type' => ['Вид работы', 'work_types', 'required|string|max:100'], 'participation' => ['Участие', 'participation', 'required|string|max:100'], 'hours' => ['Часов в неделю', 'number', 'required|integer|min:1|max:40'], 'capacity' => ['Количество мест', 'number', 'required|integer|min:1|max:1000'], 'duration' => ['Сроки', 'text', 'required|string|max:190'], 'question' => ['Исследовательский вопрос', 'textarea', 'required|string|max:4000'], 'actions' => ['Действия студента', 'textarea', 'required|string|max:4000'], 'skills' => ['Необходимые навыки', 'textarea', 'required|string|max:2000'], 'outcome' => ['Ожидаемый результат', 'textarea', 'required|string|max:4000'], 'first_assignment' => ['Первое задание', 'textarea', 'required|string|max:4000'], 'is_open' => ['Набор открыт', 'checkbox', 'boolean'], 'is_demo' => ['Демонстрационная запись', 'checkbox', 'boolean']]],
            'events' => ['title' => 'Мероприятия', 'class' => Event::class, 'fields' => [
                'title' => ['Название', 'text', 'required|string|max:190'], 'description' => ['Описание', 'textarea', 'required|string|max:8000'], 'type' => ['Тип', 'event_types', 'required|string|max:100'], 'starts_at' => ['Дата и время (Москва, UTC+3)', 'datetime-local', 'required|date'], 'format' => ['Формат', 'text', 'required|string|max:100'], 'location' => ['Место проведения', 'text', 'nullable|string|max:190'], 'participant_url' => ['Закрытая ссылка для записавшихся', 'url', 'nullable|url:https|max:2000'], 'organizer' => ['Организатор', 'text', 'required|string|max:190'], 'preparation' => ['Подготовка', 'textarea', 'required|string|max:4000'], 'capacity' => ['Количество мест', 'number', 'required|integer|min:1|max:10000'], 'beginners' => ['Можно без опыта и темы', 'checkbox', 'boolean'], 'is_published' => ['Опубликовано', 'checkbox', 'boolean'], 'is_demo' => ['Демонстрационная запись', 'checkbox', 'boolean']]],
            'contents' => ['title' => 'База знаний, новости и документы', 'class' => Content::class, 'fields' => [
                'type' => ['Раздел', 'content_types', 'required|in:knowledge,news,faq,document'], 'title' => ['Заголовок / вопрос', 'text', 'required|string|max:190'], 'category' => ['Категория', 'categories', 'nullable|string|max:100'], 'excerpt' => ['Краткое описание', 'textarea', 'nullable|string|max:1000'], 'body' => ['Текст (без HTML)', 'textarea', 'required|string|max:30000'], 'is_published' => ['Опубликовано', 'checkbox', 'boolean'], 'is_demo' => ['Демонстрационная запись', 'checkbox', 'boolean']]],
            'mentors' => ['title' => 'Профили наставников', 'class' => MentorProfile::class, 'fields' => [
                'user_id' => ['Зарегистрированный пользователь', 'user', 'required|exists:users,id'], 'department_id' => ['Подразделение', 'department', 'nullable|exists:departments,id'], 'position' => ['Должность', 'text', 'required|string|max:190'], 'interests' => ['Научные интересы', 'textarea', 'required|string|max:4000'], 'support' => ['Направления помощи', 'textarea', 'required|string|max:4000'], 'beginners' => ['Готов работать с новичками', 'checkbox', 'boolean'], 'available' => ['Принимает заявки', 'checkbox', 'boolean'], 'is_demo' => ['Демонстрационный профиль', 'checkbox', 'boolean']]],
            'departments' => ['title' => 'Подразделения', 'class' => Department::class, 'fields' => ['name' => ['Название', 'text', 'required|string|max:190']]],
        ];
    }

    public function index(string $section)
    {
        $this->authorizeSection($section);
        $definition = $this->definitions()[$section];
        $q = $definition['class']::query();
        if (auth()->user()->role === 'mentor') {
            $q->where('mentor_id', auth()->id());
        }

        return view('admin.index', ['section' => $section, 'definition' => $definition, 'records' => $q->latest()->paginate(20)]);
    }

    public function form(string $section, ?int $id = null)
    {
        $this->authorizeSection($section);
        $definition = $this->definitions()[$section];
        $record = $id ? $definition['class']::findOrFail($id) : new $definition['class'];
        if ($id && auth()->user()->role === 'mentor') {
            abort_unless($record->mentor_id === auth()->id(), 403);
        }

        return view('admin.form', compact('section', 'definition', 'record') + ['mentors' => User::where('role', 'mentor')->get(), 'users' => User::whereIn('role', ['student', 'mentor'])->get(), 'departments' => Department::all()]);
    }

    public function save(Request $r, string $section, ?int $id = null)
    {
        $this->authorizeSection($section);
        $definition = $this->definitions()[$section];
        $record = $id ? $definition['class']::findOrFail($id) : new $definition['class'];
        if ($id && $r->user()->role === 'mentor') {
            abort_unless($record->mentor_id === $r->user()->id, 403);
        }$rules = [];
        foreach ($definition['fields'] as $field => $info) {
            $rules[$field] = $info[2];
        }if ($section === 'tasks' && $r->user()->role === 'mentor') {
            $r->merge(['mentor_id' => $r->user()->id]);
        }$data = $r->validate($rules);
        foreach ($definition['fields'] as $field => $info) {
            if ($info[1] === 'checkbox') {
                $data[$field] = $r->boolean($field);
            }if (config('society.'.$info[1])) {
                $r->validate([$field => [($field === 'category' ? 'nullable' : 'required'), Rule::in(config('society.'.$info[1]))]]);
            }
        }
        if ($section === 'tasks') {
            abort_unless(User::where('role', 'mentor')->where('id', $data['mentor_id'])->exists(), 422);
            if ($id && $record->researches()->count() > $data['capacity']) {
                return back()->withInput()->withErrors(['capacity' => 'Количество мест меньше числа принятых участников.']);
            }
        }
        if ($section === 'events' && $id && $record->registrations()->count() > $data['capacity']) {
            return back()->withInput()->withErrors(['capacity' => 'Уже зарегистрировано больше участников.']);
        }
        if ($section === 'mentors') {
            $user = User::findOrFail($data['user_id']);
            abort_unless($user->role !== 'organizer', 422);
            if (($id && $record->user_id !== $user->id) || MentorProfile::where('user_id', $user->id)->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                return back()->withInput()->withErrors(['user_id' => 'Для пользователя уже существует профиль.']);
            }
            DB::transaction(function () use ($record, $data, $user) {
                $user->forceFill(['role' => 'mentor'])->save();
                $record->fill($data)->save();
            });
        } elseif (in_array($section, ['tasks', 'events'])) {
            DB::transaction(function () use ($record, $data, $section, $id, $definition) {
                $locked = $id ? $definition['class']::lockForUpdate()->findOrFail($id) : $record;
                if ($id) {
                    $used = $section === 'tasks' ? ($locked->capacity - $locked->free_seats) : $locked->registrations()->count();
                    if ($data['capacity'] < $used) {
                        throw ValidationException::withMessages(['capacity' => 'Количество мест меньше числа участников.']);
                    }
                }$locked->fill($data)->save();
            });
        } else {
            $record->fill($data)->save();
        }

        return redirect('/manage/'.$section)->with('success', 'Изменения сохранены.');
    }

    public function settings()
    {
        abort_unless(auth()->user()->role === 'organizer', 403);

        return view('admin.settings');
    }

    public function saveSettings(Request $r)
    {
        abort_unless($r->user()->role === 'organizer', 403);
        $d = $r->validate(['contact_email' => 'nullable|email|max:190', 'contact_text' => 'nullable|string|max:2000', 'documents_note' => 'nullable|string|max:2000', 'help_target_hours' => 'nullable|integer|min:1|max:720']);
        foreach ($d as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('success', 'Настройки сохранены.');
    }

    public function stats()
    {
        abort_unless(auth()->user()->role === 'organizer', 403);
        $responses = Application::whereNotNull('first_response_at')->get();
        $mean = $responses->count() ? $responses->avg(fn ($a) => $a->created_at->diffInMinutes($a->first_response_at) / 60) : null;
        $firstCompleted = Research::whereHas('stages', fn ($q) => $q->where('status', 'completed')->whereRaw('id = (select min(s.id) from stages s where s.research_id = stages.research_id)'))->count();

        return view('admin.stats', ['total' => Application::count(), 'beginners' => Application::where('beginner', true)->count(), 'accepted' => DB::table('research_user')->distinct()->count('user_id'), 'acceptedApplications' => Application::where('status', 'accepted')->count(), 'unanswered' => Application::whereNull('first_response_at')->whereIn('status', ['new', 'reviewing', 'clarification'])->count(), 'mean' => $mean, 'firstCompleted' => $firstCompleted, 'completed' => Research::where('status', 'completed')->count(), 'manuscripts' => Research::where('result_type', 'Рукопись')->selectRaw('manuscript_status, count(*) as total')->groupBy('manuscript_status')->pluck('total', 'manuscript_status'), 'mentorMinutes' => MentorTime::sum('minutes')]);
    }
}
