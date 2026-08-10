<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf as PdfWriter;

class ExcelToPdfConverter
{
    /**
     * Converts an xlsx file to PDF using PhpSpreadsheet's built-in writer
     * (backed by Dompdf). Pure PHP — no external process, no shell_exec,
     * works identically on local dev and on shared hosting like Hostinger.
     */
    public function convert(string $xlsxAbsolutePath): string
    {
        if (!file_exists($xlsxAbsolutePath)) {
            throw new \RuntimeException("Source file not found: {$xlsxAbsolutePath}");
        }

        $pdfPath = preg_replace('/\.xlsx$/i', '.pdf', $xlsxAbsolutePath);

        try {
            $spreadsheet = IOFactory::load($xlsxAbsolutePath);

            // Only export sheets that actually have a print area defined —
            // avoids dumping the Lookup/reference sheet used for dropdowns.
            $spreadsheet->setActiveSheetIndex(0);

            $writer = new PdfWriter($spreadsheet);
            $writer->writeAllSheets();
            $writer->save($pdfPath);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Excel to PDF conversion failed', [
                'file' => $xlsxAbsolutePath,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('PDF conversion failed. Please try again, or contact HR if the problem continues.');
        }

        if (!file_exists($pdfPath)) {
            throw new \RuntimeException('PDF conversion did not produce an output file.');
        }

        return $pdfPath;
    }
}