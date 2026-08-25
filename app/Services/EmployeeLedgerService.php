<?php

namespace App\Services;

use App\Models\EmployeeLedger;
use App\Models\LedgerChange;
use App\Models\LedgerTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Each employee's leave ledger workbook.
 *
 * The master template is seeded once. The first time anyone opens an
 * employee's ledger the master is copied to ledger_{employee_id}.xlsx, and
 * every edit from then on writes real cells in that copy — so merged cells,
 * column widths and print areas are preserved exactly.
 *
 * Ledgers live on the private disk: they are payroll records, and the public
 * disk is directly reachable over HTTP.
 */
class EmployeeLedgerService
{
    private const DIRECTORY = 'ledgers';

    public function __construct(
        private XlsxToPdfService $converter,
        private ActivityLogger $log,
    ) {
    }

    /**
     * The employee's ledger, copied from the master the first time it is asked
     * for. Throws when HR has not seeded a master yet.
     */
    public function forEmployee(User $employee): EmployeeLedger
    {
        $ledger = EmployeeLedger::where('user_id', $employee->id)->first();

        if ($ledger && $ledger->exists()) {
            return $ledger;
        }

        $master = LedgerTemplate::active();

        if (! $master || ! $master->exists()) {
            throw new \RuntimeException(
                'No master ledger template has been seeded yet. '
                . 'Upload one under Templates before opening an employee ledger.'
            );
        }

        return DB::transaction(function () use ($employee, $master, $ledger) {
            $path = self::DIRECTORY . '/ledger_' . $employee->id . '.xlsx';

            Storage::disk('local')->put(
                $path,
                Storage::disk('public')->get($master->file_path)
            );

            // The supplied template is a filled-in specimen: it carries a real
            // employee's name and years of their leave history. Copying it
            // verbatim would hand every employee that person's record, so the
            // data is stripped and this employee's own identity stamped in.
            // Only the values go — merges, column widths, borders and the
            // printed headings are exactly what makes it the official form.
            $this->blankForEmployee(
                Storage::disk('local')->path($path),
                $employee,
            );

            $attributes = [
                'ledger_template_id' => $master->id,
                'file_path' => $path,
                'template_version' => $master->version,
            ];

            if ($ledger) {
                // The row survived but its file did not — re-seed in place.
                $ledger->update($attributes);
            } else {
                $ledger = EmployeeLedger::create($attributes + ['user_id' => $employee->id]);
            }

            $this->log->log(
                'ledger.created',
                "Created a ledger for {$employee->name} from master template v{$master->version}.",
                $ledger,
                ['template_version' => $master->version],
            );

            return $ledger;
        });
    }



    /**
     * Re-copies the master over an existing ledger, blanking it again.
     *
     * Needed for ledgers created before the specimen data was being stripped,
     * and whenever HR wants to start an employee's card over. Destructive by
     * definition, so the caller confirms first and the change is logged.
     */
    public function reset(EmployeeLedger $ledger, User $actor): EmployeeLedger
    {
        $master = LedgerTemplate::active();

        if (! $master || ! $master->exists()) {
            throw new \RuntimeException('No master ledger template is available to reset from.');
        }

        Storage::disk('local')->put(
            $ledger->file_path,
            Storage::disk('public')->get($master->file_path)
        );

        $this->blankForEmployee($ledger->absolutePath(), $ledger->user);

        $ledger->update([
            'ledger_template_id' => $master->id,
            'template_version' => $master->version,
            'last_edited_at' => now(),
            'last_edited_by' => $actor->id,
        ]);

        $this->log->log(
            'ledger.reset',
            "{$actor->name} reset {$ledger->user->name}'s ledger to a blank card.",
            $ledger,
            ['template_version' => $master->version],
            $actor,
        );

        return $ledger->refresh();
    }

    /**
     * Strips the specimen data out of a freshly copied ledger and writes the
     * employee's own identity into the header.
     *
     * The workbook repeats the same card block down the sheet (one printed
     * page each), so every block is located and cleared rather than just the
     * first.
     */
    private function blankForEmployee(string $path, User $employee): void
    {
        $spreadsheet = IOFactory::load($path);
        $parts = $employee->nameParts();

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $lastRow = $sheet->getHighestDataRow();
            $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
                $sheet->getHighestDataColumn()
            );

            // A card starts wherever the title appears in column A.
            $cardRows = [];
            for ($row = 1; $row <= $lastRow; $row++) {
                $value = (string) $sheet->getCell([1, $row])->getValue();
                if (str_contains(strtoupper($value), 'LEAVE LEDGER CARD')) {
                    $cardRows[] = $row;
                }
            }

            if ($cardRows === []) {
                $cardRows = [1];
            }

            foreach ($cardRows as $index => $cardTop) {
                // Data belongs to this card until the next one begins.
                $cardEnd = ($cardRows[$index + 1] ?? ($lastRow + 1)) - 1;

                $headerRow = $this->locateHeaderRow($sheet, $cardTop, $cardEnd);

                if ($headerRow === null) {
                    continue;
                }

                $this->stampIdentity($sheet, $cardTop, $headerRow, $parts, $employee);

                // Everything below the column headings is the specimen's
                // history. Clear values and formulas, keep the styling.
                for ($row = $headerRow + 1; $row <= $cardEnd; $row++) {
                    for ($col = 1; $col <= $lastCol; $col++) {
                        $cell = $sheet->getCell([$col, $row]);

                        if ($cell->getValue() !== null && $cell->getValue() !== '') {
                            $cell->setValue(null);
                        }
                    }
                }
            }
        }

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    /** The row carrying the FROM / TO / REMARKS column headings. */
    private function locateHeaderRow($sheet, int $from, int $to): ?int
    {
        for ($row = $from; $row <= min($to, $from + 12); $row++) {
            $a = strtoupper(trim((string) $sheet->getCell([1, $row])->getValue()));
            $b = strtoupper(trim((string) $sheet->getCell([2, $row])->getValue()));

            if ($a === 'FROM' && $b === 'TO') {
                return $row;
            }
        }

        return null;
    }

    /**
     * Replaces the specimen's name and office with this employee's, in the
     * identification block between the card title and the column headings.
     */
    private function stampIdentity($sheet, int $cardTop, int $headerRow, array $parts, User $employee): void
    {
        for ($row = $cardTop; $row < $headerRow; $row++) {
            $label = strtoupper(trim((string) $sheet->getCell([1, $row])->getValue()));

            if (str_starts_with($label, 'NAME')) {
                // Columns B, D and F hold family / first / middle initial,
                // captioned as such on the row beneath.
                $sheet->setCellValue([2, $row], $parts['family']);
                $sheet->setCellValue([4, $row], $parts['first']);
                $sheet->setCellValue([6, $row], $parts['middle']);

                // OFFICE sits on the same row, to the right.
                $sheet->setCellValue([8, $row], $employee->college->name ?? $employee->department ?? '');
                continue;
            }

            if (str_contains($label, 'FIRST DAY')) {
                $date = $employee->first_day_of_service ?? $employee->date_hired;
                $sheet->setCellValue([4, $row], $date?->format('F j, Y') ?? '');
            }
        }
    }

    /**
     * Reads a sheet back as rows of cell => value, for the editing screen.
     *
     * @return array{sheets: array<string>, sheet: string, cells: array<string, array{value: mixed, formatted: string}>, highestRow: int, highestColumn: string}
     */
    public function readSheet(EmployeeLedger $ledger, ?string $sheetName = null, int $maxRows = 80): array
    {
        $spreadsheet = IOFactory::load($ledger->absolutePath());

        $sheetNames = $spreadsheet->getSheetNames();
        $sheet = $sheetName && in_array($sheetName, $sheetNames, true)
            ? $spreadsheet->getSheetByName($sheetName)
            : $spreadsheet->getSheet(0);

        $highestRow = min($sheet->getHighestDataRow(), $maxRows);
        $highestColumn = $sheet->getHighestDataColumn();
        $highestIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        $cells = [];

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= $highestIndex; $col++) {
                $ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
                $cell = $sheet->getCell($ref);
                $value = $cell->getValue();

                if ($value === null || $value === '') {
                    continue;
                }

                $cells[$ref] = [
                    'value' => is_scalar($value) ? $value : (string) $value,
                    'formatted' => (string) $cell->getFormattedValue(),
                ];
            }
        }

        $result = [
            'sheets' => $sheetNames,
            'sheet' => $sheet->getTitle(),
            'cells' => $cells,
            'highestRow' => $highestRow,
            'highestColumn' => $highestColumn,
            'merges' => array_keys($sheet->getMergeCells()),
        ];

        $spreadsheet->disconnectWorksheets();

        return $result;
    }

    /**
     * Writes edited cells back into the real workbook and records each change.
     *
     * @param  array<string, string|null>  $cells  cell reference => new value
     * @return int  number of cells that actually changed
     */
    public function updateCells(EmployeeLedger $ledger, string $sheetName, array $cells, User $editor): int
    {
        $spreadsheet = IOFactory::load($ledger->absolutePath());
        $sheet = $spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getSheet(0);

        $changed = [];

        foreach ($cells as $ref => $new) {
            if (! preg_match('/^[A-Z]{1,3}[1-9][0-9]{0,6}$/', $ref)) {
                continue;
            }

            $cell = $sheet->getCell($ref);
            $old = $cell->getValue();

            $newValue = ($new === '' || $new === null) ? null : $new;
            $oldCompare = ($old === '' || $old === null) ? null : $old;

            // Compare loosely: "3" from a form field equals 3 in the workbook.
            if ((string) $oldCompare === (string) $newValue) {
                continue;
            }

            // Numeric input is stored as a number so the workbook's own
            // formulas keep working.
            if ($newValue !== null && is_numeric($newValue)) {
                $sheet->setCellValue($ref, $newValue + 0);
            } else {
                $sheet->setCellValue($ref, $newValue);
            }

            $changed[] = [
                'cell' => $ref,
                'old' => $oldCompare === null ? null : (string) $oldCompare,
                'new' => $newValue === null ? null : (string) $newValue,
            ];
        }

        if ($changed === []) {
            $spreadsheet->disconnectWorksheets();

            return 0;
        }

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($ledger->absolutePath());
        $spreadsheet->disconnectWorksheets();

        DB::transaction(function () use ($ledger, $sheetName, $changed, $editor) {
            foreach ($changed as $change) {
                LedgerChange::create([
                    'employee_ledger_id' => $ledger->id,
                    'sheet' => $sheetName,
                    'cell' => $change['cell'],
                    'old_value' => $change['old'],
                    'new_value' => $change['new'],
                    'changed_by' => $editor->id,
                ]);
            }

            $ledger->update([
                'last_edited_at' => now(),
                'last_edited_by' => $editor->id,
            ]);
        });

        $this->log->log(
            'ledger.updated',
            "{$editor->name} edited " . count($changed) . " cell(s) on {$ledger->user->name}'s ledger.",
            $ledger,
            ['sheet' => $sheetName, 'changes' => $changed],
            $editor,
        );

        return count($changed);
    }

    /** The ledger as a PDF, converted from the real workbook. */
    public function pdfPath(EmployeeLedger $ledger): string
    {
        // Namespaced to the owner: a freshly seeded ledger is a byte-for-byte
        // copy of the master, so several people's cards hash the same until
        // HR writes something into them.
        return $this->converter->convert(
            $ledger->absolutePath(),
            cacheKey: 'ledger:' . $ledger->user_id,
        );
    }
}
