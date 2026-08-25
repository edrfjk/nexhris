<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Html as HtmlWriter;

/**
 * Converts a real .xlsx into PDF.
 *
 * The campus forms carry merged cells, column widths and print areas that a
 * rebuilt-from-data PDF cannot reproduce, so every export is a conversion of
 * the actual workbook rather than a re-render of its values. That holds for
 * both renderers below — neither reads the database.
 *
 * Two renderers, picked automatically:
 *
 *  - LibreOffice, when the binary is present. Highest fidelity, and what runs
 *    in development.
 *  - PhpSpreadsheet's Dompdf writer otherwise. Pure PHP, so it works on shared
 *    hosting with no shell access. Styling is simpler — it renders through an
 *    HTML representation — but the values, merges and layout come from the
 *    real workbook.
 *
 * Set PDF_RENDERER=php to force the pure-PHP path in development and see
 * exactly what production will produce.
 *
 * Two details matter for correctness:
 *
 *  - LibreOffice keeps one lock per user profile. Concurrent requests sharing
 *    the default profile fail silently, so each conversion is given its own
 *    throwaway profile directory.
 *  - Conversion is slow, so results are cached against a hash of the source
 *    file. An unchanged workbook is converted once.
 */
class XlsxToPdfService
{
    /** Seconds before a stuck conversion is abandoned. */
    private const TIMEOUT = 120;

    /** Below this the form stops being readable, so it is never shrunk past it. */
    private const MIN_SCALE = 35;

    public function __construct(private ?string $binary = null)
    {
        $this->binary = $binary ?? $this->locateBinary();
    }

    /** Whether LibreOffice specifically is usable. */
    public function isAvailable(): bool
    {
        if (strtolower((string) config('pdf.renderer', 'auto')) === 'php') {
            return false;
        }

        return $this->binary !== null && is_file($this->binary);
    }

    /**
     * Whether a PDF can be produced at all.
     *
     * The pure-PHP writer needs no binary, so this is true wherever the app
     * runs. Callers that only want to know "will the viewer get a PDF?" should
     * ask this rather than isAvailable().
     */
    public function canRender(): bool
    {
        return true;
    }

    /** Which renderer a conversion would use right now. */
    public function renderer(): string
    {
        return $this->isAvailable() ? 'libreoffice' : 'php';
    }

    /**
     * Returns the absolute path of a PDF rendering of $xlsxPath.
     *
     * @param  bool  $forceA4  Rewrite every sheet's page setup to fit-to-width
     *                         A4 before converting. The supplied templates are
     *                         set to US Letter, which prints short on A4 paper.
     */
    /**
     * @param  string|null  $cacheKey  Identity of the document this workbook
     *   belongs to. Two people's ledgers are copied from the same master and
     *   stay byte-identical until something is written into them, so a cache
     *   keyed on content alone hands the first-converted person's card to
     *   everyone else. Pass an owner-specific key for per-person documents.
     */
    public function convert(
        string $xlsxPath,
        bool $forceA4 = true,
        bool $useCache = true,
        ?string $cacheKey = null,
    ): string {
        if (! is_file($xlsxPath)) {
            throw new \RuntimeException("Workbook not found: {$xlsxPath}");
        }

        $cachePath = $this->cachePathFor($xlsxPath, $forceA4, $cacheKey);

        if ($useCache && is_file($cachePath)) {
            return $cachePath;
        }

        // No LibreOffice — convert the same workbook in pure PHP instead.
        if (! $this->isAvailable()) {
            return $this->convertWithPhp($xlsxPath, $cachePath, $forceA4);
        }

        $work = $this->makeWorkspace();

        try {
            $source = $work . DIRECTORY_SEPARATOR . 'source.xlsx';
            copy($xlsxPath, $source);

            if ($forceA4) {
                $this->applyA4PageSetup($source);
            }

            $this->runLibreOffice($source, $work);

            $produced = $work . DIRECTORY_SEPARATOR . 'source.pdf';

            if (! is_file($produced)) {
                throw new \RuntimeException('LibreOffice produced no PDF output.');
            }

            if (! is_dir(dirname($cachePath))) {
                mkdir(dirname($cachePath), 0775, true);
            }

            copy($produced, $cachePath);

            return $cachePath;
        } catch (\RuntimeException $e) {
            // A broken or half-installed LibreOffice must not take the export
            // down when a working pure-PHP path exists.
            Log::warning('LibreOffice conversion failed; falling back to the PHP renderer.', [
                'error' => $e->getMessage(),
            ]);

            return $this->convertWithPhp($xlsxPath, $cachePath, $forceA4);
        } finally {
            $this->deleteDirectory($work);
        }
    }

    /**
     * Streams a converted workbook to the browser.
     *
     * Where conversion is unavailable — shared hosting with no LibreOffice —
     * the original workbook is sent instead. It is the same official document
     * the PDF would have been made from, so the person still gets their form;
     * they open it in Excel rather than the browser. Far better than a 500 on
     * a page they reached from a normal link.
     */
    public function stream(
        string $xlsxPath,
        string $downloadName,
        bool $forceA4 = true,
        ?string $cacheKey = null,
    ) {
        try {
            $pdf = $this->convert($xlsxPath, $forceA4, true, $cacheKey);
        } catch (\RuntimeException) {
            return $this->streamWorkbook($xlsxPath, $downloadName);
        }

        return response()->file($pdf, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($downloadName) . '"',
        ]);
    }

    /** The untouched .xlsx, named like the PDF the caller asked for. */
    private function streamWorkbook(string $xlsxPath, string $downloadName)
    {
        $name = preg_replace('/\.pdf$/i', '.xlsx', $downloadName);

        return response()->download(
            $xlsxPath,
            $name,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function locateBinary(): ?string
    {
        // Through config, not env(): env() returns null once the config
        // cache is warmed, which production should be doing.
        if ($configured = config('pdf.libreoffice_path')) {
            return is_file($configured) ? $configured : null;
        }

        $candidates = [
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            '/usr/bin/soffice',
            '/usr/bin/libreoffice',
            '/usr/local/bin/soffice',
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The template workbooks are authored on US Letter. Printing them on A4
     * without this crops the right-hand balance columns.
     */
    private function applyA4PageSetup(string $path): void
    {
        try {
            $spreadsheet = IOFactory::load($path);

            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $setup = $sheet->getPageSetup();
                $setup->setPaperSize(PageSetup::PAPERSIZE_A4);
                $setup->setFitToWidth(1);
                $setup->setFitToHeight(0);
            }

            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
            $spreadsheet->disconnectWorksheets();
        } catch (\Throwable $e) {
            // A workbook we cannot re-save is still worth converting as-is —
            // better a Letter-sized PDF than no PDF at all.
            Log::warning('Could not force A4 page setup before conversion.', [
                'file' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function runLibreOffice(string $source, string $workspace): void
    {
        // A private profile per run; without it concurrent conversions collide
        // on LibreOffice's single-instance lock and return silently.
        $profile = $workspace . DIRECTORY_SEPARATOR . 'profile';
        @mkdir($profile, 0775, true);

        $command = [
            $this->binary,
            '--headless',
            '--norestore',
            '--nolockcheck',
            '--nodefault',
            '-env:UserInstallation=file:///' . str_replace('\\', '/', $profile),
            '--convert-to', 'pdf:calc_pdf_Export',
            '--outdir', $workspace,
            $source,
        ];

        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $workspace
        );

        if (! is_resource($process)) {
            throw new \RuntimeException('LibreOffice could not be started.');
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $deadline = time() + self::TIMEOUT;
        $stderr = '';

        while (true) {
            $status = proc_get_status($process);
            $stderr .= (string) stream_get_contents($pipes[2]);

            if (! $status['running']) {
                break;
            }

            if (time() > $deadline) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                throw new \RuntimeException('The PDF conversion timed out.');
            }

            usleep(100_000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        // LibreOffice reports success even for some failures, so the caller
        // checks for the output file too. Only log the detail here.
        if ($exit !== 0) {
            Log::warning('LibreOffice exited non-zero during conversion.', [
                'exit' => $exit,
                'stderr' => trim($stderr),
            ]);
        }
    }

    /** Cache key covers the file contents and the A4 flag. */
    /**
     * Converts with PhpSpreadsheet and Dompdf — no binary, no shell.
     *
     * Still a conversion of the real workbook: the reader opens the same
     * .xlsx and what is drawn comes from its cells, merges and column widths.
     *
     * Three things have to hold for the output to be usable:
     *
     *  - A4, always. The supplied templates are US Letter, which prints short
     *    and crops the right-hand columns on A4 paper.
     *  - The template's own orientation. The ledger card is landscape and the
     *    PDS is portrait; forcing either one rotates the other on its side.
     *  - Fit to width. Dompdf does not scale a wide sheet, it runs it off the
     *    page and clips, so the table is constrained to the printable width
     *    with its column proportions kept.
     */
    private function convertWithPhp(string $xlsxPath, string $cachePath, bool $forceA4): string
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setIncludeCharts(false);
            $book = $reader->load($xlsxPath);

            // The writer reads page setup from the first sheet only, so that
            // is the one whose orientation decides the page.
            $orientation = $book->getSheet(0)->getPageSetup()->getOrientation();

            if ($orientation === PageSetup::ORIENTATION_DEFAULT) {
                $orientation = PageSetup::ORIENTATION_PORTRAIT;
            }

            // No page-setup rewrite here. The paper is set on the renderer
            // itself, and clearing fitToHeight would throw away the one flag
            // that says this sheet has to land on a single page.
            $this->trimToPrintAreas($book);

            $html = $this->workbookHtml($book, $orientation);

            $book->disconnectWorksheets();

            $this->renderHtmlToPdf($html, $orientation, $cachePath, $forceA4);

            if (! is_file($cachePath)) {
                throw new \RuntimeException('The PDF writer produced no output.');
            }

            return $cachePath;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'This workbook could not be converted to PDF: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * The workbook as HTML, one wrapper per printable sheet.
     *
     * Built sheet by sheet rather than in one call, because the sheets of a
     * form do not share a page setup. CS Form 212 asks for four different
     * margin sets (top 9.1mm on C1, 4.0mm on C2, and no side margins at all
     * on three of them); a single @page rule applied to every sheet is what
     * makes some pages sit differently from others.
     *
     * Dompdf has one @page box for the document, so the smallest margin each
     * side asks for becomes that box, and every sheet adds the remainder as
     * padding. Each sheet then sits exactly where its own page setup puts it.
     */
    private function workbookHtml(Spreadsheet $book, string $orientation): string
    {
        $sheets = $this->printableSheets($book);
        $base = $this->baseMargins($book, $sheets);

        // Header without the writer's stylesheet: with inline CSS every cell
        // already carries its own styling, and that block repeats the row
        // heights unscaled, fighting the per-sheet scaling applied below.
        $html = $this->sheetWriter($book, $sheets[0])->generateHTMLHeader(false);
        $html .= $this->pageCss($orientation, $base);

        foreach ($sheets as $position => $index) {
            // A fresh writer per sheet. Reusing one and moving its sheet index
            // keeps state from the first pass, and every sheet after it comes
            // out with its merged cells flattened into a plain grid.
            $writer = $this->sheetWriter($book, $index);

            $margins = $book->getSheet($index)->getPageMargins();

            // Only the excess over the shared page box, so the two do not add up.
            $padding = sprintf(
                'padding: %.2fmm %.2fmm %.2fmm %.2fmm;',
                max(0, $margins->getTop() * 25.4 - $base['top']),
                max(0, $margins->getRight() * 25.4 - $base['right']),
                max(0, $margins->getBottom() * 25.4 - $base['bottom']),
                max(0, $margins->getLeft() * 25.4 - $base['left']),
            );

            $break = $position > 0 ? 'page-break-before: always;' : '';

            $body = $writer->generateSheetData();

            $scale = $this->fittedScale(
                $book->getSheet($index), $body, $orientation, $base, $padding,
            );

            $html .= '<div class="xlsx-sheet" style="' . $padding . $break . '">'
                . $this->applySheetScale($body, $scale)
                . '</div>';
        }

        return $html . $this->sheetWriter($book, $sheets[0])->generateHTMLFooter();
    }

    /**
     * The percentage this sheet should print at.
     *
     * A sheet set to "fit to 1 page tall" must end up on one page. The scale
     * recorded in the file is Excel's answer for Excel's own row model; drawn
     * as HTML the same rows come out around a quarter taller, because cells
     * that wrap grow by whole lines. Predicting that reliably is not
     * possible — a wrapped cell's height depends on the font, the column
     * width and the text — so the sheet is rendered and measured instead,
     * stepping down until it fits.
     *
     * Bounded to a handful of attempts, and only for sheets that ask to fit.
     * The finished PDF is cached, so this is paid once per document.
     */
    private function fittedScale(
        Worksheet $sheet,
        string $body,
        string $orientation,
        array $base,
        string $padding,
    ): int {
        $setup = $sheet->getPageSetup();
        $declared = $setup->getScale() ?: 100;

        if ($setup->getFitToHeight() !== 1) {
            return $declared;
        }

        // A sheet naming its own scale is stating an intention: this is the
        // size the form is meant to be read at. One left at 100% with
        // fit-to-page set is not — Excel ignores that number and computes its
        // own, so there is no intention there to respect.
        $authorChoseScale = $declared !== 100;

        // How far the content would have to shrink, from the row heights the
        // sheet declares. Rendered as HTML the same rows come out roughly a
        // third taller — cells that wrap grow by whole lines — so the
        // estimate is deliberately pessimistic. It is only a starting point;
        // the renders below decide.
        $needed = $this->estimatedScale($sheet, $orientation);

        // Where the author set a scale and the sheet is far past it, no amount
        // of the modest shrinking below will help: it holds more than a page.
        // Skip straight to printing it at its intended size, which also skips
        // half a minute of fruitless rendering.
        if ($authorChoseScale && $needed < $declared * 0.58) {
            return $declared;
        }

        $floor = $authorChoseScale
            ? (int) round($declared * 0.58)
            : self::MIN_SCALE;

        // A few candidates around the estimate rather than a walk down from
        // full size. Each one costs a full render of the sheet.
        $candidates = [];

        foreach ([1.0, 0.92, 0.84] as $step) {
            $candidates[] = max($floor, min($declared, (int) round($needed * $step)));
        }

        foreach (array_unique($candidates) as $scale) {
            $candidate = $this->applySheetScale($body, $scale);

            if ($this->rendersOnOnePage($candidate, $orientation, $base, $padding)) {
                return $scale;
            }
        }

        // Nothing fit.
        //
        // Where the author set a scale, print at the size the form was drawn
        // for and let it run onto another page: a readable card over two pages
        // beats an unreadable one squeezed onto one. Where no scale was
        // chosen, the smallest candidate is the best on offer.
        return $authorChoseScale ? $declared : max($floor, min($candidates));
    }

    /**
     * A first guess at the scale this sheet needs to fit one page.
     *
     * Built from the declared row heights with a third added for the way HTML
     * renders them taller. Cheap — no rendering — so it is used to aim the
     * probes rather than to decide anything on its own.
     */
    private function estimatedScale(Worksheet $sheet, string $orientation): int
    {
        $content = $this->contentHeightPoints($sheet) * 1.33;

        if ($content <= 0) {
            return 100;
        }

        $page = $orientation === PageSetup::ORIENTATION_LANDSCAPE ? 595.28 : 841.89;
        $usable = $page - (2 * 4.0 * 2.83465) - $sheet->getHighestRow();

        return max(1, (int) floor($usable / $content * 100));
    }

    /** Total height of the sheet's rows, in points. */
    private function contentHeightPoints(Worksheet $sheet): float
    {
        $default = $sheet->getDefaultRowDimension()->getRowHeight();
        $default = $default > 0 ? $default : 15.0;

        $total = 0.0;

        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            $height = $sheet->getRowDimension($row)->getRowHeight();
            $total += $height > 0 ? $height : $default;
        }

        return $total;
    }

    /**
     * Renders one sheet on its own and reports whether it stayed on a page.
     *
     * The probe has to use the page box and the sheet padding the real
     * document will use. Measuring a bare sheet says it fits, then the margin
     * and padding eat into the page and it spills — which is exactly how a
     * four-page form came out on eight.
     */
    private function rendersOnOnePage(string $body, string $orientation, array $base, string $padding): bool
    {
        try {
            $html = '<html><head><meta charset="utf-8">'
                . $this->pageCss($orientation, $base)
                . '</head><body><div style="' . $padding . '">' . $body . '</div></body></html>';

            $dompdf = $this->dompdf($orientation, true);
            $dompdf->loadHtml($html);
            $dompdf->render();

            return preg_match_all('/\/Type\s*\/Page[^s]/', $dompdf->output()) <= 1;
        } catch (\Throwable $e) {
            // If the probe cannot run, accept the scale rather than loop.
            return true;
        }
    }

    /**
     * Applies the sheet's own print scaling to the markup.
     *
     * Every sheet of CS Form 212 is set to print at 67–77% so it lands on a
     * single page; the widths, heights and type sizes in the file are the
     * unscaled ones. Emitting them as-is makes each sheet two or three pages
     * long. Horizontal scaling is already handled by fitting the table to the
     * page width, so only the vertical metrics and the type are adjusted.
     */
    private function applySheetScale(string $html, ?int $scale): string
    {
        // 100 and "unset" both mean leave it alone. Anything outside a sane
        // band is more likely a corrupt value than a real intention.
        if ($scale === null || $scale === 100 || $scale < 10 || $scale > 400) {
            return $html;
        }

        $factor = $scale / 100;

        return (string) preg_replace_callback(
            '/(font-size|height|line-height)\s*:\s*(\d+(?:\.\d+)?)\s*(pt|px)/i',
            static function (array $match) use ($factor): string {
                return $match[1] . ':' . round((float) $match[2] * $factor, 2) . $match[3];
            },
            $html,
        );
    }

    /** A writer scoped to one sheet, configured for faithful output. */
    private function sheetWriter(Spreadsheet $book, int $index): HtmlWriter
    {
        $writer = new HtmlWriter($book);
        $writer->setPreCalculateFormulas(false);
        $writer->setUseInlineCss(true);
        $writer->setPreserveFormatAndValue(true);
        $writer->setSheetIndex($index);

        // Required before generateSheetData(): the column widths and the
        // per-cell fonts, borders and fills are all read out of the style
        // table this builds. Without it the sheet comes out as an unstyled
        // grid of equal-width columns — the form's layout gone.
        $writer->generateStyles(false);

        return $writer;
    }

    /**
     * Cuts every sheet down to its declared print area.
     *
     * A worksheet is usually far larger than the part that prints. CS Form
     * 212 defines A1:S264 on its first sheet but a print area of A1:N61 —
     * the rest is scratch space Excel never puts on paper. Rendering all of
     * it turned a four-page form into twenty-four, and most of the
     * conversion time went into pages nobody asked for.
     *
     * Done on the in-memory workbook only; the stored .xlsx is untouched.
     */
    private function trimToPrintAreas(Spreadsheet $book): void
    {
        foreach ($book->getAllSheets() as $sheet) {
            $area = $sheet->getPageSetup()->getPrintArea();

            if (! $area) {
                continue;
            }

            try {
                [$first, $last] = $this->printAreaBounds($area);

                // Trailing rows and columns first: dropping from the end
                // cannot shift what comes before it.
                $lastRow = $sheet->getHighestRow();
                if ($lastRow > $last['row']) {
                    $sheet->removeRow($last['row'] + 1, $lastRow - $last['row']);
                }

                $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());
                if ($lastColumn > $last['column']) {
                    $sheet->removeColumnByIndex($last['column'] + 1, $lastColumn - $last['column']);
                }

                // Then anything before the area starts.
                if ($first['row'] > 1) {
                    $sheet->removeRow(1, $first['row'] - 1);
                }

                if ($first['column'] > 1) {
                    $sheet->removeColumnByIndex(1, $first['column'] - 1);
                }
            } catch (\Throwable $e) {
                // An unreadable print area is not worth losing the page over.
                Log::warning('Could not apply a print area; rendering the whole sheet.', [
                    'sheet' => $sheet->getTitle(),
                    'area' => $area,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * The bounding box of a print area, which may name several ranges.
     *
     * @return array{0: array{column: int, row: int}, 1: array{column: int, row: int}}
     */
    private function printAreaBounds(string $area): array
    {
        $firstColumn = $firstRow = PHP_INT_MAX;
        $lastColumn = $lastRow = 0;

        foreach (explode(',', $area) as $range) {
            $range = str_replace('$', '', trim($range));

            if ($range === '') {
                continue;
            }

            [$start, $end] = str_contains($range, ':')
                ? explode(':', $range, 2)
                : [$range, $range];

            [$startColumn, $startRow] = Coordinate::coordinateFromString($start);
            [$endColumn, $endRow] = Coordinate::coordinateFromString($end);

            $firstColumn = min($firstColumn, Coordinate::columnIndexFromString($startColumn));
            $lastColumn = max($lastColumn, Coordinate::columnIndexFromString($endColumn));
            $firstRow = min($firstRow, (int) $startRow);
            $lastRow = max($lastRow, (int) $endRow);
        }

        if ($lastColumn === 0 || $lastRow === 0) {
            throw new \RuntimeException("Unreadable print area: {$area}");
        }

        return [
            ['column' => $firstColumn, 'row' => $firstRow],
            ['column' => $lastColumn, 'row' => $lastRow],
        ];
    }

    /**
     * Indexes of the sheets that should print.
     *
     * Workbooks carry hidden helper sheets — CS Form 212 has a "Lookup" sheet
     * feeding its dropdowns — and Excel does not print those. Including them
     * adds a page of scratch data to the middle of someone's PDS.
     */
    private function printableSheets(Spreadsheet $book): array
    {
        $printable = [];

        foreach ($book->getAllSheets() as $index => $sheet) {
            if ($sheet->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE) {
                continue;
            }

            $printable[] = $index;
        }

        // A workbook of nothing but hidden sheets still has to render something.
        return $printable ?: [0];
    }

    /**
     * The largest page box every printable sheet can live inside — that is,
     * the smallest margin any of them asks for, per side.
     */
    private function baseMargins(Spreadsheet $book, array $sheets): array
    {
        $sides = ['top' => [], 'right' => [], 'bottom' => [], 'left' => []];

        foreach ($sheets as $index) {
            $margins = $book->getSheet($index)->getPageMargins();

            $sides['top'][] = $margins->getTop() * 25.4;
            $sides['right'][] = $margins->getRight() * 25.4;
            $sides['bottom'][] = $margins->getBottom() * 25.4;
            $sides['left'][] = $margins->getLeft() * 25.4;
        }

        return [
            // A floor of 3mm on the top and bottom: a sheet that runs past one
            // page would otherwise start hard against the paper edge on every
            // page after the first, where no per-sheet padding reaches.
            'top' => max(3.0, min($sides['top'])),
            'bottom' => max(3.0, min($sides['bottom'])),
            'right' => min($sides['right']),
            'left' => min($sides['left']),
        ];
    }

    private function pageCss(string $orientation, array $base): string
    {
        $size = $orientation === PageSetup::ORIENTATION_LANDSCAPE
            ? 'A4 landscape'
            : 'A4 portrait';

        $margin = sprintf(
            '%.2fmm %.2fmm %.2fmm %.2fmm',
            $base['top'], $base['right'], $base['bottom'], $base['left'],
        );

        return <<<CSS
<style>
    /* A4, always: the campus templates are set to Letter or a custom size,
       which prints short and crops the right-hand columns on A4 paper. */
    @page { size: {$size}; margin: {$margin}; }

    body { margin: 0; }

    /* Constrained to the printable width, keeping the column proportions the
       workbook declares — Excel's "fit to width: 1 page" in CSS terms. Dompdf
       does not scale a wide sheet; without this it runs off and is clipped. */
    table {
        width: 100% !important;
        max-width: 100% !important;
        table-layout: fixed;
        border-collapse: collapse;
    }

    /* No global wrapping rule: Excel wraps only where a cell asks for it,
       and forcing it here turns single-line rows into three-line ones and
       pushes the form onto extra pages. Long words are broken rather than
       allowed to run out of a fixed-width cell, so a lengthy reason on a
       ledger line stays inside its column. */
    td, th { overflow-wrap: break-word; }

    /* Excel rows are exactly as tall as the sheet says. Dompdf's default cell
       padding and 1.2x line spacing add a few points to every row, which over
       sixty rows is enough to push each sheet onto a second page. */
    td, th {
        padding: 0;
        line-height: 1;
        vertical-align: middle;
    }

    table { border-spacing: 0; }

    /* Arial Narrow is not one of Dompdf's core faces. Naming the fallbacks
       keeps a narrow face where the form asked for one instead of dropping to
       something wider and reflowing every column. */
    td, th, div, span, p {
        font-family: Arial, Helvetica, "DejaVu Sans", sans-serif;
    }

    /* A row is never split down the middle, and a card long enough to run
       past one page repeats its heading on the next. */
    tr { page-break-inside: avoid; }
    thead { display: table-header-group; }
    .xlsx-sheet { page-break-inside: auto; }
</style>
CSS;
    }

    /** A configured Dompdf, with the page box set explicitly. */
    private function dompdf(string $orientation, bool $forceA4): Dompdf
    {
        $options = new DompdfOptions();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);

        // Set on the renderer as well as in @page: Dompdf honours whichever
        // it resolves first, and the two must not disagree.
        if ($forceA4) {
            $dompdf->setPaper(
                'A4',
                $orientation === PageSetup::ORIENTATION_LANDSCAPE ? 'landscape' : 'portrait',
            );
        }

        return $dompdf;
    }

    /** Hands the HTML to Dompdf with the page box set explicitly. */
    private function renderHtmlToPdf(string $html, string $orientation, string $cachePath, bool $forceA4): void
    {
        $dompdf = $this->dompdf($orientation, $forceA4);

        $dompdf->loadHtml($html);
        $dompdf->render();

        if (! is_dir(dirname($cachePath))) {
            mkdir(dirname($cachePath), 0775, true);
        }

        file_put_contents($cachePath, $dompdf->output());
    }

    private function cachePathFor(string $xlsxPath, bool $forceA4, ?string $cacheKey = null): string
    {
        // Content still drives invalidation — edit the workbook and the entry
        // changes — but the owner's key keeps two identical workbooks apart.
        $hash = hash('sha256', implode('|', [
            hash_file('sha256', $xlsxPath),
            $cacheKey ?? '',
            // The two renderers produce different documents from the same
            // workbook, so switching must not serve the other one's output.
            $this->renderer(),
        ])) . ($forceA4 ? '-a4' : '-native');

        return storage_path('app/pdf-cache' . DIRECTORY_SEPARATOR . $hash . '.pdf');
    }

    private function makeWorkspace(): string
    {
        $path = storage_path('app/tmp-convert' . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)));
        mkdir($path, 0775, true);

        return $path;
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}
