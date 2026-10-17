<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Models\Event;
use App\Models\ResearchTask;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ExportPages extends Command
{
    protected $signature = 'society:export-pages {--base-path=/SNOKSU} {--output=dist-pages}';

    protected $description = 'Export a guest-only static preview, without authentication, private data or server actions.';

    private string $prefix;

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Export is only allowed in local/testing with demo data.');

            return self::FAILURE;
        }
        $name = $this->option('output');
        if (! preg_match('/^dist-[a-zA-Z0-9_-]+$/', $name)) {
            $this->error('Use a generated directory named dist-*.');

            return self::FAILURE;
        }
        $out = base_path($name);
        if (is_dir($out) && ! is_file($out.'/.sno-pages-generated')) {
            $this->error('Refusing to overwrite a directory without an export marker.');

            return self::FAILURE;
        }
        if (is_dir($out)) {
            File::deleteDirectory($out);
        }
        File::makeDirectory($out, 0755, true);
        File::put($out.'/.sno-pages-generated', 'Static preview only.');
        $this->prefix = '/'.trim($this->option('base-path'), '/');
        if ($this->prefix === '/') {
            $this->prefix = '';
        }
        $tasks = ResearchTask::with('mentor.mentorProfile')->get();
        $mentors = User::where('role', 'mentor')->whereHas('mentorProfile')->with('mentorProfile')->get();
        $events = Event::where('is_published', true)->get();
        $articles = Content::where('is_published', true)->get();
        if ($tasks->contains(fn ($t) => ! $t->is_demo) || $mentors->contains(fn ($m) => (! $m->is_demo || ! $m->mentorProfile->is_demo)) || $events->contains(fn ($e) => ! $e->is_demo) || $articles->contains(fn ($a) => ! $a->is_demo)) {
            $this->error('Only a fresh demo dataset may be exported. Real content was found.');

            return self::FAILURE;
        }
        $data = ['tasks' => $tasks->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'summary' => $t->summary, 'direction' => $t->direction, 'level' => $t->level, 'work_type' => $t->work_type, 'participation' => $t->participation, 'hours' => $t->hours, 'open' => (bool) $t->accepting])->values(), 'mentors' => $mentors->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'interests' => $m->mentorProfile->interests, 'department' => (string) $m->mentorProfile->department_id, 'beginners' => $m->mentorProfile->beginners])->values(), 'events' => $events->map(fn ($e) => ['id' => $e->id, 'title' => $e->title, 'description' => $e->description, 'type' => $e->type, 'starts_at' => $e->starts_at->toIso8601String(), 'format' => $e->format])->values()];
        $routes = ['/', '/about', '/research', '/research/match', '/mentors', '/events', '/conferences', '/conference-announcements', '/questions', '/contacts'];
        foreach ($tasks as $t) {
            $routes[] = '/research/'.$t->id;
        }
        foreach ($mentors as $m) {
            $routes[] = '/mentors/'.$m->id;
        }
        foreach ($events as $e) {
            $routes[] = '/events/'.$e->id;
        }
        foreach ($articles->where('type', '!=', 'knowledge') as $a) {
            $routes[] = '/articles/'.$a->id;
        }
        $kernel = app(Kernel::class);
        foreach ($routes as $uri) {
            $request = Request::create('http://pages-export.test'.$uri);
            $response = $kernel->handle($request);
            if ($response->getStatusCode() !== 200) {
                $this->error("Export failed: $uri (HTTP {$response->getStatusCode()})");

                return self::FAILURE;
            }
            $html = $response->getContent();
            $this->savePage($out, $uri, $this->transform($html, $uri, $data));
            $kernel->terminate($request, $response);
        }
        foreach (['plan', 'presentation', 'report'] as $kind) {
            $response = $kernel->handle(Request::create('http://pages-export.test/templates/'.$kind));
            if ($response->getStatusCode() !== 200) {
                return self::FAILURE;
            }
            File::ensureDirectoryExists($out.'/templates');
            File::put($out.'/templates/'.$kind.'.md', $response->getContent());
        }
        foreach (['css', 'js', 'fonts', 'images'] as $asset) {
            File::copyDirectory(public_path($asset), $out.'/'.$asset);
        }
        foreach (File::allFiles($out.'/css') as $css) {
            $text = File::get($css->getPathname());
            $text = str_replace(["url('/", 'url("/'], ["url('".$this->prefix.'/', 'url("'.$this->prefix.'/'], $text);
            File::put($css->getPathname(), $text);
        }
        File::copy(public_path('favicon.svg'), $out.'/favicon.svg');
        File::put($out.'/.nojekyll', '');
        File::put($out.'/robots.txt', "User-agent: *\nDisallow: /\n");
        File::put($out.'/404.html', File::get($out.'/questions/index.html'));
        $this->info('Exported '.(count($routes)).' public preview pages. Private workspaces and credentials were not exported.');

        return self::SUCCESS;
    }

    private function savePage(string $out, string $uri, string $html): void
    {
        $dir = $out.($uri === '/' ? '' : $uri);
        File::ensureDirectoryExists($dir);
        File::put($dir.'/index.html', $html);
    }

    private function rewrite(string $url): string
    {
        if ($url === '' || str_starts_with($url, '#') || preg_match('/^(mailto:|tel:|data:)/', $url)) {
            return $url;
        }
        $p = parse_url($url);
        if (! $p) {
            return $url;
        }
        if (isset($p['host']) && $p['host'] !== 'pages-export.test') {
            return $url;
        }
        $path = $p['path'] ?? '/';
        if (! str_starts_with($path, '/')) {
            return $url;
        }
        if (preg_match('#^/templates/(plan|presentation|report)$#', $path, $m)) {
            return $this->prefix.'/templates/'.$m[1].'.md';
        }
        if (! pathinfo($path, PATHINFO_EXTENSION)) {
            $path = rtrim($path, '/').'/';
        }

        return $this->prefix.$path.(isset($p['query']) ? '?'.$p['query'] : '').(isset($p['fragment']) ? '#'.$p['fragment'] : '');
    }

    private function transform(string $html, string $uri, array $data): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $dom->encoding = 'UTF-8';
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//meta[@name="csrf-token"]') as $node) {
            $node->parentNode->removeChild($node);
        }
        foreach ($xpath->query('//form[translate(@method,"post","POST")="POST"]') as $form) {
            if ($form->hasAttribute('data-question-form')) {
                foreach (iterator_to_array($xpath->query('.//input[@name="_token"]', $form)) as $token) {
                    $token->parentNode->removeChild($token);
                }
                $endpoint = config('society.questions_endpoint', '') ?? '';
                if ($endpoint && ! preg_match('#^https://[^/]+/api/questions$#', $endpoint)) {
                    throw new \RuntimeException('QUESTIONS_ENDPOINT must be an HTTPS URL ending in /api/questions.');
                }
                $form->setAttribute('data-preview-question', $endpoint);
                $form->setAttribute('action', $endpoint ?: '#');
                $button = $xpath->query('.//button[@type="submit"]', $form)->item(0);
                $button->setAttribute('disabled', 'disabled');
                $status = $xpath->query('.//*[@data-question-status]', $form)->item(0);
                $status->textContent = $endpoint
                    ? 'Вопрос поступит представителям СНО. Регистрация не нужна.'
                    : 'Форма пока не принимает вопросы. Возможность отправки появится после подключения приёма обращений.';

                continue;
            }
            $notice = $dom->createElement('p', 'Отправка доступна в серверной версии. Это статическое превью.');
            $notice->setAttribute('class', 'alert info');
            $form->parentNode->replaceChild($notice, $form);
        }
        foreach ($xpath->query('//*[@href or @src or @action]') as $el) {
            foreach (['href', 'src', 'action'] as $attr) {
                if ($el->hasAttribute($attr)) {
                    $el->setAttribute($attr, $this->rewrite($el->getAttribute($attr)));
                }
            }
        }
        $body = $dom->getElementsByTagName('body')->item(0);
        $body->setAttribute('data-preview-route', $uri);
        $body->setAttribute('data-preview-base', $this->prefix);
        foreach (['task-card' => 'research', 'mentor-card' => 'mentors', 'content-card' => 'articles', 'event-row' => 'events'] as $class => $path) {
            foreach ($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," '.$class.' ")]') as $card) {
                $links = $card->hasAttribute('href') ? [$card] : iterator_to_array($card->getElementsByTagName('a'));
                foreach ($links as $link) {
                    if (preg_match('#/'.$path.'/(\d+)/#', $link->getAttribute('href'), $m)) {
                        $card->setAttribute('data-preview-id', $m[1]);
                        break;
                    }
                }
            }
        }
        $head = $dom->getElementsByTagName('head')->item(0);
        $robots = $dom->createElement('meta');
        $robots->setAttribute('name', 'robots');
        $robots->setAttribute('content', 'noindex,nofollow');
        $head->appendChild($robots);
        $json = $dom->createElement('script');
        $json->setAttribute('id', 'sno-preview-data');
        $json->setAttribute('type', 'application/json');
        $json->appendChild($dom->createTextNode(json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)));
        $body->appendChild($json);
        $script = $dom->createElement('script');
        $script->setAttribute('src', $this->prefix.'/js/pages-preview.js');
        $script->setAttribute('defer', 'defer');
        $body->appendChild($script);

        return $dom->saveHTML();
    }
}
