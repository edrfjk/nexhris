<?php

namespace App\Services;

use ZipArchive;

class PdsCheckboxService
{
    /**
     * @param string $filePath   Path to the already-saved xlsx (after PdsSpreadsheetExportService)
     * @param array  $labelChecks e.g. ['Male' => true, 'Single' => true, 'Filipino' => true]
     *                            — checkboxes on sheet C1 matched by their exact visible label
     * @param array  $yesNoChecks e.g. [6 => false, 8 => false, 13 => true, ...]
     *                            — keyed by the question's Excel row on sheet C4,
     *                              value is true for YES / false for NO
     */
    public function apply(string $filePath, array $labelChecks = [], array $yesNoChecks = []): void
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException("Unable to open {$filePath} for checkbox post-processing.");
        }

        $vml1 = $zip->getFromName('xl/drawings/vmlDrawing1.vml');
        if ($vml1 !== false) {
            foreach ($labelChecks as $label => $checked) {
                if ($checked) {
                    $vml1 = $this->checkByLabel($vml1, (string) $label);
                }
            }
            $zip->addFromString('xl/drawings/vmlDrawing1.vml', $vml1);
        }

        $vml2 = $zip->getFromName('xl/drawings/vmlDrawing2.vml');
        if ($vml2 !== false) {
            foreach ($yesNoChecks as $targetRow => $answerYes) {
                if ($answerYes === null) {
                    continue;
                }
                $vml2 = $this->checkNearestYesNo($vml2, (int) $targetRow, (bool) $answerYes);
            }
            $zip->addFromString('xl/drawings/vmlDrawing2.vml', $vml2);
        }

        $zip->close();
    }

    private function getShapes(string $vml): array
    {
        preg_match_all('/<v:shape\b.*?<\/v:shape>/s', $vml, $m);
        return $m[0];
    }

    private function shapeLabel(string $shape): ?string
    {
        if (!preg_match('/<div[^>]*>(.*?)<\/div>/s', $shape, $lm)) {
            return null;
        }

        return trim(preg_replace('/\s+/', ' ', strip_tags($lm[1])));
    }

    private function checkByLabel(string $vml, string $label): string
    {
        return preg_replace_callback('/<v:shape\b.*?<\/v:shape>/s', function ($m) use ($label) {
            $shape = $m[0];

            if (stripos($shape, 'ObjectType="Checkbox"') === false) {
                return $shape;
            }

            $shapeLabel = $this->shapeLabel($shape);
            if ($shapeLabel === null || strcasecmp($shapeLabel, $label) !== 0) {
                return $shape;
            }

            return $this->markChecked($shape);
        }, $vml);
    }

    /**
     * The Yes/No checkboxes on the questionnaire page all share the same
     * two labels ("YES" / "NO"), so we identify the right pair by finding
     * the checkbox whose vertical position on the sheet sits closest to
     * the target question's row.
     */
    private function checkNearestYesNo(string $vml, int $targetRow, bool $answerYes): string
    {
        $shapes = $this->getShapes($vml);
        $wanted = $answerYes ? 'YES' : 'NO';
        $bestIndex = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($shapes as $i => $shape) {
            if (stripos($shape, 'ObjectType="Checkbox"') === false) {
                continue;
            }

            $label = $this->shapeLabel($shape);
            if ($label === null || strcasecmp($label, $wanted) !== 0) {
                continue;
            }

            if (!preg_match('/<x:Anchor>\d+,\d+,(\d+),/', $shape, $am)) {
                continue;
            }

            $shapeRow = (int) $am[1] + 1; // VML rows are 0-indexed
            $distance = abs($shapeRow - $targetRow);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestIndex = $i;
            }
        }

        if ($bestIndex === null) {
            return $vml;
        }

        $shapes[$bestIndex] = $this->markChecked($shapes[$bestIndex]);

        $i = 0;
        return preg_replace_callback('/<v:shape\b.*?<\/v:shape>/s', function () use (&$i, $shapes) {
            return $shapes[$i++];
        }, $vml);
    }

    private function markChecked(string $shape): string
    {
        if (stripos($shape, '<x:Checked>') !== false) {
            return preg_replace('/<x:Checked>\d<\/x:Checked>/', '<x:Checked>1</x:Checked>', $shape);
        }

        return str_replace('</x:ClientData>', '<x:Checked>1</x:Checked></x:ClientData>', $shape);
    }
}