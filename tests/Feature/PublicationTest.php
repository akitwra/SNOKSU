<?php

namespace Tests\Feature;

use App\Models\ResearchTask;
use App\Models\User;
use App\Services\ApplicationWorkflow;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_publication_is_separate_from_private_work_and_consent_is_revocable(): void
    {
        $this->seed(DemoSeeder::class);
        $student = User::where('email', 'student@example.test')->firstOrFail();
        $mentor = User::where('email', 'mentor@example.test')->firstOrFail();
        $organizer = User::where('email', 'organizer@example.test')->firstOrFail();
        $task = ResearchTask::where('mentor_id', $mentor->id)->firstOrFail();
        $w = app(ApplicationWorkflow::class);
        $a = $w->submit($student, ['message' => 'Хочу изучить данные, это закрытое сообщение.'], $task);
        $w->transition($a, $organizer, 'reviewing', 'Согласуйте первый шаг и сроки.', $mentor->id);
        $w->transition($a, $mentor, 'accepted', 'Принимаю, обсудим метод.');
        $r = $a->fresh()->research;
        $r->update(['status' => 'completed']);
        $r->comments()->create(['user_id' => $student->id, 'body' => 'Секретный комментарий']);
        $this->get("/results/$r->id")->assertNotFound();
        $summary = 'Это отдельный согласованный текст о результатах исследования.';
        $this->actingAs($organizer)->post("/workspace/$r->id/publish", ['publish' => 1, 'public_summary' => $summary])->assertSessionHasErrors('publish');
        $this->actingAs($student)->post("/workspace/$r->id/consent", ['consent' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($r->fresh()->publication_consent);
        $this->post("/workspace/$r->id/publish", ['publish' => 1, 'public_summary' => $summary])->assertForbidden();
        $this->actingAs($organizer)->post("/workspace/$r->id/publish", ['publish' => 1, 'public_summary' => $summary])->assertSessionHasNoErrors();
        $this->post('/logout');
        $this->get("/results/$r->id")->assertOk()->assertSee($summary)->assertDontSee('Секретный комментарий')->assertDontSee('закрытое сообщение');
        $this->actingAs($student)->post("/workspace/$r->id/consent", ['consent' => 0])->assertSessionHasNoErrors();
        $this->get("/results/$r->id")->assertNotFound();
    }

    public function test_adding_team_members_cannot_exceed_task_capacity(): void
    {
        $this->seed(DemoSeeder::class);
        $student = User::where('email', 'student@example.test')->firstOrFail();
        $mentor = User::where('email', 'mentor@example.test')->firstOrFail();
        $organizer = User::where('email', 'organizer@example.test')->firstOrFail();
        $task = ResearchTask::where('mentor_id', $mentor->id)->firstOrFail();
        $task->update(['capacity' => 1]);
        $w = app(ApplicationWorkflow::class);
        $a = $w->submit($student, ['message' => 'Хочу попробовать исследование.'], $task);
        $w->transition($a, $organizer, 'reviewing', 'Обсудите первый этап исследования.', $mentor->id);
        $w->transition($a, $mentor, 'accepted', 'Принимаю участие, начинаем с вопроса.');
        $r = $a->fresh()->research;
        $other = User::factory()->create();
        $this->actingAs($mentor)->post("/workspace/$r->id/participants", ['email' => $other->email])->assertSessionHasErrors('email');
        $this->assertSame(1, $r->participants()->count());
    }

    public function test_organizer_cannot_approve_scientific_stage(): void
    {
        $this->seed(DemoSeeder::class);
        $student = User::where('email', 'student@example.test')->firstOrFail();
        $mentor = User::where('email', 'mentor@example.test')->firstOrFail();
        $organizer = User::where('email', 'organizer@example.test')->firstOrFail();
        $task = ResearchTask::where('mentor_id', $mentor->id)->firstOrFail();
        $w = app(ApplicationWorkflow::class);
        $a = $w->submit($student, ['message' => 'Мне интересен исследовательский вопрос.'], $task);
        $w->transition($a, $organizer, 'reviewing', 'Обсудите работу и метод исследования.', $mentor->id);
        $w->transition($a, $mentor, 'accepted', 'Принимаю участие и научное сопровождение.');
        $stage = $a->fresh()->research->stages()->first();
        $this->actingAs($organizer)->patch("/stages/$stage->id", ['status' => 'completed'])->assertForbidden();
    }
}
