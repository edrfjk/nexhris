<?php

namespace App\Support;

/**
 * How wide a line of Arial is, from the Helvetica metrics Dompdf ships.
 *
 * Arial was drawn to Helvetica's widths, and Liberation Sans — what a Linux
 * server prints Arial with — to Arial's, so one table measures text the
 * same way Excel, LibreOffice and the PDF renderer will set it.
 */
final class TextMetrics
{
    /** @var array<string, array<int, int>> */
    private static array $widths = [];

    /** Width in points of $text set in Arial at $size. */
    public static function width(string $text, float $size, bool $bold = false): float
    {
        $table = self::table($bold);
        $units = 0;

        foreach (mb_str_split($text) as $char) {
            $code = mb_ord($char);
            // Outside Latin-1 (curly quotes, dashes) the average glyph will do.
            $units += $table[$code] ?? 556;
        }

        return $units / 1000 * $size;
    }

    /** The largest size, from $size down to $smallest, at which $text fits $width. */
    public static function fit(string $text, float $width, float $size, float $smallest = 6.0, bool $bold = true): float
    {
        $measured = self::width($text, $size, $bold);

        if ($measured <= $width || $measured <= 0) {
            return $size;
        }

        return max($smallest, floor($size * $width / $measured * 2) / 2);
    }

    /**
     * Splits text into lines no wider than the given widths, one width per
     * line. Whatever does not fit the last line stays on it.
     *
     * @param  list<float>  $widths
     * @return list<string>
     */
    public static function wrap(string $text, array $widths, float $size, bool $bold = true): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';
        $index = 0;

        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            $isLast = $index >= count($widths) - 1;

            if ($line !== '' && ! $isLast && self::width($candidate, $size, $bold) > $widths[$index]) {
                $lines[] = $line;
                $line = $word;
                $index++;

                continue;
            }

            $line = $candidate;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    /** @return array<int, int> code point => width in thousandths of an em */
    private static function table(bool $bold): array
    {
        $key = $bold ? 'bold' : 'regular';

        if (! isset(self::$widths[$key])) {
            $file = base_path('vendor/dompdf/dompdf/lib/fonts/' . ($bold ? 'Helvetica-Bold' : 'Helvetica') . '.afm.json');
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

            self::$widths[$key] = array_map('intval', $data['C'] ?? []);
        }

        return self::$widths[$key];
    }
}
