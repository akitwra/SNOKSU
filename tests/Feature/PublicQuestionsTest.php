<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicQuestionsTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['name' => 'Посетитель сайта', 'email' => 'visitor@example.test', 'topic' => 'Конференции', 'message' => 'Как подготовить первый доклад на конференцию?', 'consent' => 1];
    }

    public function test_guest_question_is_stored_and_only_organizer_can_read_it(): void
    {
        $this->seed(DemoSeeder::class);
        $this->from('/questions')->post('/questions', $this->payload())->assertRedirect('/questions')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('questions', ['email' => 'visitor@example.test']);
        $this->assertDatabaseHas('notices', ['url' => '/manage/questions']);
        $this->assertGuest();
        $this->get('/manage/questions')->assertRedirect('/login');
        $this->actingAs(User::where('role', 'student')->first())->get('/manage/questions')->assertForbidden();
        $this->actingAs(User::where('role', 'mentor')->first())->get('/manage/questions')->assertForbidden();
        $this->actingAs(User::where('role', 'organizer')->first())->get('/manage/questions')->assertOk()->assertSee('visitor@example.test')->assertSee($this->payload()['message']);
    }

    public function test_question_requires_contact_consent_and_meaningful_message(): void
    {
        $this->postJson('/api/questions', ['name' => 'Имя', 'email' => 'invalid', 'topic' => 'unknown', 'message' => 'Hi'])->assertUnprocessable()->assertJsonValidationErrors(['email', 'topic', 'message', 'consent']);
        $this->postJson('/api/questions', $this->payload() + ['website' => 'spam.example'])->assertUnprocessable();
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_pages_api_supports_allowed_origin_and_limits_spam(): void
    {
        $this->withHeaders(['Origin' => 'https://akitwra.github.io'])->postJson('/api/questions', $this->payload())->assertCreated()->assertHeader('Access-Control-Allow-Origin', 'https://akitwra.github.io');
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/questions', $this->payload())->assertCreated();
        }
        $this->postJson('/api/questions', $this->payload())->assertStatus(429);
        $this->assertDatabaseCount('questions', 5);
    }

    public function test_conference_announcements_exclude_past_unpublished_and_other_event_types(): void
    {
        $this->seed(DemoSeeder::class);
        $conference = Event::where('type', 'Конференция')->where('starts_at', '>', now())->first();
        $past = Event::where('type', 'Конференция')->where('starts_at', '<', now())->first();
        $other = Event::where('type', '!=', 'Конференция')->first();
        $this->get('/conferences')->assertOk()->assertSee($conference->title)->assertSee($past->title)->assertDontSee($other->title);
        $this->get('/conference-announcements')->assertOk()->assertSee($conference->title)->assertDontSee($past->title)->assertDontSee($other->title);
        $conference->update(['is_published' => false]);
        $this->get('/conferences')->assertDontSee($conference->title);
        $this->get('/')->assertDontSee('Личный кабинет')->assertDontSee('База знаний')->assertDontSee('href="/dashboard"', false)->assertSee('Наши конференции')->assertSee('Анонс конференций');
        $this->get('/about')->assertSee('data-question-form', false)->assertDontSee('Войти и задать вопрос');
    }

    public function test_static_export_keeps_api_form_but_excludes_private_questions(): void
    {
        $this->seed(DemoSeeder::class);
        Question::create(array_intersect_key($this->payload(), array_flip(['name', 'email', 'topic', 'message'])));
        config(['society.questions_endpoint' => 'https://example.test/api/questions']);
        try {
            $this->artisan('society:export-pages', ['--output' => 'dist-questions-test'])->assertSuccessful();
            $page = File::get(base_path('dist-questions-test/questions/index.html'));
            $this->assertStringContainsString('data-preview-question="https://example.test/api/questions"', $page);
            $this->assertStringNotContainsString('visitor@example.test', $page);
            $this->assertStringNotContainsString('name="_token"', $page);
            $this->assertDirectoryDoesNotExist(base_path('dist-questions-test/manage'));
        } finally {
            File::deleteDirectory(base_path('dist-questions-test'));
        }
    }

    public function test_google_form_can_replace_question_form(): void
    {
        config(['society.questions_form_url' => 'https://docs.google.com/forms/d/e/example/viewform']);
        $this->get('/questions')->assertOk()->assertSee('https://docs.google.com/forms/d/e/example/viewform', false)->assertDontSee('data-question-form', false);
    }
}
