<?php

namespace App\Console\Commands;

use App\Services\DatabaseDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Takes an encrypted backup of the database and the uploaded records.
 *
 * The ledger cards are the system of record for leave credits people have
 * accrued across whole careers, and nothing else holds them. A bad migration,
 * a dropped table or a hosting incident loses them outright.
 *
 * The archive is AES-256 encrypted before it leaves the server, which is what
 * makes an off-site copy reasonable: the provider holds ciphertext, so putting
 * personnel records on storage the campus does not own stays a storage
 * decision rather than a disclosure.
 */
class BackupRunCommand extends Command
{
    protected $signature = 'backup:run
        {--keep= : How many archives to keep, overriding config}
        {--local : Skip the off-site copy even when one is configured}';

    protected $description = 'Write an encrypted archive of the database and uploaded records';

    public function handle(DatabaseDump $dump): int
    {
        $passphrase = (string) config('backup.passphrase');

        if ($passphrase === '') {
            $this->error('  No passphrase. Set BACKUP_PASSWORD, or APP_KEY at minimum.');

            return self::FAILURE;
        }

        $name = 'nexhris-' . now()->format('Y-m-d-Hi') . '.zip';
        $directory = storage_path('app/backups');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $archive = $directory . DIRECTORY_SEPARATOR . $name;
        $sql = $directory . DIRECTORY_SEPARATOR . 'database.sql';

        $this->line('  Writing the database…');
        $rows = $dump->writeTo($sql);
        $this->line(sprintf('    %s rows across %d tables', number_format($rows), count($dump->tables())));

        try {
            $files = $this->build($archive, $sql, $passphrase);
        } finally {
            // The plaintext dump must not outlive the encrypted archive.
            @unlink($sql);
        }

        $this->line(sprintf(
            '  %s  —  %s, %d file(s), AES-256',
            $name,
            $this->readableSize((int) filesize($archive)),
            $files,
        ));

        $this->copyOffSite($archive, $name);
        $this->prune((int) ($this->option('keep') ?: config('backup.keep', 7)));

        $this->newLine();
        $this->info('  Backup complete.');

        return self::SUCCESS;
    }

    /**
     * Builds the encrypted zip, streaming each file in from disk.
     *
     * ZipArchive reads from the filesystem rather than from memory, so a
     * gigabyte of records does not need a gigabyte of RAM — which matters,
     * because shared hosting caps PHP well below the size these archives will
     * eventually reach.
     */
    private function build(string $archive, string $sql, string $passphrase): int
    {
        $zip = new ZipArchive();

        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create {$archive}");
        }

        $zip->setPassword($passphrase);

        $add = function (string $path, string $entry) use ($zip): void {
            $zip->addFile($path, $entry);
            $zip->setEncryptionName($entry, ZipArchive::EM_AES_256);
        };

        $add($sql, 'database.sql');
        $count = 1;

        foreach ((array) config('backup.include', []) as $folder) {
            $root = storage_path('app/' . $folder);

            if (! is_dir($root)) {
                continue;
            }

            $walk = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($walk as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $relative = 'storage/' . $folder . '/' . str_replace(
                    '\\',
                    '/',
                    substr($file->getPathname(), strlen($root) + 1),
                );

                $add($file->getPathname(), $relative);
                $count++;
            }
        }

        $zip->close();

        return $count;
    }

    private function copyOffSite(string $archive, string $name): void
    {
        $disk = config('backup.disk');

        if ($this->option('local') || ! $disk) {
            $this->warn('  Kept on this server only — set BACKUP_DISK for an off-site copy.');

            return;
        }

        try {
            $stream = fopen($archive, 'rb');
            Storage::disk($disk)->put('backups/' . $name, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $this->line("  Copied to the {$disk} disk.");
        } catch (\Throwable $e) {
            // A failed upload must not lose the local archive, which is the
            // part that actually exists.
            $this->error('  Off-site copy failed: ' . $e->getMessage());
            $this->line('  The local archive is intact.');
        }
    }

    private function prune(int $keep): void
    {
        if ($keep < 1) {
            return;
        }

        $archives = glob(storage_path('app/backups/nexhris-*.zip')) ?: [];
        rsort($archives);

        foreach (array_slice($archives, $keep) as $old) {
            @unlink($old);
            $this->line('  Removed ' . basename($old));
        }

        $disk = config('backup.disk');

        if ($this->option('local') || ! $disk) {
            return;
        }

        try {
            $remote = Storage::disk($disk)->files('backups');
            rsort($remote);

            foreach (array_slice($remote, $keep) as $old) {
                Storage::disk($disk)->delete($old);
            }
        } catch (\Throwable $e) {
            $this->warn('  Could not prune the off-site copies: ' . $e->getMessage());
        }
    }

    private function readableSize(int $bytes): string
    {
        return $bytes > 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : max(1, (int) round($bytes / 1024)) . ' KB';
    }
}
