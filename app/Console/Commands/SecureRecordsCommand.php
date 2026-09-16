<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Moves personnel records off the web-readable disk.
 *
 * Uploads used to be written to storage/app/public, which is symlinked into
 * public/storage and served straight off the filesystem — Laravel never sees
 * the request, so no login and no role check. PDS workbooks were named
 * {user_id}_{year}, which made the URLs countable: /storage/pds-working/2_2026
 * was employee #2's complete Personal Data Sheet, with their birth date, home
 * address and GSIS, PhilHealth, TIN and SSS numbers.
 *
 * The code now writes these to the private disk. This moves what is already
 * there, and is safe to run more than once.
 */
class SecureRecordsCommand extends Command
{
    protected $signature = 'records:secure {--dry-run : List what would move without moving it}';

    protected $description = 'Move uploaded personnel records off the public disk onto the private one';

    /**
     * Folders holding personal data. Blank templates stay public: they are the
     * empty official forms, the same ones anyone can download from the CSC.
     *
     * Profile photos stay public too — the ID verification page is meant to be
     * reachable without signing in, and their filenames are random rather than
     * derived from the employee.
     */
    private const PRIVATE_FOLDERS = [
        'pds-working' => 'filled Personal Data Sheets and their conversions',
        'leave-applications' => 'filed leave forms',
        'hr-policies' => 'policy attachments',
    ];

    /** Written by a feature that no longer exists, and never referenced again. */
    private const ORPHANED = [
        'pds/photos',
        'pds/signatures',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $public = Storage::disk('public');
        $private = Storage::disk('local');

        $moved = 0;
        $deleted = 0;

        foreach (self::PRIVATE_FOLDERS as $folder => $what) {
            $files = $public->files($folder);

            if ($files === []) {
                $this->line(sprintf('  %-22s nothing to move', $folder));

                continue;
            }

            $this->line(sprintf('  %-22s %d file(s) — %s', $folder, count($files), $what));

            foreach ($files as $path) {
                if ($dryRun) {
                    $this->line('      would move  ' . $path);
                    $moved++;

                    continue;
                }

                // Copy before deleting: an interrupted run must not lose a
                // record that exists in one place only.
                if (! $private->exists($path)) {
                    $private->put($path, $public->get($path));
                }

                $public->delete($path);
                $moved++;
            }
        }

        foreach (self::ORPHANED as $folder) {
            foreach ($public->files($folder) as $path) {
                if ($dryRun) {
                    $this->line('      would delete ' . $path . '  (orphaned)');
                    $deleted++;

                    continue;
                }

                $public->delete($path);
                $deleted++;
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("  {$moved} file(s) would move, {$deleted} orphan(s) would be deleted.");
            $this->line('  Run again without --dry-run to do it.');

            return self::SUCCESS;
        }

        $this->info("  {$moved} file(s) moved to the private disk, {$deleted} orphan(s) deleted.");

        $remaining = 0;

        foreach (array_keys(self::PRIVATE_FOLDERS) as $folder) {
            $remaining += count($public->files($folder));
        }

        if ($remaining > 0) {
            $this->warn("  {$remaining} file(s) are still readable at /storage. Run this again.");

            return self::FAILURE;
        }

        $this->line('  Nothing personal is left on the public disk.');

        return self::SUCCESS;
    }
}
