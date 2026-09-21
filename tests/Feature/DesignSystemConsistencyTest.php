<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards the design system against the drift that produced the mismatched
 * screens in the first place: two neutral palettes, ten spellings of the same
 * button, and every form inventing its own input styling.
 *
 * These assert on the Blade sources, not on rendered output, so a new view
 * that hand-rolls a control fails here rather than in someone's browser.
 */
class DesignSystemConsistencyTest extends TestCase
{
    private const VIEWS = __DIR__ . '/../../resources/views';

    /**
     * Blade views that participate in the Tailwind design system. PDF
     * templates are excluded: dompdf gets its own inline stylesheet because it
     * never loads the compiled CSS bundle.
     */
    private static function appViews(): array
    {
        $files = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::VIEWS));

        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            foreach (['/pdf/', 'pdf.blade.php', 'welcome.blade.php'] as $excluded) {
                if (str_contains($path, $excluded)) {
                    continue 2;
                }
            }

            $files[substr($path, strpos($path, 'resources/views'))] = file_get_contents($path);
        }

        return $files;
    }

    /** Renders offenders as "file — offending class list" lines. */
    private function assertNoOffenders(array $offenders, string $rule, string $fix): void
    {
        $this->assertSame([], $offenders, $rule . "\n" . $fix . "\n\nFound in:\n  "
            . implode("\n  ", $offenders) . "\n");
    }

    public function test_views_use_only_the_seal_palette(): void
    {
        // Neutrals are the warm `sand` ramp built from the seal's cream; the
        // brand colours are maroon / gold / forest, sampled from the seal
        // itself. Any other ramp is drift.
        $retired = 'gray|slate|zinc|neutral|stone|green|emerald|yellow|amber|purple|blue|indigo|teal|lime|orange|rose|pink|fuchsia|cyan';

        $offenders = [];

        foreach (self::appViews() as $name => $source) {
            preg_match_all(
                '/\b(?:bg|text|border|ring|divide|from|via|to|placeholder|fill|stroke)-(?:' . $retired . ')-\d+/',
                $source,
                $m
            );

            foreach (array_unique($m[0]) as $hit) {
                $offenders[] = "{$name} — {$hit}";
            }
        }

        $this->assertNoOffenders($offenders,
            'Views must use the palette sampled from the ISPSC seal.',
            'Neutrals are sand-*; brand colours are maroon-* (#780000), gold-* (#F0DC00) and forest-* (#145000). Semantic red-* and sky-* are allowed.');
    }

    public function test_views_do_not_hand_roll_form_controls(): void
    {
        $offenders = [];

        foreach (self::appViews() as $name => $source) {
            preg_match_all(
                '/<(?:input|select|textarea)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*?>/is',
                $source,
                $matches
            );

            foreach ($matches[0] as $tag) {
                // Checkboxes, radios and file inputs have their own treatment.
                if (preg_match('/type\s*=\s*["\'](checkbox|radio|hidden|file)["\']/i', $tag)) {
                    continue;
                }
                if (! preg_match('/class\s*=\s*"([^"]*)"/i', $tag, $c)) {
                    continue;
                }
                // Class lists built by Blade/Alpine are out of scope.
                if (str_contains($c[1], '{{') || str_contains($c[1], '{!!')) {
                    continue;
                }
                if (preg_match('/(^|\s)(input|select|textarea|file-input)(\s|$)/', $c[1])) {
                    continue;
                }
                // Padding or a border of its own means it skipped the recipe.
                if (preg_match('/\bborder\b|\bpx-\d|\brounded\b/', $c[1])) {
                    $offenders[] = "{$name} — " . trim($c[1]);
                }
            }
        }

        $this->assertNoOffenders($offenders,
            'Form controls must use the shared recipe.',
            'Use class="input" / "select" / "textarea" (or the <x-ui.input> component).');
    }

    public function test_views_do_not_hand_roll_primary_buttons(): void
    {
        $offenders = [];

        foreach (self::appViews() as $name => $source) {
            preg_match_all(
                '/<(?:a|button)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*?>/is',
                $source,
                $matches
            );

            foreach ($matches[0] as $tag) {
                if (! preg_match('/class\s*=\s*"([^"]*)"/i', $tag, $c)) {
                    continue;
                }
                $classes = $c[1];

                if (str_contains($classes, '{{') || str_contains($classes, '{!!')) {
                    continue;
                }
                if (preg_match('/(^|\s)btn(\s|$)/', $classes)) {
                    continue;
                }
                // Fixed-size circles are avatars and step markers, not buttons.
                if (preg_match('/\bw-\d+\s+h-\d+\b/', $classes)) {
                    continue;
                }
                if (preg_match('/\bbg-maroon-(700|800)\b/', $classes) && preg_match('/\bpx-[\d.]+/', $classes)) {
                    $offenders[] = "{$name} — " . trim($classes);
                }
            }
        }

        $this->assertNoOffenders($offenders,
            'The brand button has one spelling.',
            'Use class="btn btn-md btn-primary" (or the <x-ui.button> component).');
    }

    public function test_the_design_system_defines_every_primitive(): void
    {
        $css = file_get_contents(__DIR__ . '/../../resources/css/app.css');

        foreach ([
            '.btn', '.btn-primary', '.btn-secondary', '.btn-ghost',
            '.btn-success', '.btn-danger',
            '.input', '.select', '.textarea', '.label', '.hint',
            '.card', '.card-header', '.card-body',
            '.table', '.badge', '.alert',
        ] as $primitive) {
            $this->assertStringContainsString($primitive, $css,
                "The design system is missing {$primitive}.");
        }
    }

    /**
     * The employee and HR ledger pages show the same underlying balances.
     * They must therefore use one visual component, not three copied boxes
     * that can slowly diverge in font size, colour, radius or spacing.
     */
    public function test_leave_balance_summaries_use_the_shared_card(): void
    {
        foreach ([
            'employee/leave/index.blade.php',
            'leave/my-ledger.blade.php',
            'admin/leave/ledger.blade.php',
        ] as $view) {
            $source = file_get_contents(self::VIEWS . '/' . $view);

            $this->assertStringContainsString('<x-leave.balance-card', $source,
                "{$view} must use the shared leave balance card.");
        }
    }

    public function test_pdf_templates_stay_off_the_tailwind_bundle(): void
    {
        // dompdf never loads the compiled CSS, so a PDF view that reaches for
        // a design-system class would silently render unstyled.
        foreach (['leave-ledger-card', 'leave-approval-sheet'] as $view) {
            $source = file_get_contents(self::VIEWS . "/pdf/{$view}.blade.php");

            $this->assertStringContainsString('<style>', $source,
                "{$view} must carry its own stylesheet.");
            $this->assertMatchesRegularExpression('/@page\s*\{[^}]*A4/i', $source,
                "{$view} must declare A4 paper.");
        }
    }

    /**
     * Every directory holding an app view must be scanned by Tailwind.
     *
     * A directory left off the content list is never read, so any class used
     * only there produces no CSS and the page renders half-styled. That is how
     * My Profile and My Leave Ledger came to look unlike the rest of the
     * system, and nothing on screen says why.
     */
    public function test_tailwind_scans_every_directory_holding_app_views(): void
    {
        $config = file_get_contents(__DIR__ . '/../../tailwind.config.js');

        // The config used to list one glob per directory, and the two that were
        // missing meant any class used only in views/profile or views/leave
        // generated no CSS at all — those pages rendered half-styled and it
        // read as a design problem rather than a build one. A single recursive
        // glob makes the whole class of mistake impossible, so that is what is
        // asserted rather than the old directory-by-directory list.
        $recursive = str_contains($config, './resources/views/**/*.blade.php');

        if ($recursive) {
            $this->assertTrue(true);

            return;
        }

        // Still correct if someone goes back to listing them, as long as the
        // list is complete.
        preg_match_all('#\./resources/views/([a-z-]+)/#', $config, $matches);
        $scanned = array_flip($matches[1]);

        // Documents with their own inline stylesheet, and mail templates.
        $exempt = ['pdf', 'emails'];

        $missing = [];

        foreach (glob(self::VIEWS . '/*', GLOB_ONLYDIR) as $dir) {
            $name = basename($dir);

            if (in_array($name, $exempt, true) || isset($scanned[$name])) {
                continue;
            }

            if (glob($dir . '/*.blade.php') || glob($dir . '/*/*.blade.php')) {
                $missing[] = 'resources/views/' . $name;
            }
        }

        $this->assertSame([], $missing,
            "These view directories are not in tailwind.config.js content[].
"
            . "Classes used only there generate no CSS and the pages render unstyled.

"
            . "  " . implode("
  ", $missing) . "
");
    }

    /**
     * A class the scanner cannot see as a literal string.
     *
     * <x-badge> builds its class as 'badge-' . $tone, so Tailwind never reads
     * the finished name and drops any tone no view happens to spell out.
     * badge-violet was being purged exactly this way.
     */
    public function test_dynamically_composed_classes_are_safelisted(): void
    {
        $config = file_get_contents(__DIR__ . '/../../tailwind.config.js');
        $component = file_get_contents(self::VIEWS . '/components/badge.blade.php');

        // Only the tone map — @props carries a default colour name that is an
        // alias, not a tone, and there is no CSS class for it.
        preg_match('/\$map = \[(.*?)\];/s', $component, $block);
        preg_match_all("/=> '([a-z]+)'/", $block[1] ?? '', $matches);

        $tones = array_unique($matches[1]);

        $this->assertNotEmpty($tones, 'no tones found — has the badge component changed shape?');

        foreach ($tones as $tone) {
            $this->assertStringContainsString(
                'badge-' . $tone,
                $config,
                "badge-{$tone} is reachable from the component but is not safelisted, "
                . 'so it will be purged and that badge will render with no colour.',
            );
        }
    }

    /**
     * Sizes the templates ask for must exist on the spacing scale.
     *
     * w-4.5 is not a Tailwind default. Fifteen icons across four screens
     * carried it, generated nothing, and rendered at their intrinsic size —
     * visibly larger than the icons beside them.
     */
    public function test_icon_sizes_are_on_the_spacing_scale(): void
    {
        $config = file_get_contents(__DIR__ . '/../../tailwind.config.js');

        $offenders = [];

        foreach (self::appViews() as $view => $source) {
            preg_match_all('/\b[wh]-(\d+\.\d+)\b/', $source, $matches);

            foreach (array_unique($matches[1]) as $size) {
                // Tailwind ships .5 steps up to 3.5 only; anything else has to
                // be declared in the config.
                $shipped = in_array($size, ['0.5', '1.5', '2.5', '3.5'], true);
                $declared = str_contains($config, $size . ':');

                if (! $shipped && ! $declared) {
                    $offenders[] = "{$view} — w-{$size}/h-{$size}";
                }
            }
        }

        $this->assertSame([], array_unique($offenders),
            "These sizes are not on the spacing scale, so they generate no CSS.\n"
            . "Add the step to tailwind.config.js or use one that exists.\n\n"
            . "  " . implode("\n  ", array_unique($offenders)) . "\n");
    }

    /**
     * Rich body text has to be styled by the design system.
     *
     * The policy body was marked up for @tailwindcss/typography, which is not
     * installed, so it rendered as unstyled HTML — no heading sizes, no list
     * markers — inside an otherwise finished page.
     */
    public function test_body_html_uses_the_systems_own_rich_text_styling(): void
    {
        $offenders = [];

        foreach (self::appViews() as $view => $source) {
            if (preg_match('/\bclass="[^"]*\bprose\b/', $source)) {
                $offenders[] = $view;
            }
        }

        $this->assertSame([], $offenders,
            "These views use prose-*, which needs a plugin the project does not install.\n"
            . "Use the .rich-text class instead.\n\n"
            . "  " . implode("\n  ", $offenders) . "\n");
    }

    /**
     * One spelling for a chip and one for an icon button.
     *
     * Both recur in tables and toolbars across every role, and each hand-rolled
     * copy drifts a shade or a couple of pixels from the last.
     */
    public function test_chips_and_icon_buttons_use_their_shared_classes(): void
    {
        $offenders = [];

        foreach (self::appViews() as $view => $source) {
            // A pill with a tinted fill, written out by hand.
            if (preg_match('/class="inline-flex[^"]*rounded-full[^"]*(?:bg-maroon-50|bg-sand-100)[^"]*"/', $source)) {
                $offenders[] = "{$view} — hand-rolled chip";
            }

            // A square, borderless action button in a row.
            if (preg_match('/class="inline-flex[^"]*justify-center[^"]*w-9 h-9[^"]*rounded-lg[^"]*"/', $source)) {
                $offenders[] = "{$view} — hand-rolled icon button";
            }
        }

        $this->assertSame([], $offenders,
            "Use .chip and .icon-btn rather than spelling them out.\n\n"
            . "  " . implode("\n  ", $offenders) . "\n");
    }

    /**
     * A page's action buttons wrap on a phone instead of widening the page.
     *
     * The row was marked shrink-0, so three buttons on the HR dashboard or
     * four on Leave Management kept their desktop width and pushed every
     * screen that used them sideways on a phone.
     */
    public function test_page_header_actions_wrap_on_a_narrow_screen(): void
    {
        $source = file_get_contents(self::VIEWS . '/components/page-header.blade.php');

        preg_match('/<div class="([^"]*)">\s*@if \(\$back\)/', $source, $row);

        $this->assertNotEmpty($row, 'could not find the action row in the page header');
        $this->assertStringContainsString('flex-wrap', $row[1]);
        $this->assertStringNotContainsString('shrink-0', $row[1]);
        $this->assertStringContainsString('max-w-full', $row[1]);
    }

    /**
     * An uploaded file's name, which has no spaces to break at, may wrap
     * anywhere rather than run off the edge of a phone screen.
     */
    public function test_uploaded_file_names_can_wrap(): void
    {
        $offenders = [];

        foreach (self::appViews() as $view => $source) {
            // The element that prints the name, up to the name itself.
            preg_match_all(
                '/<(?:p|span)\s+class="([^"]*)">\s*\{\{\s*\$\w+->(?:file_original_name|original_filename)\b/',
                $source,
                $matches,
            );

            foreach ($matches[1] as $classes) {
                if (! preg_match('/\b(?:break-all|truncate)\b/', $classes)) {
                    $offenders[] = "{$view} — class=\"{$classes}\"";
                }
            }
        }

        $this->assertSame([], $offenders,
            "These print an uploaded file name that cannot wrap:\n\n"
            . "  " . implode("\n  ", $offenders) . "\n");
    }
}
