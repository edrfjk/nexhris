<?php

namespace Tests\Feature;

use App\Models\HrPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A policy is read by every member of staff, so its body must be safe to print.
 *
 * The body is HTML from the rich-text editor, but the server stores whatever is
 * posted in its place, and it used to be printed to employees exactly as
 * stored. Script saved into one policy ran in the browser of everyone who
 * opened it — which is how a single compromised HR session becomes every staff
 * member's session.
 */
class PolicyContentSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function render(string $body): string
    {
        return (string) (new HrPolicy(['type' => 'text', 'body' => $body]))->renderedBody();
    }

    /** @return array<string, array{string}> */
    public static function attacks(): array
    {
        return [
            'script tag' => ['<p>hi</p><script>alert(document.cookie)</script>'],
            'image error handler' => ['<img src=x onerror="alert(1)">'],
            'event handler on a paragraph' => ['<p onclick="alert(1)">click</p>'],
            'javascript: link' => ['<a href="javascript:alert(1)">x</a>'],
            'data: link' => ['<a href="data:text/html,<script>alert(1)</script>">x</a>'],
            'iframe' => ['<iframe src="https://evil.example"></iframe>'],
            'svg with onload' => ['<svg onload="alert(1)"></svg>'],
            'inline style' => ['<p style="background:url(javascript:alert(1))">x</p>'],
            'object and embed' => ['<object data="x.swf"></object><embed src="x.swf">'],
            'form' => ['<form action="https://evil.example"><input name="password"></form>'],
            'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://evil.example">'],
            'uppercase script' => ['<SCRIPT>alert(1)</SCRIPT>'],
            'handler on a heading' => ['<h2 onmouseover="alert(1)">Title</h2>'],
        ];
    }

    #[DataProvider('attacks')]
    public function test_nothing_executable_survives(string $payload): void
    {
        $out = $this->render($payload);

        $this->assertDoesNotMatchRegularExpression(
            '/<script|\son\w+\s*=|javascript:|<iframe|<svg|<object|<embed|<form|<meta|\sstyle\s*=|data:text/i',
            $out,
            "this reached the page: {$out}",
        );
    }

    /** Sanitising must not cost HR the formatting the editor gives them. */
    public function test_the_editors_own_formatting_survives(): void
    {
        $out = html_entity_decode($this->render(
            '<h2>Leave Rules</h2>'
            . '<p class="ql-align-center"><strong>bold</strong> <em>italic</em> <u>under</u> <s>struck</s></p>'
            . '<ul><li>older bullet</li></ul>'
            . '<ol><li data-list="bullet">newer bullet</li><li data-list="ordered">numbered</li></ol>'
            . '<p><a href="https://ispsc.edu.ph">site</a> <a href="mailto:hr@ispsc.edu.ph">mail</a></p>'
        ), ENT_QUOTES | ENT_HTML5);

        foreach ([
            '<strong>bold</strong>', '<em>italic</em>', '<u>under</u>', '<s>struck</s>',
            'ql-align-center', '<ul>', 'data-list="bullet"', 'data-list="ordered"',
            'href="https://ispsc.edu.ph"', 'mailto:hr@ispsc.edu.ph',
        ] as $kept) {
            $this->assertStringContainsString($kept, $out, "{$kept} was stripped");
        }

        // Headings still carry the anchors the table of contents links to.
        $this->assertStringContainsString('id="leave-rules"', $out);
    }

    public function test_links_open_away_from_the_policy(): void
    {
        $out = $this->render('<a href="https://ispsc.edu.ph">site</a>');

        $this->assertStringContainsString('target="_blank"', $out);
        $this->assertStringContainsString('noopener', $out);
    }

    /** The employee's page, end to end, not only the model method. */
    public function test_the_employee_page_does_not_run_a_stored_script(): void
    {
        $hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'active']);

        $policy = HrPolicy::create([
            'title' => 'Code of Conduct',
            'type' => 'text',
            'body' => '<p>Be kind.</p><script>window.stolen = document.cookie</script>',
            'is_published' => true,
            'created_by' => $hr->id,
        ]);

        $this->actingAs($employee)
            ->get(route('policies.show', $policy))
            ->assertOk()
            ->assertSee('Be kind.')
            ->assertDontSee('window.stolen', false);
    }

    /** The HR preview goes through the same sanitiser as the employee page. */
    public function test_the_hr_preview_does_not_run_a_stored_script(): void
    {
        $hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        HrPolicy::create([
            'title' => 'Code of Conduct',
            'type' => 'text',
            'body' => '<p>Be kind.</p><script>window.stolen = document.cookie</script>',
            'is_published' => true,
            'created_by' => $hr->id,
        ]);

        $this->actingAs($hr)
            ->get(route('admin.policies.index'))
            ->assertOk()
            ->assertSee('Be kind.')
            ->assertDontSee('window.stolen', false);
    }

    /** No view prints a policy body without sanitising it first. */
    public function test_no_view_prints_a_raw_policy_body(): void
    {
        $offenders = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            if (preg_match('/\{!!\s*\$\w+->body\s*!!\}/', file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'these print a stored body without renderedBody()');
    }

    /**
     * Staff are never told to fill a Civil Service form in an editor that
     * strips its tick boxes.
     *
     * The PDS page used to recommend Google Sheets. Google Sheets, Excel in a
     * browser, WPS and phone apps drop form controls when they save, and a
     * sheet filled in any of them uploads and converts with every checkbox
     * missing — the file itself, which no converter can put back.
     */
    public function test_the_forms_do_not_recommend_an_editor_that_strips_tick_boxes(): void
    {
        foreach (['employee/pds/editor.blade.php', 'employee/leave/index.blade.php'] as $view) {
            $source = file_get_contents(resource_path("views/{$view}"));

            // Only the rendered text, not comments explaining the history.
            $visible = preg_replace('/\{\{--.*?--\}\}/s', '', $source);

            $this->assertDoesNotMatchRegularExpression(
                '/open it in[^.]*Google Sheets/i',
                $visible,
                "{$view} recommends Google Sheets",
            );

            $this->assertMatchesRegularExpression(
                '/Google Sheets[^.]*(remove|strip|drop|Do not)|Do not[^.]*Google Sheets/i',
                $visible,
                "{$view} does not warn against the editors that remove the tick boxes",
            );
        }
    }

    /**
     * Creating and editing a policy use the same editor.
     *
     * They used to load Quill 1.3.7 and Quill 2.0.3 respectively, which write
     * different HTML for the same list, so opening a policy to fix a typo could
     * turn its bullet points into a numbered list.
     */
    public function test_create_and_edit_load_the_same_editor(): void
    {
        $views = resource_path('views/admin/policies');

        $version = function (string $file): array {
            preg_match_all('#quill@([\d.]+)/#', file_get_contents($file), $m);

            return array_values(array_unique($m[1]));
        };

        $create = $version("{$views}/create.blade.php");
        $edit = $version("{$views}/edit.blade.php");

        $this->assertCount(1, $create, 'the create page loads more than one Quill');
        $this->assertCount(1, $edit, 'the edit page loads more than one Quill');
        $this->assertSame($edit, $create, 'create and edit load different versions of the editor');
    }
}
