<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('student')->index();
            $t->string('program')->nullable();
            $t->unsignedSmallInteger('year')->nullable();
            $t->text('interests')->nullable();
            $t->text('skills')->nullable();
            $t->boolean('is_demo')->default(false);
        });
        Schema::create('mentor_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->string('position');
            $t->text('interests');
            $t->text('support');
            $t->boolean('beginners')->default(true);
            $t->boolean('available')->default(true);
            $t->boolean('is_demo')->default(false);
            $t->timestamps();
        });
        Schema::create('research_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mentor_id')->constrained('users');
            $t->string('title');
            $t->text('summary');
            $t->string('direction');
            $t->string('level')->default('Без опыта');
            $t->string('work_type');
            $t->string('participation')->default('Индивидуально');
            $t->unsignedSmallInteger('hours')->default(3);
            $t->unsignedSmallInteger('capacity')->default(3);
            $t->boolean('is_open')->default(true);
            $t->boolean('is_demo')->default(false);
            $t->text('question');
            $t->text('actions');
            $t->text('skills');
            $t->string('duration');
            $t->text('outcome');
            $t->text('first_assignment');
            $t->timestamps();
        });
        Schema::create('applications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('research_task_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('mentor_id')->nullable()->constrained('users');
            $t->string('title');
            $t->text('message');
            $t->string('direction');
            $t->text('support')->nullable();
            $t->string('status')->default('new')->index();
            $t->boolean('beginner')->default(true);
            $t->timestamp('first_response_at')->nullable();
            $t->timestamps();
        });
        DB::statement("CREATE UNIQUE INDEX one_active_application ON applications (user_id, research_task_id) WHERE research_task_id IS NOT NULL AND status IN ('new','reviewing','clarification','accepted')");
        Schema::create('application_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->constrained('users');
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->text('note');
            $t->timestamps();
        });
        Schema::create('researches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->unique()->constrained();
            $t->foreignId('research_task_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('mentor_id')->constrained('users');
            $t->string('title');
            $t->text('goal');
            $t->text('outcome');
            $t->string('status')->default('active');
            $t->string('result_type')->default('Аналитический отчёт');
            $t->string('manuscript_status')->nullable();
            $t->text('assessment')->nullable();
            $t->boolean('publication_consent')->default(false);
            $t->boolean('is_public')->default(false);
            $t->timestamps();
        });
        Schema::create('research_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('research_id')->constrained('researches')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->unique(['research_id', 'user_id']);
        });
        Schema::create('stages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('research_id')->constrained('researches')->cascadeOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->date('due_at')->nullable();
            $t->string('status')->default('planned');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        foreach (['comments', 'materials'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->foreignId('research_id')->constrained('researches')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained();
                if ($table === 'comments') {
                    $t->text('body');
                } else {
                    $t->string('title');
                    $t->text('url');
                }$t->timestamps();
            });
        }
        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('description');
            $t->string('type');
            $t->timestampTz('starts_at');
            $t->string('format');
            $t->string('location')->nullable();
            $t->text('participant_url')->nullable();
            $t->string('organizer');
            $t->text('preparation');
            $t->boolean('beginners')->default(true);
            $t->unsignedSmallInteger('capacity')->default(30);
            $t->boolean('is_published')->default(true);
            $t->boolean('is_demo')->default(false);
            $t->timestamps();
        });
        Schema::create('event_registrations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->timestamps();
            $t->unique(['event_id', 'user_id']);
        });
        Schema::create('contents', function (Blueprint $t) {
            $t->id();
            $t->string('type')->index();
            $t->string('title');
            $t->string('category')->nullable();
            $t->text('excerpt')->nullable();
            $t->text('body');
            $t->boolean('is_published')->default(false);
            $t->boolean('is_demo')->default(false);
            $t->timestamps();
        });
        Schema::create('help_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('research_id')->nullable()->constrained('researches');
            $t->string('kind')->default('help');
            $t->text('body');
            $t->string('status')->default('new');
            $t->text('response')->nullable();
            $t->timestamp('responded_at')->nullable();
            $t->timestamps();
        });
        Schema::create('notices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('body');
            $t->string('url')->default('/dashboard');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('mentor_times', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('research_id')->constrained('researches');
            $t->date('worked_on');
            $t->unsignedInteger('minutes');
            $t->text('note');
            $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'mentor_times', 'notices', 'help_requests', 'contents', 'event_registrations', 'events', 'materials', 'comments', 'stages', 'research_user', 'researches', 'application_histories', 'applications', 'research_tasks', 'mentor_profiles'] as $name) {
            Schema::dropIfExists($name);
        }Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'program', 'year', 'interests', 'skills', 'is_demo']));
        Schema::dropIfExists('departments');
    }
};
