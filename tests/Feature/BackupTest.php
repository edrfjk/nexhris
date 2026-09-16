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

    protected function tearDown(): void
    {
        foreach (glob(storage_path('app/backups/nexhris-*.zip')) ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function latestArchive(): string
    {
        $archives = glob(storage_path('app/backups/nexhris-*.zip')) ?: [];

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
        $this->assertFileDoesNotExist(storage_path('app/backups/database.sql'));
    }

    public function test_old_archives_are_pruned(): void
    {
        $directory = storage_path('app/backups');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        foreach (['2020-01-01-0100', '2020-01-02-0100', '2020-01-03-0100'] as $stamp) {
            touch($directory . '/nexhris-' . $stamp . '.zip');
        }

        $this->artisan('backup:run --local --keep=2')->assertSuccessful();

        $this->assertCount(
            2,
            glob($directory . '/nexhris-*.zip') ?: [],
            'the run should leave exactly the number of archives asked for',
        );
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
