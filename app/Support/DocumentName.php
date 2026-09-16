<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Names every document the system hands out.
 *
 * Two things have to line up for a browser to save a PDF under a sensible
 * name. The obvious one is the Content-Disposition header. The less obvious
 * one is the URL: Chrome opens a PDF inline and then names the saved file
 * after the last path segment, so routes ending in "/pdf", "/export" or
 * "/download" were landing on people's desktops as a file called "pdf" with
 * no extension, whatever the header said.
 *
 * So the document routes carry the name as their final segment, and both the
 * link in the view and the header in the controller read it from here — one
 * definition per document, or the two drift apart.
 */
final class DocumentName
{
    /** Characters Windows and macOS refuse in a filename. */
    private const FORBIDDEN = ['\\', '/', ':', '*', '?', '"', '<', '>', '|', "\0"];

    // ------------------------------------------------------------------
    // Documents that belong to one person
    // ------------------------------------------------------------------

    public static function ledgerCard(User $person): string
    {
        return self::forPerson($person, 'Leave Ledger Card');
    }

    /**
     * Defaults to this year, matching PdsSubmissionService::forYear(), so a
     * link built without the submission in hand still names the same document
     * the controller will send back.
     */
    public static function personalDataSheet(
        User $person,
        ?int $year = null,
        string $extension = 'pdf',
    ): string {
        return self::forPerson(
            $person,
            'Personal Data Sheet (' . ($year ?: now()->year) . ')',
            $extension,
        );
    }

    public static function leaveForm(
        User $person,
        int|string $reference,
        string $extension = 'pdf',
    ): string {
        return self::forPerson($person, "Leave Form (#{$reference})", $extension);
    }

    // ------------------------------------------------------------------
    // Campus-wide reports
    // ------------------------------------------------------------------

    public static function leaveBalances(?CarbonInterface $on = null, string $extension = 'pdf'): string
    {
        return self::clean('Leave Balances - ' . ($on ?? now())->format('F j, Y'), $extension);
    }

    public static function leaveCalendar(CarbonInterface $month): string
    {
        return self::clean('Leave Calendar - ' . $month->format('F Y'));
    }

    public static function employeeDirectory(?CarbonInterface $on = null): string
    {
        return self::clean('Employee Directory - ' . ($on ?? now())->format('F j, Y'));
    }

    // ------------------------------------------------------------------
    // Published blank forms
    // ------------------------------------------------------------------

    public static function template(string $document, int $version, string $extension = 'pdf'): string
    {
        return self::clean($document . ' v' . $version, $extension);
    }

    // ------------------------------------------------------------------

    /** "Mary Rose Niro's Leave Ledger Card.pdf" */
    public static function forPerson(User $person, string $document, string $extension = 'pdf'): string
    {
        $name = trim((string) $person->name) ?: 'Employee';

        // "Reyes'" reads better than "Reyes's" when the name already ends in s.
        $possessive = $name . (str_ends_with(strtolower($name), 's') ? "'" : "'s");

        return self::clean($possessive . ' ' . $document, $extension);
    }

    /**
     * A Content-Disposition value for a document served inline.
     *
     * Built here because addslashes() was doing this job, and it escapes the
     * apostrophe as well as the quote — so "Niro's Ledger Card.pdf" reached
     * the browser as "Niro\'s Ledger Card.pdf". Only the quote and the
     * backslash need escaping inside a quoted header value.
     */
    public static function disposition(string $filename, string $type = 'inline'): string
    {
        return HeaderUtils::makeDisposition(
            $type,
            $filename,
            // The fallback is used when the name is not plain ASCII.
            preg_replace('/[^\x20-\x7E]/', '_', $filename) ?: 'document.pdf',
        );
    }

    /** Strips what a filesystem will not accept and pins the extension. */
    public static function clean(string $name, string $extension = 'pdf'): string
    {
        $name = str_replace(self::FORBIDDEN, ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = trim($name, ' .');

        if ($name === '') {
            $name = 'Document';
        }

        return $name . '.' . ltrim($extension, '.');
    }
}
