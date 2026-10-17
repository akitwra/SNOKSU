<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Content;
use App\Models\Event;
use App\Models\HelpRequest;
use App\Models\Notice;
use App\Models\Research;
use App\Models\ResearchTask;
use App\Models\User;
use App\Services\ApplicationWorkflow;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SocietyTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $mentor;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->student = User::where('email', 'student@example.test')->firstOrFail();
        $this->mentor = User::where('email', 'mentor@example.test')->firstOrFail();
        $this->organizer = User::where('email', 'organizer@example.test')->firstOrFail();
    }

    private function task(): ResearchTask
    {
        return ResearchTask::where('mentor_id', $this->mentor->id)->orderBy('id')->firstOrFail();
    }

    private function application(?User $student = null, ?ResearchTask $task = null): Application
    {
        return app(ApplicationWorkflow::class)->submit($student ?? $this->student, ['message' => 'Мне интересно проверить исследовательский вопрос.', 'beginner' => true], $task ?? $this->task());
    }

    private function accepted(?Application $a = null): Research
    {
        $w = app(ApplicationWorkflow::class);
        $a ??= $this->application();
        $w->transition($a, $this->organizer, 'reviewing', 'Передаю наставнику: обсудите первый шаг.', $this->mentor->id);
        $w->transition($a, $this->mentor, 'accepted', 'Добро пожаловать, начнём с исследовательского вопроса.');

        return $a->fresh()->research;
    }

    public function test_public_sections_work_without_registration(): void
    {
        foreach (['/', '/about', '/research', '/research/match', '/research/'.$this->task()->id, '/mentors', '/mentors/'.$this->mentor->id, '/events', '/events?view=calendar', '/events/'.Event::first()->id, '/conferences', '/conference-announcements', '/questions', '/articles/'.Content::where('type', 'knowledge')->first()->id, '/login', '/register'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/templates/plan')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="plan.md"');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/idea')->assertRedirect('/login');
    }

    public function test_complete_application_first_stage_and_comment_flow(): void
    {
        $t = $this->task();
        $this->actingAs($this->student)->post("/research/$t->id/apply", ['message' => 'Хочу попробовать исследовать данные и учиться.', 'beginner' => 1])->assertRedirect();
        $a = Application::firstOrFail();
        $this->get("/applications/$a->id")->assertOk();
        $this->actingAs($this->organizer)->get("/applications/$a->id")->assertOk();
        $this->post("/applications/$a->id/transition", ['status' => 'reviewing', 'mentor_id' => $this->mentor->id, 'note' => 'Передаю наставнику для согласования первого этапа.'])->assertSessionHasNoErrors();
        $this->actingAs($this->mentor)->get("/applications/$a->id")->assertOk();
        $this->post("/applications/$a->id/transition", ['status' => 'accepted', 'note' => 'Участие согласовано, начнём с вопроса.'])->assertSessionHasNoErrors();
        $research = $a->fresh()->research;
        $this->assertNotNull($research);
        $this->assertSame(1, $research->participants()->count());
        $this->assertSame(1, $research->stages()->count());
        $this->assertSame(3, $a->history()->count());
        $this->assertNotNull($a->fresh()->first_response_at);
        $this->post("/applications/$a->id/transition", ['status' => 'accepted', 'note' => 'Повтор того же решения.'])->assertSessionHasNoErrors();
        $this->assertSame(1, Research::count());
        $stage = $research->stages()->first();
        $this->actingAs($this->student)->patch("/stages/$stage->id", ['status' => 'submitted'])->assertSessionHasNoErrors();
        $this->post("/workspace/$research->id/comments", ['body' => '<script>alert("test")</script> Мой первый комментарий.'])->assertSessionHasNoErrors();
        $this->get("/workspace/$research->id")->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false);
        $this->actingAs($this->mentor)->patch("/stages/$stage->id", ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertNotNull($stage->fresh()->completed_at);
        $this->patch("/workspace/$research->id", ['status' => 'completed', 'result_type' => 'Прототип', 'assessment' => 'Метод выбран обоснованно, ограничения описаны.'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $research->fresh()->status);
        $this->assertTrue(Notice::where('user_id', $this->student->id)->exists());
    }

    public function test_duplicate_active_application_is_rejected(): void
    {
        $a = $this->application();
        $t = $a->task;
        $this->actingAs($this->student)->post("/research/$t->id/apply", ['message' => 'Повторно отправляю ту же заявку.', 'beginner' => 1])->assertSessionHasErrors('message');
        $this->assertSame(1, Application::count());
    }

    public function test_database_also_prevents_active_duplicates(): void
    {
        $a = $this->application();
        $this->expectException(UniqueConstraintViolationException::class);
        Application::create($a->only(['user_id', 'research_task_id', 'title', 'message', 'direction', 'status']));
    }

    public function test_capacity_and_closed_recruitment_are_enforced(): void
    {
        $t = $this->task();
        $t->update(['capacity' => 1]);
        $a = $this->application(null, $t);
        $other = User::factory()->create();
        $b = $this->application($other, $t);
        $this->accepted($a);
        $this->assertSame(1, $t->fresh()->capacity);
        $this->assertSame(0, $t->fresh()->free_seats);
        app(ApplicationWorkflow::class)->transition($b, $this->organizer, 'reviewing', 'Согласуйте участие и нагрузку.', $this->mentor->id);
        $this->actingAs($this->mentor)->post("/applications/$b->id/transition", ['status' => 'accepted', 'note' => 'Согласовываю участие.'])->assertStatus(302)->assertSessionHasErrors('status');
        $this->assertSame(1, Research::count());
        $this->actingAs($other)->post("/research/$t->id/apply", ['message' => 'Хочу попробовать исследование.', 'beginner' => 1])->assertSessionHasErrors('message');
        $t->update(['capacity' => 5, 'is_open' => false]);
        $this->post("/research/$t->id/apply", ['message' => 'Хочу попробовать исследование.', 'beginner' => 1])->assertSessionHasErrors('message');
    }

    public function test_unrelated_students_and_mentors_cannot_read_or_modify_private_work(): void
    {
        $a = $this->application();
        $research = $this->accepted($a);
        $stage = $research->stages()->first();
        $stranger = User::factory()->create();
        $unrelated = User::where('email', 'social@example.test')->firstOrFail();
        foreach ([$stranger, $unrelated] as $user) {
            $this->actingAs($user)->get("/applications/$a->id")->assertForbidden();
            $this->get("/workspace/$research->id")->assertForbidden();
            $this->post("/workspace/$research->id/comments", ['body' => 'Чужой комментарий'])->assertForbidden();
            $this->post("/workspace/$research->id/materials", ['title' => 'Чужая ссылка', 'url' => 'https://example.org'])->assertForbidden();
            $this->patch("/stages/$stage->id", ['status' => 'submitted'])->assertForbidden();
            $this->post('/help', ['body' => 'Посмотрите чужое исследование', 'kind' => 'help', 'research_id' => $research->id])->assertForbidden();
        }
    }

    public function test_roles_cannot_skip_coordinator_or_change_their_own_role(): void
    {
        $a = $this->application();
        $this->actingAs($this->mentor)->post("/applications/$a->id/transition", ['status' => 'accepted', 'note' => 'Без направления координатора'])->assertForbidden();
        $this->actingAs($this->organizer)->post("/applications/$a->id/transition", ['status' => 'accepted', 'note' => 'Организатор не оценивает научную часть'])->assertSessionHasErrors('status');
        $this->actingAs($this->student)->patch('/profile', ['name' => 'Студент', 'role' => 'organizer'])->assertSessionHasNoErrors();
        $this->assertSame('student', $this->student->fresh()->role);
        $this->get('/manage/tasks')->assertForbidden();
        $this->get('/manage/statistics')->assertForbidden();
    }

    public function test_clarification_withdrawal_and_new_application_after_withdrawal(): void
    {
        $a = $this->application();
        $w = app(ApplicationWorkflow::class);
        $w->transition($a, $this->organizer, 'clarification', 'Расскажи, какие данные тебе интересны.');
        $this->actingAs($this->student)->post("/applications/$a->id/transition", ['status' => 'new', 'note' => 'Мне интересны открытые городские данные.'])->assertSessionHasNoErrors();
        $this->assertSame('new', $a->fresh()->status);
        $this->post("/applications/$a->id/transition", ['status' => 'withdrawn', 'note' => 'Хочу выбрать другую задачу.'])->assertSessionHasNoErrors();
        $this->application();
        $this->assertSame(2, Application::count());
    }

    public function test_rejection_requires_reason_and_keeps_history(): void
    {
        $a = $this->application();
        $this->actingAs($this->organizer)->post("/applications/$a->id/transition", ['status' => 'rejected', 'note' => 'нет'])->assertSessionHasErrors('note');
        $this->assertSame('new', $a->fresh()->status);
        $this->post("/applications/$a->id/transition", ['status' => 'rejected', 'note' => 'Сейчас задача закрывается, попробуй другой проект из каталога.'])->assertSessionHasNoErrors();
        $this->assertSame(2, $a->history()->count());
    }

    public function test_own_idea_uses_shared_queue_and_creates_research(): void
    {
        $this->actingAs($this->student)->post('/idea', ['title' => 'Моя исследовательская идея', 'message' => 'Хочу узнать, как люди проверяют научные новости и выбирают источники.', 'direction' => 'Социальные науки', 'support' => 'Помочь подобрать метод и наставника.', 'beginner' => 1])->assertSessionHasNoErrors();
        $a = Application::firstOrFail();
        $this->assertNull($a->research_task_id);
        $research = $this->accepted($a);
        $this->assertSame('Моя исследовательская идея', $research->title);
        $this->assertSame(1, $research->stages()->count());
    }

    public function test_event_registration_duplicates_capacity_cancellation_and_private_link(): void
    {
        $event = Event::firstOrFail();
        $event->update(['capacity' => 1, 'participant_url' => 'https://example.org/private-room']);
        $this->get("/events/$event->id")->assertDontSee('private-room');
        $this->actingAs($this->student)->post("/events/$event->id/register")->assertSessionHasNoErrors();
        $this->get("/events/$event->id")->assertSee('private-room');
        $this->post("/events/$event->id/register")->assertSessionHasErrors('event');
        $this->assertSame(1, $event->registrations()->count());
        $other = User::factory()->create();
        $this->actingAs($other)->get("/events/$event->id")->assertDontSee('private-room');
        $this->post("/events/$event->id/register")->assertSessionHasErrors('event');
        $this->actingAs($this->student)->delete("/events/$event->id/register")->assertSessionHasNoErrors();
        $this->assertSame(0, $event->registrations()->count());
        $this->actingAs($other)->post("/events/$event->id/register")->assertSessionHasNoErrors();
    }

    public function test_students_cannot_approve_stages_or_complete_research(): void
    {
        $research = $this->accepted();
        $stage = $research->stages()->first();
        $this->actingAs($this->student)->patch("/stages/$stage->id", ['status' => 'completed'])->assertForbidden();
        $this->patch("/workspace/$research->id", ['status' => 'completed', 'result_type' => 'Доклад'])->assertForbidden();
        $this->actingAs($this->mentor)->patch("/workspace/$research->id", ['status' => 'completed', 'result_type' => 'Доклад'])->assertSessionHasErrors('status');
    }

    public function test_help_pause_response_and_history_are_persistent(): void
    {
        $research = $this->accepted();
        $this->actingAs($this->student)->post('/help', ['kind' => 'pause', 'research_id' => $research->id, 'body' => 'Нужна пауза, хочу согласовать возвращение через две недели.'])->assertSessionHasNoErrors();
        $this->assertSame('pause_requested', $research->fresh()->status);
        $this->assertSame(1, $research->stages()->count());
        $help = HelpRequest::firstOrFail();
        $this->actingAs($this->mentor)->post("/help/$help->id/answer", ['response' => 'Чужая организационная роль'])->assertForbidden();
        $this->actingAs($this->organizer)->post("/help/$help->id/answer", ['response' => 'Давай согласуем паузу и дату возвращения.'])->assertSessionHasNoErrors();
        $this->assertSame('answered', $help->fresh()->status);
        $this->assertTrue(Notice::where('user_id', $this->student->id)->where('title', 'Ответ координатора')->exists());
    }

    public function test_mentor_can_only_edit_own_tasks(): void
    {
        $own = $this->task();
        $other = ResearchTask::where('mentor_id', '!=', $this->mentor->id)->firstOrFail();
        $this->actingAs($this->mentor)->get("/manage/tasks/$own->id/edit")->assertOk();
        $this->get("/manage/tasks/$other->id/edit")->assertForbidden();
        $this->get('/manage/events')->assertForbidden();
        $this->get('/manage/mentors')->assertForbidden();
        $data = $own->only(['title', 'summary', 'mentor_id', 'direction', 'level', 'work_type', 'participation', 'hours', 'capacity', 'duration', 'question', 'actions', 'skills', 'outcome', 'first_assignment', 'is_demo', 'is_open']);
        $data['title'] = 'Обновлённая задача';
        $data['mentor_id'] = $other->mentor_id;
        $this->put("/manage/tasks/$own->id", $data)->assertSessionHasNoErrors();
        $this->assertSame($this->mentor->id, $own->fresh()->mentor_id);
    }

    public function test_admin_and_dashboard_pages_render_for_all_roles(): void
    {
        foreach ([$this->student, $this->mentor, $this->organizer] as $u) {
            $this->actingAs($u);
            foreach (['', '?tab=profile', '?tab=applications', '?tab=researches', '?tab=events', '?tab=notices', '?tab=help'] as $tab) {
                $this->get('/dashboard'.$tab)->assertOk();
            }
        }
        foreach (['tasks', 'mentors', 'events', 'contents', 'departments'] as $section) {
            $this->get('/manage/'.$section)->assertOk();
            $this->get('/manage/'.$section.'/create')->assertOk();
        }
        $this->get('/manage/statistics')->assertOk();
        $this->get('/manage/settings')->assertOk();
    }

    public function test_admin_content_settings_and_mentor_promotion_save(): void
    {
        $this->actingAs($this->organizer)->post('/manage/contents', ['type' => 'faq', 'title' => 'Новый вопрос?', 'body' => 'Подтверждённый организатором ответ.', 'is_published' => 1, 'is_demo' => 0])->assertSessionHasNoErrors();
        $this->get('/about')->assertSee('Новый вопрос?');
        $this->post('/manage/settings', ['contact_email' => 'coordinator@example.test', 'help_target_hours' => 48])->assertSessionHasNoErrors();
        $this->get('/about')->assertSee('coordinator@example.test')->assertSee('48 ч');
        $student = User::factory()->create();
        $this->post('/manage/mentors', ['user_id' => $student->id, 'position' => 'Пример наставника', 'interests' => 'Методы исследования', 'support' => 'Помощь с исследованием', 'available' => 1, 'beginners' => 1, 'is_demo' => 1])->assertSessionHasNoErrors();
        $this->assertSame('mentor', $student->fresh()->role);
    }

    public function test_search_filters_empty_state_and_conferences_work(): void
    {
        $this->get('/research?q=НесуществующийВопрос')->assertOk()->assertSee('Пока нет подходящих задач');
        $this->get('/research?direction='.urlencode('Цифровые технологии').'&hours=4&open=1')->assertOk()->assertSee('Может ли нейросеть')->assertDontSee('Что расскажет вода');
        $this->get('/conferences?q='.urlencode('первые исследования'))->assertOk()->assertSee('Студенческая конференция: первые исследования');
        $this->get('/knowledge')->assertRedirect('/conferences');
    }

    public function test_registration_and_password_authentication_use_real_sessions(): void
    {
        $this->post('/register', ['name' => 'Новый студент', 'email' => 'new@example.test', 'password' => 'a-good-password123', 'password_confirmation' => 'a-good-password123', 'consent' => 1, 'role' => 'organizer'])->assertRedirect('/dashboard');
        $u = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertSame('student', $u->role);
        $this->assertTrue(Hash::check('a-good-password123', $u->password));
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->post('/login', ['email' => $u->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $u->email, 'password' => 'a-good-password123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
    }

    public function test_material_links_and_mentor_time_are_validated(): void
    {
        $research = $this->accepted();
        $this->actingAs($this->student)->post("/workspace/$research->id/materials", ['title' => 'Опасная ссылка', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->post("/workspace/$research->id/materials", ['title' => 'Материал', 'url' => 'https://example.org/document'])->assertSessionHasNoErrors();
        $this->assertSame(1, $research->materials()->count());
        $this->post("/workspace/$research->id/time", ['worked_on' => now()->toDateString(), 'minutes' => 30, 'note' => 'Консультация'])->assertForbidden();
        $this->actingAs($this->mentor)->post("/workspace/$research->id/time", ['worked_on' => now()->toDateString(), 'minutes' => 30, 'note' => 'Разбор исследовательского вопроса'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('mentor_times', ['research_id' => $research->id, 'minutes' => 30]);
    }

    public function test_notifications_are_scoped_to_the_signed_in_user(): void
    {
        Notice::send($this->student->id, 'Личное', 'Только студенту');
        Notice::send($this->mentor->id, 'Другое', 'Только наставнику');
        $this->actingAs($this->student)->post('/notices/read')->assertSessionHasNoErrors();
        $this->assertNotNull(Notice::where('user_id', $this->student->id)->first()->read_at);
        $this->assertNull(Notice::where('user_id', $this->mentor->id)->first()->read_at);
    }

    public function test_draft_content_is_not_public(): void
    {
        $c = Content::create(['type' => 'news', 'title' => 'Черновик', 'body' => 'Закрытый текст', 'is_published' => false]);
        $this->get('/articles/'.$c->id)->assertNotFound();
    }

    public function test_coordinator_assignment_preserves_status_and_is_role_protected(): void
    {
        $a = $this->application();
        $this->actingAs($this->student)->post("/applications/$a->id/assign", ['coordinator_id' => $this->organizer->id])->assertForbidden();
        $this->actingAs($this->organizer)->post("/applications/$a->id/assign", ['coordinator_id' => $this->mentor->id])->assertSessionHasErrors('coordinator_id');
        $this->post("/applications/$a->id/assign", ['coordinator_id' => $this->organizer->id])->assertSessionHasNoErrors();
        $this->assertSame($this->organizer->id, $a->fresh()->coordinator_id);
        $this->assertSame('new', $a->fresh()->status);
        $this->assertNull($a->fresh()->first_response_at);
        $this->assertSame(2, $a->history()->count());
    }

    public function test_mentor_can_update_own_availability_but_student_cannot(): void
    {
        $data = ['available' => 0, 'beginners' => 1, 'interests' => 'Научные интересы наставника', 'support' => 'Помощь с вопросом и исследовательским методом'];
        $this->actingAs($this->student)->patch('/mentor-profile', $data)->assertForbidden();
        $this->actingAs($this->mentor)->patch('/mentor-profile', $data)->assertSessionHasNoErrors();
        $this->assertFalse($this->mentor->fresh()->mentorProfile->available);
        $this->assertFalse($this->task()->fresh()->accepting);
    }

    public function test_promoted_participant_does_not_gain_assigned_mentor_permissions(): void
    {
        $research = $this->accepted();
        $stage = $research->stages()->first();
        $this->student->forceFill(['role' => 'mentor'])->save();
        $this->actingAs($this->student)->get("/workspace/$research->id")->assertOk();
        $this->patch("/stages/$stage->id", ['status' => 'completed'])->assertForbidden();
        $this->post("/workspace/$research->id/stages", ['title' => 'Чужой научный этап'])->assertForbidden();
        $this->patch("/workspace/$research->id", ['status' => 'active', 'result_type' => 'Доклад', 'assessment' => 'Не мой научный доступ'])->assertForbidden();
    }
}
