<?php

namespace App\Services\LeaveForm;

use App\Services\Xlsx\TemplateFiller;

/**
 * Corrects the known printing faults of the campus CS Form No. 6.
 *
 * The workbook HR uses was drawn by eye in Excel and prints wrong:
 *
 *  - Items 1 to 5 were boxed cell by cell, so a ruled line ran through
 *    "OFFICE/DEPARTMENT", "(Last)" and "DATE OF FILING", and the line under
 *    the name broke into dashes.
 *  - A stray rule split "(Specify Illness)" under Special Leave Benefits.
 *  - Every answer text box is painted white. Where one sits on a ruled line
 *    — "As of", the three lines under 7.D — it rubbed the line out.
 *  - Page 2, the instructions, is an embedded Word document whose "*" note
 *    floated over the top of the text, and whose picture cut off the text of
 *    item 15. The corrected document and its picture ship with the system.
 *
 * Every repair checks before it changes anything, so running this over a
 * workbook already repaired — or a different form altogether — leaves it as
 * it was.
 */
class CsForm6Repairs
{
    /** The corrected instructions page, as a Word document and as its picture. */
    public const INSTRUCTIONS_DOCX = 'templates/cs-form-6/instructions.docx';

    public const INSTRUCTIONS_PICTURE = 'templates/cs-form-6/instructions.png';

    private const PICTURE_PART = 'xl/media/cs-form-6-instructions.png';

    /**
     * Repairs $path in place. Returns what was repaired, empty when nothing
     * needed it (or the workbook is not CS Form No. 6).
     *
     * @return list<string>
     */
    public function repair(string $path): array
    {
        $work = $path . '.repair-' . bin2hex(random_bytes(4)) . '.xlsx';

        try {
            $filler = new TemplateFiller($path, $work);
        } catch (\Throwable) {
            @unlink($work);

            return []; // Not an .xlsx package; nothing this can do.
        }

        if (! CsForm6::matches($filler)) {
            $filler->save();
            @unlink($work);

            return [];
        }

        [$form, $instructions] = CsForm6::sheets($filler);

        $done = array_merge(
            $this->itemsOneToFive($filler, $form),
            $this->specifyIllnessRule($filler, $form),
            $this->seeThroughAnswerBoxes($filler, $form),
            $instructions ? $this->instructionsPage($filler, $instructions) : [],
        );

        $filler->save();

        try {
            if ($done && ! @copy($work, $path)) {
                throw new \RuntimeException("The repaired form could not be written back to {$path}.");
            }
        } finally {
            @unlink($work);
        }

        return $done;
    }

    /**
     * Two boxes side by side — office | name — over the answer row, and the
     * filing date, position and salary on one ruled line beneath.
     */
    private function itemsOneToFive(TemplateFiller $f, string $sheet): array
    {
        // Already done: the label row is merged across the office box.
        if ($this->isMerged($f, $sheet, 'A4:D4')) {
            return [];
        }

        $thin = 'thin';
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

        foreach ($columns as $column) {
            $left = in_array($column, ['A', 'E'], true) ? $thin : null;
            $right = in_array($column, ['D', 'I'], true) ? $thin : null;

            // Labels: top rule, the two outer edges and the divider.
            $f->setBorders($sheet, "{$column}4", ['left' => $left, 'right' => $right, 'top' => $thin, 'bottom' => null]);
            // Answers: the same box, closed underneath.
            $f->setBorders($sheet, "{$column}5", ['left' => $left, 'right' => $right, 'top' => null, 'bottom' => $thin]);
            // Date of filing, position, salary: one line, no divider.
            $f->setBorders($sheet, "{$column}6", [
                'left' => $column === 'A' ? $thin : null,
                'right' => $column === 'I' ? $thin : null,
                'top' => $thin,
                'bottom' => $thin,
            ]);
        }

        foreach (['A4:D4', 'E4:I4', 'A5:D5', 'A6:D6', 'E6:I6'] as $range) {
            $f->mergeCells($sheet, $range);
        }

        return ['items 1 to 5 re-ruled'];
    }

    private function specifyIllnessRule(TemplateFiller $f, string $sheet): array
    {
        if (! $this->hasBorder($f, $sheet, 'H27', 'right')) {
            return [];
        }

        $f->setBorders($sheet, 'H27', ['right' => null]);

        return ['stray rule beside "(Specify Illness)" removed'];
    }

    private function seeThroughAnswerBoxes(TemplateFiller $f, string $sheet): array
    {
        $changed = false;

        foreach (CsForm6::BOXES as $box) {
            if ($this->shapeIsFilled($f, $sheet, $box)) {
                $f->setShapeFill($sheet, $box, null);
                $changed = true;
            }
        }

        return $changed ? ['answer boxes made see-through'] : [];
    }

    /**
     * Swaps the embedded instructions document, and the picture that stands
     * in for it on the sheet, for the corrected pair.
     */
    private function instructionsPage(TemplateFiller $f, string $sheet): array
    {
        $sheetPart = $f->sheetPart($sheet);
        $relations = $f->partRelationships($sheetPart);

        $document = $this->firstOfType($relations, '/package');
        $picture = $this->firstOfType($relations, '/image');

        if (! $document || ! $picture || ! $this->isDefectiveInstructions($f->readPart($document))) {
            return [];
        }

        $f->writePart($document, (string) file_get_contents(resource_path(self::INSTRUCTIONS_DOCX)));
        $f->writePart(self::PICTURE_PART, (string) file_get_contents(resource_path(self::INSTRUCTIONS_PICTURE)));

        // Point the sheet's preview, and the VML shape Excel draws it with,
        // at the new picture.
        $this->retarget($f, $sheetPart, $picture);

        $vml = $this->firstOfType($relations, '/vmlDrawing');
        if ($vml) {
            $this->retarget($f, $vml, $picture);
        }

        if ($picture !== self::PICTURE_PART) {
            $f->removePart($picture);
        }

        // The picture reaches into row 64 and column L; the page's print area
        // stopped at K62, which cut the "*" note off the foot of the page.
        $workbook = $f->readPart('xl/workbook.xml');
        $index = array_search($sheet, $f->sheetNames(), true);
        $workbook = preg_replace_callback(
            '#(<definedName name="_xlnm\.Print_Area" localSheetId="' . $index . '">[^<]*!)\$A\$1:\$[A-Z]+\$\d+(</definedName>)#',
            fn ($m) => $m[1] . '$A$1:$L$64' . $m[2],
            $workbook,
        );
        $f->writePart('xl/workbook.xml', $workbook);

        // …and printed it at a fixed 81%, which no longer fits that area on
        // one sheet. Fit it to the page instead, between even margins.
        $xml = $f->readPart($sheetPart);
        $xml = preg_match('#<pageSetUpPr\b#', $xml)
            ? preg_replace('#<pageSetUpPr\b[^>]*/>#', '<pageSetUpPr fitToPage="1"/>', $xml)
            : (preg_match('#<sheetPr\b[^>]*/>#', $xml)
                ? preg_replace('#<sheetPr\b([^>]*)/>#', '<sheetPr$1><pageSetUpPr fitToPage="1"/></sheetPr>', $xml, 1)
                : preg_replace('#(<sheetPr\b[^>]*>)#', '$1<pageSetUpPr fitToPage="1"/>', $xml, 1));
        $xml = preg_replace_callback('#<pageSetup\b([^>]*?)(/?)>#', fn ($m) => '<pageSetup'
            . preg_replace('#\s+(scale|fitToWidth|fitToHeight)="[^"]*"#', '', $m[1])
            . ' fitToWidth="1" fitToHeight="1"' . $m[2] . '>', $xml);
        $xml = preg_replace('#<pageMargins\b[^>]*/>#',
            '<pageMargins left="0.4" right="0.4" top="0.4" bottom="0.4" header="0" footer="0"/>', $xml);
        $f->writePart($sheetPart, $xml);

        $types = $f->readPart('[Content_Types].xml');
        if (! preg_match('/<Default\b[^>]*Extension="png"/i', $types)) {
            $f->writePart('[Content_Types].xml', str_replace(
                '<Default ',
                '<Default Extension="png" ContentType="image/png"/><Default ',
                $types,
            ));
        }

        return ['instructions page replaced with the corrected copy'];
    }

    /** The shipped instructions document had its "*" note floating over the text. */
    private function isDefectiveInstructions(string $docx): bool
    {
        $temp = tempnam(sys_get_temp_dir(), 'cs6');
        file_put_contents($temp, $docx);

        $zip = new \ZipArchive();
        $xml = $zip->open($temp) === true ? (string) $zip->getFromName('word/document.xml') : '';
        $zip->close();
        @unlink($temp);

        return (bool) preg_match(
            '#<wp:positionV relativeFrom="paragraph">(?:(?!</w:drawing>).)*?For leave of absence for thirty#s',
            $xml,
        );
    }

    private function retarget(TemplateFiller $f, string $ownerPart, string $oldTarget): void
    {
        $relsPath = dirname($ownerPart) . '/_rels/' . basename($ownerPart) . '.rels';
        $rels = $f->readPart($relsPath);

        $relative = $this->relativePath(dirname($ownerPart), $oldTarget);
        $replacement = $this->relativePath(dirname($ownerPart), self::PICTURE_PART);

        $updated = str_replace(
            ['Target="' . $relative . '"', 'Target="/' . $oldTarget . '"'],
            'Target="' . $replacement . '"',
            $rels,
        );

        if ($updated !== $rels) {
            $f->writePart($relsPath, $updated);
        }
    }

    private function relativePath(string $fromDirectory, string $target): string
    {
        $from = explode('/', trim($fromDirectory, '/'));
        $to = explode('/', trim($target, '/'));

        while ($from && $to && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return str_repeat('../', count($from)) . implode('/', $to);
    }

    private function firstOfType(array $relations, string $suffix): ?string
    {
        foreach ($relations as $relation) {
            if (str_ends_with($relation['type'], $suffix)) {
                return $relation['path'];
            }
        }

        return null;
    }

    private function isMerged(TemplateFiller $f, string $sheet, string $range): bool
    {
        return in_array($range, $this->mergedRanges($f, $sheet), true);
    }

    private function mergedRanges(TemplateFiller $f, string $sheet): array
    {
        preg_match_all('/<mergeCell ref="([^"]+)"/', $f->sheetXml($sheet), $m);

        return $m[1];
    }

    private function hasBorder(TemplateFiller $f, string $sheet, string $cell, string $side): bool
    {
        return $f->borderStyle($sheet, $cell, $side) !== null;
    }

    private function shapeIsFilled(TemplateFiller $f, string $sheet, string $box): bool
    {
        return $f->hasShape($sheet, $box) && $f->shapeFill($sheet, $box) !== null;
    }
}
