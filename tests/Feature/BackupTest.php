<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseDump;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * The backup has to be readable later and unreadable now.
 *
 * Leave ledger cards are the only record of credits people accrued across
 * whole careers, and nothing else holds them — a bad migration or a hosting
 * incident loses them outright.
 *
 * Encryption is what makes an off-site copy reasonable: the archive leaves the
 * server as ciphertext, so storing it somewhere the campus does not own stays
 * a storage decision rather than a disclosure.
 */
class BackupTest extends TestCase
{
    use RefreshDatabase;

    /** A throwaway folder owned by this test, never storage/app/backups. */
    private string $directory;

    /** What the real backup folder held before the test, to prove it is untouched. */
    private array $realBefore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->realBefore = $this->snapshot(storage_path('app/backups'));

        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexhris-backup-test-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0775, true);
        config(['backup.directory' => $this->directory]);
    }

    protected function tearDown(): void
    {
        // Cleanup is confined to this test's own folder: only it is removed.
        if (isset($this->directory) && str_contains(basename($this->directory), 'nexhris-backup-test-')) {
            foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->directory);
        }

        // The real archives are never touched by any backup test.
        if (isset($this->realBefore)) {
            $this->assertSame($this->realBefore, $this->snapshot(storage_path('app/backups')),
                'a backup test changed the real storage/app/backups folder');
        }

        parent::tearDown();
    }

    /** name => size + mtime of every file in a folder (read-only). */
    private function snapshot(string $directory): array
    {
        $files = [];

        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            $files[basename($file)] = filesize($file) . '@' . filemtime($file);
        }

        ksort($files);

        return $files;
    }

    private function latestArchive(): string
    {
        $archives = glob($this->directory . DIRECTORY_SEPARATOR . 'nexhris-*.zip') ?: [];

        $this->assertNotEmpty($archives, 'no archive was written');

        rsort($archives);

        return $archives[0];
    }

    public function test_the_archive_cannot_be_read_without_the_passphrase(): void
    {
        $this->artisan('backup:run --local')->assertSuccessful();

        $zip = new ZipArchive();
        $zip->open($this->latestArchive());

        $this->assertFalse(
            $zip->getFromName('database.sql'),
            'the dump is readable without a passphrase — the archive is not encrypted',
        );

        $zip->setPassword((string) config('backup.passphrase'));

        $this->assertIsString(
            $zip->getFromName('database.sql'),
            'the dump cannot be read even with the right passphrase',
        );

        $zip->close();
    }

    public function test_the_dump_carries_the_rows_that_matter(): void
    {
        User::factory()->create(['name' => 'Mary Rose Niro', 'role' => 'employee']);

        $this->artisan('backup:run --local')->assertSuccessful();

        $zip = new ZipArchive();
        $zip->open($this->latestArchive());
        $zip->setPassword((string) config('backup.passphrase'));
        $sql = (string) $zip->getFromName('database.sql');
        $zip->close();

        $this->assertStringContainsString('users', $sql);
        $this->assertStringContainsString('leave_ledger_entries', $sql);
        $this->assertStringContainsString('Mary Rose Niro', $sql);
    }

    public function test_the_plaintext_dump_is_not_left_behind(): void
    {
        $this->artisan('backup:run --local')->assertSuccessful();

        // It is written to build the archive and must not outlive it — an
        // unencrypted copy of every personnel record beside the encrypted one
        // would defeat the point entirely.
        $this->assertFileDoesNotExist($this->directory . DIRECTORY_SEPARATOR . 'database.sql');
    }

    public function test_old_archives_are_pruned(): void
    {
        foreach (['2020-01-01-0100', '2020-01-02-0100', '2020-01-03-0100'] as $stamp) {
            touch($this->directory . '/nexhris-' . $stamp . '.zip');
        }

        $this->artisan('backup:run --local --keep=2')->assertSuccessful();

        $this->assertCount(
            2,
            glob($this->directory . '/nexhris-*.zip') ?: [],
            'the run should leave exactly the number of archives asked for',
        );
    }

    public function test_pruning_only_removes_archives_the_command_named(): void
    {
        touch($this->directory . '/nexhris-2020-01-01-0100.zip');
        touch($this->directory . '/nexhris-before-upgrade.zip');
        touch($this->directory . '/payroll-2020.zip');

        $this->artisan('backup:run --local --keep=1')->assertSuccessful();

        $this->assertFileDoesNotExist($this->directory . '/nexhris-2020-01-01-0100.zip');
        $this->assertFileExists($this->directory . '/nexhris-before-upgrade.zip', 'a hand-made copy was pruned');
        $this->assertFileExists($this->directory . '/payroll-2020.zip', 'an unrelated file was pruned');
    }

    public function test_the_tests_never_write_to_the_real_backup_folder(): void
    {
        $this->assertNotSame(realpath(storage_path('app/backups')) ?: storage_path('app/backups'), realpath($this->directory));
        $this->assertSame($this->directory, config('backup.directory'));

        $this->artisan('backup:run --local')->assertSuccessful();

        $this->assertNotEmpty(glob($this->directory . '/nexhris-*.zip'));
        // tearDown() then asserts storage/app/backups is exactly as it was.
    }

    public function test_the_production_default_is_unchanged(): void
    {
        $config = require config_path('backup.php');

        $this->assertSame(storage_path('app/backups'), $config['directory']);
    }

    public function test_archive_names_are_recognised_exactly(): void
    {
        $this->assertTrue(\App\Console\Commands\BackupRunCommand::isOwnArchive('nexhris-2026-10-09-2329.zip'));
        foreach (['nexhris-before-upgrade.zip', 'nexhris-2026-10-09.zip', 'nexhris-2026-10-09-2329.zip.bak', 'x-nexhris-2026-10-09-2329.zip'] as $name) {
            $this->assertFalse(\App\Console\Commands\BackupRunCommand::isOwnArchive($name), $name);
        }
    }

    public function test_the_dump_runs_without_mysqldump(): void
    {
        // The whole point of writing this in PHP: shared hosting usually has
        // exec() disabled and mysqldump absent, and a backup that only works in
        // development is not a backup.
        $path = tempnam(sys_get_temp_dir(), 'dump') . '.sql';

        $rows = app(DatabaseDump::class)->writeTo($path);

        $this->assertGreaterThan(0, $rows);
        $this->assertStringContainsString('CREATE TABLE', file_get_contents($path));

        @unlink($path);
    }
}
