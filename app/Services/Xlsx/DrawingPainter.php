<?php

namespace App\Services\Xlsx;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing as SheetDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Everything on the sheet that is not a cell.
 *
 * The campus forms are not only grids. CS Form No. 6 carries the college seal,
 * a stamp box, the rules under each signature and the names printed beneath
 * them, and none of that lives in a cell — it is anchored over the grid. A
 * renderer that draws only cells produces a form with no logo, no signature
 * blocks and no stamp box, which is not the form.
 *
 * Anchors are given as a cell plus an offset in EMU (English Metric Units,
 * 914400 to the inch), so they are converted against the same column and row
 * offset tables the cells were placed with. That is what keeps a drawing
 * sitting where it sits in Excel once the sheet is scaled to fit the page.
 */
class DrawingPainter
{
    /** English Metric Units per point. */
    private const EMU_PER_POINT = 12700;

    /**
     * @param  array  $cols  Column offset table from SheetPainter.
     * @param  array  $rows  Row offset table from SheetPainter.
     */
    public function paint(Worksheet $sheet, array $cols, array $rows, float $scale): string
    {
        $out = '';

        foreach ($sheet->getDrawingCollection() as $drawing) {
            if (! $drawing instanceof SheetDrawing && ! $drawing instanceof MemoryDrawing) {
                continue;
            }

            if ($src = $this->imageData($drawing)) {
                $out .= $this->place($drawing, $src, $cols, $rows, $scale);
            }
        }

        return $out;
    }

    /**
     * The image as a data URI.
     *
     * Embedded rather than linked because Dompdf resolves a path against the
     * filesystem, and these images live inside the .xlsx package.
     */
    private function imageData(SheetDrawing|MemoryDrawing $drawing): ?string
    {
        try {
            if ($drawing instanceof MemoryDrawing) {
                ob_start();
                imagepng($drawing->getImageResource());
                $binary = ob_get_clean();

                return 'data:image/png;base64,' . base64_encode($binary);
            }

            $path = $drawing->getPath();

            // A drawing read out of the package exposes itself through a
            // zip:// stream rather than a real file.
            $binary = @file_get_contents($path);

            if ($binary === false || $binary === '') {
                return null;
            }

            $mime = match (strtolower($drawing->getExtension())) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                // Windows metafiles cannot be rasterised in pure PHP, and a
                // broken <img> is worse than none.
                default => null,
            };

            if (! $mime) {
                return null;
            }

            return "data:{$mime};base64," . base64_encode($binary);
        } catch (\Throwable) {
            return null;
        }
    }

    private function place(
        SheetDrawing|MemoryDrawing $drawing,
        string $src,
        array $cols,
        array $rows,
        float $scale,
    ): string {
        [$col, $row] = Coordinate::coordinateFromString($drawing->getCoordinates());
        $row = (int) $row;

        if (! isset($cols['left'][$col], $rows['top'][$row])) {
            return '';
        }

        $left = $cols['left'][$col] + $drawing->getOffsetX() * 0.75;
        $top = $rows['top'][$row] + $drawing->getOffsetY() * 0.75;

        $width = $drawing->getWidth() * 0.75;
        $height = $drawing->getHeight() * 0.75;

        if ($width <= 0 || $height <= 0) {
            return '';
        }

        return sprintf(
            '<img src="%s" style="position:absolute;left:%.2fpt;top:%.2fpt;width:%.2fpt;height:%.2fpt;">',
            $src,
            $left * $scale,
            $top * $scale,
            $width * $scale,
            $height * $scale,
        );
    }
}
