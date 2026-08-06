<?php

namespace App\Services;

use ZipArchive;

class PdsCheckboxService
{
    public function apply(string $filePath, array $labelChecks = [], array $yesNoChecks = []): void
    {
        $zip = new ZipArchive();
        $openResult = $zip->open($filePath);
        if ($openResult !== true) {
            throw new \RuntimeException("Unable to open {$filePath} for checkbox post-processing. ZipArchive error code: {$openResult}");
        }

        $modified = false;

        $vml1 = $zip->getFromName('xl/drawings/vmlDrawing1.vml');
        if ($vml1 === false) {
            $zip->close();
            throw new \RuntimeException('vmlDrawing1.vml not found inside the generated xlsx.');
        }

        $originalVml1 = $vml1;
        foreach ($labelChecks as $label => $checked) {
            if ($checked) {
                $vml1 = $this->checkByLabel($vml1, (string) $label);
            }
        }
        if ($vml1 !== $originalVml1) {
            if (!$zip->addFromString('xl/drawings/vmlDrawing1.vml', $vml1)) {
                $zip->close();
                throw new \RuntimeException('Failed to write updated vmlDrawing1.vml back into the xlsx.');
            }
            $modified = true;
        }

        $vml2 = $zip->getFromName('xl/drawings/vmlDrawing2.vml');
        if ($vml2 !== false) {
            $originalVml2 = $vml2;
            foreach ($yesNoChecks as $targetRow => $answerYes) {
                if ($answerYes === null) {
                    continue;
                }
                $vml2 = $this->checkNearestYesNo($vml2, (int) $targetRow, (bool) $answerYes);
            }
            if ($vml2 !== $originalVml2) {
                if (!$zip->addFromString('xl/drawings/vmlDrawing2.vml', $vml2)) {
                    $zip->close();
                    throw new \RuntimeException('Failed to write updated vmlDrawing2.vml back into the xlsx.');
                }
                $modified = true;
            }
        }

        if (!$zip->close()) {
            throw new \RuntimeException('Failed to finalize the xlsx after checkbox post-processing.');
        }

        if (!$modified) {
            \Illuminate\Support\Facades\Log::warning('PDS checkbox step ran but matched nothing — labelChecks/yesNoChecks may be empty or labels did not match any shape.', [
                'labelChecks' => $labelChecks,
                'yesNoChecks' => $yesNoChecks,
            ]);
        }
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

        $label = strip_tags($lm[1]);

        // Strip UTF-8 non-breaking spaces (0xC2 0xA0) that the template embeds
        // before each checkbox caption — plain trim()/preg_replace('/\s+/')
        // don't recognize these as whitespace since they're multi-byte.
        $label = str_replace("\xC2\xA0", ' ', $label);
        $label = str_replace("\xA0", ' ', $label);
        $label = html_entity_decode($label, ENT_QUOTES, 'UTF-8');
        $label = preg_replace('/\s+/u', ' ', $label);

        return trim($label);
    }

    private function checkByLabel(string $vml, string $label): string
    {
        $matchedAny = false;

        $result = preg_replace_callback('/<v:shape\b.*?<\/v:shape>/s', function ($m) use ($label, &$matchedAny) {
            $shape = $m[0];

            if (stripos($shape, 'ObjectType="Checkbox"') === false) {
                return $shape;
            }

            $shapeLabel = $this->shapeLabel($shape);

            \Illuminate\Support\Facades\Log::info('Checkbox candidate', [
                'wanted' => $label,
                'found' => $shapeLabel,
            ]);

            if ($shapeLabel === null || strcasecmp($shapeLabel, $label) !== 0) {
                return $shape;
            }

            $matchedAny = true;
            return $this->markChecked($shape);
        }, $vml);

        if (!$matchedAny) {
            \Illuminate\Support\Facades\Log::warning("checkByLabel found NO match for label: {$label}");
        }

        return $result;
    }

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

            $shapeRow = (int) $am[1] + 1;
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