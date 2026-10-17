<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Notice;
use App\Models\Research;
use App\Models\ResearchTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationWorkflow
{
    public function submit(User $user, array $data, ?ResearchTask $task = null): Application
    {
        abort_unless($user->role === 'student', 403, 'Заявки доступны студентам.');

        return DB::transaction(function () use ($user, $data, $task) {
            if ($task) {
                $task = ResearchTask::lockForUpdate()->findOrFail($task->id);
                if (! $task->accepting) {
                    throw ValidationException::withMessages(['message' => 'Набор закрыт. Выберите другую задачу.']);
                }if (Application::where('user_id', $user->id)->where('research_task_id', $task->id)->whereIn('status', ['new', 'reviewing', 'clarification', 'accepted'])->exists()) {
                    throw ValidationException::withMessages(['message' => 'У вас уже есть активная заявка на эту задачу.']);
                }
            }
            $a = Application::create(['user_id' => $user->id, 'research_task_id' => $task?->id, 'title' => $task?->title ?? $data['title'], 'message' => $data['message'], 'direction' => $task?->direction ?? $data['direction'], 'support' => $data['support'] ?? null, 'beginner' => $data['beginner'] ?? true]);
            $a->history()->create(['actor_id' => $user->id, 'to_status' => 'new', 'note' => 'Заявка поступила в общую очередь СНО.']);
            Notice::organizers('Новая заявка', $a->title, "/applications/$a->id");
            Notice::send($user->id, 'Заявка отправлена', 'Её рассмотрит организатор СНО.', "/applications/$a->id");

            return $a;
        }, 3);
    }

    public function transition(Application $application, User $actor, string $next, string $note, ?int $mentorId = null): Application
    {
        return DB::transaction(function () use ($application, $actor, $next, $note, $mentorId) {
            $a = Application::lockForUpdate()->findOrFail($application->id);
            $old = $a->status;
            $owner = $actor->id === $a->user_id;
            $organizer = $actor->role === 'organizer';
            $mentor = $actor->role === 'mentor' && $actor->id === $a->mentor_id;
            abort_unless($owner || $organizer || $mentor, 403);
            if ($next === 'accepted' && $old === 'accepted' && $mentor) {
                return $a;
            }
            $allowed = ($owner && $next === 'withdrawn' && in_array($old, ['new', 'reviewing', 'clarification'])) || ($owner && $old === 'clarification' && $next === 'new') || ($organizer && in_array($old, ['new', 'reviewing', 'clarification']) && in_array($next, ['reviewing', 'clarification', 'rejected'])) || ($mentor && $old === 'reviewing' && in_array($next, ['accepted', 'clarification', 'rejected']));
            if (! $allowed) {
                throw ValidationException::withMessages(['status' => 'Такой переход статуса недоступен.']);
            }
            if (in_array($next, ['clarification', 'rejected']) && mb_strlen(trim($note)) < 10) {
                throw ValidationException::withMessages(['note' => 'Поясните решение: не менее 10 символов. При отказе предложите альтернативу.']);
            }
            if ($next === 'reviewing' && $organizer) {
                $m = User::where('role', 'mentor')->whereHas('mentorProfile', fn ($q) => $q->where('available', true))->find($mentorId);
                if (! $m) {
                    throw ValidationException::withMessages(['mentor_id' => 'Выберите доступного наставника.']);
                }$a->mentor_id = $m->id;
                $a->coordinator_id ??= $actor->id;
            }
            if ($next === 'new') {
                if (mb_strlen(trim($note)) < 10) {
                    throw ValidationException::withMessages(['note' => 'Добавьте содержательное уточнение: не менее 10 символов.']);
                }$a->mentor_id = null;
            }
            if ($next === 'accepted') {
                $task = $a->research_task_id ? ResearchTask::lockForUpdate()->find($a->research_task_id) : null;
                if ($task && (! $task->is_open || $task->free_seats < 1)) {
                    throw ValidationException::withMessages(['status' => 'В задаче не осталось мест или набор закрыт.']);
                }
                if ($task && Research::where('research_task_id', $task->id)->whereHas('participants', fn ($q) => $q->where('users.id', $a->user_id))->exists()) {
                    throw ValidationException::withMessages(['status' => 'Студент уже участвует в этой задаче.']);
                }
                $r = Research::firstOrCreate(['application_id' => $a->id], ['research_task_id' => $task?->id, 'mentor_id' => $a->mentor_id, 'title' => $a->title, 'goal' => $task?->question ?? $a->message, 'outcome' => $task?->outcome ?? 'Согласовать результат с наставником']);
                $r->participants()->syncWithoutDetaching([$a->user_id]);
                if (! $r->stages()->exists()) {
                    $r->stages()->create(['title' => 'Первый шаг: уточнить исследовательский вопрос', 'description' => $task?->first_assignment ?? 'Обсудите идею с наставником и определите первый шаг.']);
                }
            }
            if (! $a->first_response_at && ($organizer || $mentor) && trim($note) !== '') {
                $a->first_response_at = now();
            }
            $a->status = $next;
            $a->save();
            $a->history()->create(['actor_id' => $actor->id, 'from_status' => $old, 'to_status' => $next, 'note' => $note ?: 'Статус обновлён.']);
            Notice::send($a->user_id, 'Заявка: '.$a->label, $note ?: 'Откройте заявку, чтобы узнать подробности.', "/applications/$a->id");
            if ($next === 'reviewing') {
                Notice::send($a->mentor_id, 'Вам направлена заявка', $a->title, "/applications/$a->id");
            }
            Notice::organizers('Заявка: '.$a->label, $a->title, "/applications/$a->id");

            return $a;
        }, 3);
    }
}
