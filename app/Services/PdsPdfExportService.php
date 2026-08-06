<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use setasign\Fpdi\Fpdi;
use ZipArchive;

// setasign/fpdf 1.8 still checks the PHP 7-era magic-quotes function,
// which was removed in PHP 8.  FPDI otherwise works normally on PHP 8.
class PdsFpdi extends Fpdi
{
    protected function _dochecks()
    {
    }
}

/**
 * Generates a PDS by overlaying the employee's data on the official CSC PDF.
 * This needs only PHP at runtime; LibreOffice is never started by a request.
 */
class PdsPdfExportService
{
    private const SHEETS = ['C1', 'C2', 'C3', 'C4'];

    public function __construct(private PdsSpreadsheetExportService $spreadsheetExport)
    {
    }

    public function render(User $user): string
    {
        $xlsxTemplate = resource_path('templates/CS-Form-212-2026.xlsx');
        $pdfTemplate = resource_path('templates/CS-Form-212-2026.pdf');
        $blank = IOFactory::load($xlsxTemplate);
        $filled = $this->spreadsheetExport->fill($user);

        $pdf = new PdsFpdi('P', 'pt');
        $pageCount = $pdf->setSourceFile($pdfTemplate);
        if ($pageCount < count(self::SHEETS)) {
            throw new \RuntimeException('The official PDS PDF template must contain four pages.');
        }

        foreach (self::SHEETS as $page => $sheetName) {
            $templateId = $pdf->importPage($page + 1);
            $size = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            $this->renderChangedCells(
                $pdf,
                $blank->getSheetByName($sheetName),
                $filled->getSheetByName($sheetName),
                $size['width'],
                $size['height'],
            );
            $this->renderPageCheckboxes($pdf, $user, $blank->getSheetByName($sheetName), $sheetName, $xlsxTemplate, $size['width'], $size['height']);
            $this->renderPageImages($pdf, $user, $blank->getSheetByName($sheetName), $sheetName, $page + 1, $size['width'], $size['height']);
        }

        return $pdf->Output('S');
    }

    private function renderChangedCells(Fpdi $pdf, Worksheet $blank, Worksheet $filled, float $pageWidth, float $pageHeight): void
    {
        foreach ($filled->getCoordinates() as $coordinate) {
            $value = $filled->getCell($coordinate)->getValue();
            $templateValue = $blank->getCell($coordinate)->getValue();

            if ($value === null || $value === '' || (string) $value === (string) $templateValue) {
                continue;
            }

            [$x, $y, $width, $height] = $this->cellBox($blank, $coordinate, $pageWidth, $pageHeight);
            $text = $this->toPdfText((string) $value);
            $fontSize = strlen($text) > 55 ? 5.5 : 7;

            $pdf->SetFont('Arial', '', $fontSize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($x + 1, $y + 1);
            $pdf->Cell(max(1, $width - 2), max(1, $height - 2), $text, 0, 0, 'C');
        }
    }

    private function renderPageImages(Fpdi $pdf, User $user, Worksheet $sheet, string $sheetName, int $page, float $pageWidth, float $pageHeight): void
    {
        $declaration = $user->pdsDeclaration;
        if (!$declaration) {
            return;
        }

        $signaturePath = $declaration->signature_path && Storage::disk('public')->exists($declaration->signature_path)
            ? storage_path('app/public/' . $declaration->signature_path)
            : null;

        $signatureCells = ['C1' => 'D60', 'C2' => 'D47', 'C3' => 'C50', 'C4' => 'F60'];
        if ($signaturePath && isset($signatureCells[$sheetName])) {
            [$x, $y, $width, $height] = $this->cellBox($sheet, $signatureCells[$sheetName], $pageWidth, $pageHeight);
            $pdf->Image($signaturePath, $x + 2, $y + 1, max(20, $width - 4), max(12, $height - 2));
        }

        if ($page === 4 && $declaration->photo_path && Storage::disk('public')->exists($declaration->photo_path)) {
            [$x, $y, $width, $height] = $this->cellBox($sheet, 'K58', $pageWidth, $pageHeight);
            $pdf->Image(storage_path('app/public/' . $declaration->photo_path), $x + 2, $y + 2, max(20, $width - 4), max(20, $height - 4));
        }
    }

    private function renderPageCheckboxes(Fpdi $pdf, User $user, Worksheet $sheet, string $sheetName, string $xlsxTemplate, float $pageWidth, float $pageHeight): void
    {
        $checks = $sheetName === 'C1' ? $this->personalChecks($user) : ($sheetName === 'C4' ? $this->questionnaireChecks($user) : []);
        if ($checks === []) return;

        $zip = new ZipArchive();
        if ($zip->open($xlsxTemplate) !== true) return;
        $vml = $zip->getFromName($sheetName === 'C1' ? 'xl/drawings/vmlDrawing1.vml' : 'xl/drawings/vmlDrawing2.vml');
        $zip->close();
        if ($vml === false) return;

        preg_match_all('/<v:shape\b.*?<\/v:shape>/s', $vml, $shapes);

        foreach ($checks as $target => $answer) {
            if ($sheetName === 'C4' && $answer === null) {
                continue;
            }

            $candidate = null;
            $distance = PHP_INT_MAX;

            foreach ($shapes[0] as $shape) {
                if (stripos($shape, 'ObjectType="Checkbox"') === false) continue;
                if (!preg_match('/<div[^>]*>(.*?)<\/div>/s', $shape, $labelMatch)) continue;
                if (!preg_match('/<x:Anchor>(\d+),\d+,(\d+),/', $shape, $anchor)) continue;

                $label = strip_tags($labelMatch[1]);
                $label = str_replace(["\xC2\xA0", "\xA0"], ' ', $label);
                $label = trim(preg_replace('/\s+/u', ' ', $label));

                if ($sheetName === 'C1' && strcasecmp($label, (string) $target) === 0) {
                    $candidate = $anchor;
                    break;
                }

                if ($sheetName === 'C4' && strcasecmp($label, $answer ? 'YES' : 'NO') === 0) {
                    $row = (int) $anchor[2] + 1;
                    if (abs($row - (int) $target) < $distance) {
                        $candidate = $anchor;
                        $distance = abs($row - (int) $target);
                    }
                }
            }

            if (!$candidate) {
                continue;
            }

            $cell = Coordinate::stringFromColumnIndex((int) $candidate[1] + 1) . ((int) $candidate[2] + 1);
            [$x, $y, $width, $height] = $this->cellBox($sheet, $cell, $pageWidth, $pageHeight);

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetTextColor(0, 0, 0);
            // Center the mark within the checkbox's own cell height/width
            // instead of a fixed offset, so it lands correctly regardless
            // of that row's actual height.
            $pdf->Text($x + max(1, $width * 0.15), $y + ($height / 2) + 3, 'X');
        }
    }

    private function personalChecks(User $user): array
    {
        $personal = $user->pdsPersonalInformation;
        if (!$personal) return [];
        $checks = [];
        if ($personal->sex) $checks[$personal->sex] = true;
        $status = ['Single' => 'Single', 'Married' => 'Married', 'Widowed' => 'Widowed', 'Separated' => 'Separated', 'Solo Parent' => 'Other/s:', 'Others' => 'Other/s:'];
        if (isset($status[$personal->civil_status])) $checks[$status[$personal->civil_status]] = true;
        if ($personal->citizenship === 'Filipino') $checks['Filipino'] = true;
        return $checks;
    }

    private function questionnaireChecks(User $user): array
    {
        $q = $user->pdsQuestionnaire;
        if (!$q) return [];
        return [6 => $q->related_third_degree, 8 => $q->related_fourth_degree, 13 => $q->found_admin_guilty, 18 => $q->criminally_charged, 23 => $q->convicted_crime, 27 => $q->separated_from_service, 31 => $q->candidate_in_election, 34 => $q->resigned_before_election, 37 => $q->acquired_immigrant_status, 43 => $q->is_indigenous_group_member, 45 => $q->is_pwd, 47 => $q->is_solo_parent];
    }

    /** @return array{float, float, float, float} */
    private function cellBox(Worksheet $sheet, string $coordinate, float $pageWidth, float $pageHeight): array
    {
        [$column, $row] = Coordinate::coordinateFromString($coordinate);
        $columnIndex = Coordinate::columnIndexFromString($column);
        [$startColumn, $startRow, $endColumn, $endRow] = $this->printAreaBounds($sheet);

        foreach ($sheet->getMergeCells() as $range) {
            [[$mergeStartColumn, $mergeStartRow], [$mergeEndColumn, $mergeEndRow]] = Coordinate::rangeBoundaries($range);
            if ($columnIndex >= $mergeStartColumn && $columnIndex <= $mergeEndColumn && $row >= $mergeStartRow && $row <= $mergeEndRow) {
                $columnIndex = $mergeStartColumn;
                $row = $mergeStartRow;
                $endCellColumn = $mergeEndColumn;
                $endCellRow = $mergeEndRow;
                break;
            }
        }

        $endCellColumn ??= $columnIndex;
        $endCellRow ??= $row;
        $totalWidth = $this->columnUnits($sheet, $startColumn, $endColumn);
        $totalHeight = $this->rowUnits($sheet, $startRow, $endRow);
        $margins = $sheet->getPageMargins();
        $left = $margins->getLeft() * 72;
        $top = $margins->getTop() * 72;
        $printWidth = $pageWidth - $left - ($margins->getRight() * 72);
        $printHeight = $pageHeight - $top - ($margins->getBottom() * 72);

        $x = $left + ($this->columnUnits($sheet, $startColumn, $columnIndex - 1) / $totalWidth * $printWidth);
        $y = $top + ($this->rowUnits($sheet, $startRow, $row - 1) / $totalHeight * $printHeight);
        $width = $this->columnUnits($sheet, $columnIndex, $endCellColumn) / $totalWidth * $printWidth;
        $height = $this->rowUnits($sheet, $row, $endCellRow) / $totalHeight * $printHeight;

        return [$x, $y, $width, $height];
    }

    private function printAreaBounds(Worksheet $sheet): array
    {
        $area = str_replace('$', '', explode(',', $sheet->getPageSetup()->getPrintArea())[0]);
        [$start, $end] = explode(':', $area);
        [$startColumn, $startRow] = Coordinate::coordinateFromString($start);
        [$endColumn, $endRow] = Coordinate::coordinateFromString($end);

        return [Coordinate::columnIndexFromString($startColumn), $startRow, Coordinate::columnIndexFromString($endColumn), $endRow];
    }

    private function columnUnits(Worksheet $sheet, int $from, int $to): float
    {
        if ($to < $from) return 0;
        $total = 0;
        for ($column = $from; $column <= $to; $column++) {
            $width = $sheet->getColumnDimensionByColumn($column)->getWidth();
            $total += $width > 0 ? $width : $sheet->getDefaultColumnDimension()->getWidth();
        }
        return $total;
    }

    private function rowUnits(Worksheet $sheet, int $from, int $to): float
    {
        if ($to < $from) return 0;
        $total = 0;
        for ($row = $from; $row <= $to; $row++) {
            $height = $sheet->getRowDimension($row)->getRowHeight();
            $total += $height > 0 ? $height : $sheet->getDefaultRowDimension()->getRowHeight();
        }
        return $total;
    }

    private function toPdfText(string $text): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $text) ?: '';
    }
}
