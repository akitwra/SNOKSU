<?php

namespace Tests\Feature;

use App\Models\Content;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PagesExportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('dist-export-test'));
        parent::tearDown();
    }

    public function test_export_is_public_only_and_works_under_repository_base_path(): void
    {
        $this->seed(DemoSeeder::class);
        $this->artisan('society:export-pages', ['--base-path' => '/SNOKSU', '--output' => 'dist-export-test'])->assertSuccessful();
        $path = base_path('dist-export-test');
        $home = File::get($path.'/index.html');
        $this->assertStringContainsString('/SNOKSU/css/app.css', $home);
        $this->assertStringContainsString('/SNOKSU/research/', $home);
        $this->assertStringContainsString('Демонстрационное превью', html_entity_decode($home));
        $this->assertStringNotContainsString('Личный кабинет', html_entity_decode($home));
        $this->assertStringContainsString('/SNOKSU/conferences/', $home);
        $this->assertStringContainsString('/SNOKSU/conference-announcements/', $home);
        $this->assertStringNotContainsString('csrf-token', $home);
        preg_match('/<script id="sno-preview-data" type="application\/json">(.*?)<\/script>/s', $home, $matches);
        $metadata = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertContains('Может ли нейросеть распознать эмоцию?', array_column($metadata['tasks'], 'title'));
        $this->assertDirectoryDoesNotExist($path.'/login');
        $this->assertDirectoryDoesNotExist($path.'/register');
        $this->assertDirectoryDoesNotExist($path.'/dashboard');
        $question = File::get($path.'/questions/index.html');
        $this->assertStringContainsString('data-question-form', $question);
        $this->assertStringContainsString('disabled', $question);
        $this->assertStringNotContainsString('name="_token"', $question);
        $this->assertStringNotContainsString('demo-local-only-2026', $question);
        $this->assertDirectoryDoesNotExist($path.'/manage');
        $this->assertStringContainsString('/SNOKSU/fonts/', File::get($path.'/css/app.css'));
        $this->assertFileDoesNotExist($path.'/.env');
        $this->assertFileDoesNotExist($path.'/index.php');
        $this->assertDirectoryDoesNotExist($path.'/workspace');
        $this->assertFileExists($path.'/templates/plan.md');
    }

    public function test_export_refuses_real_content(): void
    {
        $this->seed(DemoSeeder::class);
        Content::create(['type' => 'news', 'title' => 'Non-demo record', 'body' => 'Do not export this record.', 'is_demo' => false, 'is_published' => true]);
        $this->artisan('society:export-pages', ['--output' => 'dist-export-test'])->assertFailed();
        $this->assertFileDoesNotExist(base_path('dist-export-test/index.html'));
    }

    public function test_export_is_disabled_in_production(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('society:export-pages', ['--output' => 'dist-export-test'])->assertFailed();
    }
}
