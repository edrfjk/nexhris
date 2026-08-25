<?php

namespace App\Services;

use App\Models\LedgerTemplate;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/**
 * The pieces of the ledger template that the printed card needs but cannot
 * express in HTML — at present, the campus seal sitting in the corner of the
 * form.
 *
 * Pulled out of the active template rather than kept as a separate file, so
 * publishing a new ledger template brings its logo with it.
 */
class LedgerCardAssets
{
    private const CACHE_DIRECTORY = 'ledger-assets';

    /**
     * An absolute path to the logo drawn on the active ledger template, or
     * null when the template carries none.
     */
    public function logoPath(): ?string
    {
        $template = LedgerTemplate::active();

        if (! $template || ! $template->exists()) {
            // The ledger form is fixed, so its seal ships with the app and
            // does not depend on a template record still being around.
            return $this->bundledSeal();
        }

        $cached = storage_path(
            'app/' . self::CACHE_DIRECTORY . '/logo-v' . $template->version . '.png'
        );

        if (is_file($cached)) {
            return $cached;
        }

        try {
            $extracted = $this->extractFirstDrawing($template->absolutePath());

            if ($extracted === null) {
                return $this->bundledSeal();
            }

            if (! is_dir(dirname($cached))) {
                mkdir(dirname($cached), 0775, true);
            }

            file_put_contents($cached, $this->fitForPrint($extracted));

            return $cached;
        } catch (\Throwable $e) {
            // A card without its seal still prints; a card that throws does not.
            Log::warning('Could not read the ledger template logo.', [
                'template' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return $this->bundledSeal();
        }
    }

    /** The seal that ships with the app, used when no template supplies one. */
    private function bundledSeal(): ?string
    {
        $path = resource_path('images/ledger-seal.png');

        return is_file($path) ? $path : null;
    }

    /** The logo as a data URI, which is what Dompdf embeds most reliably. */
    public function logoDataUri(): ?string
    {
        $path = $this->logoPath();

        if ($path === null) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }

    /**
     * Shrinks the seal to a sensible print size.
     *
     * The template carries it at full resolution — 4096 square in the current
     * file — while drawing it about 64 points wide. Embedding the original
     * puts a couple of hundred kilobytes into every card for no visible gain,
     * so it is resampled to a size that is still sharp at 300dpi.
     */
    private function fitForPrint(string $png): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $png;
        }

        $source = @imagecreatefromstring($png);

        if ($source === false) {
            return $png;
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);
            $target = 320;

            if ($width <= $target && $height <= $target) {
                return $png;
            }

            $scale = $target / max($width, $height);
            $resized = imagecreatetruecolor((int) round($width * $scale), (int) round($height * $scale));

            // The seal is transparent behind the crest; flattening it onto
            // white would box it in.
            imagealphablending($resized, false);
            imagesavealpha($resized, true);

            imagecopyresampled(
                $resized, $source,
                0, 0, 0, 0,
                imagesx($resized), imagesy($resized), $width, $height,
            );

            ob_start();
            imagepng($resized, null, 9);
            $out = (string) ob_get_clean();

            imagedestroy($resized);

            return $out !== '' ? $out : $png;
        } finally {
            imagedestroy($source);
        }
    }

    /** The raw bytes of the first image on the workbook's first sheet. */
    private function extractFirstDrawing(string $workbook): ?string
    {
        $book = IOFactory::createReader('Xlsx')->load($workbook);

        try {
            foreach ($book->getAllSheets() as $sheet) {
                foreach ($sheet->getDrawingCollection() as $drawing) {
                    if (! $drawing instanceof Drawing) {
                        continue;
                    }

                    // Images inside an .xlsx are read through a zip:// stream.
                    $contents = file_get_contents($drawing->getPath());

                    if ($contents !== false && $contents !== '') {
                        return $contents;
                    }
                }
            }

            return null;
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
