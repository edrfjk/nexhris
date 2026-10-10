<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/**
 * Base for tests that need the real engine. Every test starts from a freshly
 * migrated `nexhris_integration_test` — never the development or production
 * database, which this class refuses to touch. Data is committed for real
 * (no wrapping transaction), so other processes can see it.
 */
abstract class MariaDbTestCase extends TestCase
{
    public const DATABASE = 'nexhris_integration_test';

    /** Creates the throwaway database if it is missing. Touches nothing else. */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (getenv('DB_DATABASE') !== self::DATABASE) {
            return;
        }

        $env = self::dotenv();
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s', $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306'),
            $env['DB_USERNAME'] ?? 'root',
            $env['DB_PASSWORD'] ?? '',
        );
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . self::DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $connection = DB::connection();

        // The only guard that matters: everything below wipes the database.
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
            || $connection->getDatabaseName() !== self::DATABASE) {
            $this->markTestSkipped('Integration tests run only on ' . self::DATABASE . ' (vendor/bin/phpunit -c phpunit.mariadb.xml).');
        }

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    /** @return array<string, string> */
    private static function dotenv(): array
    {
        $values = [];

        foreach (file(dirname(__DIR__, 2) . '/.env', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^(DB_[A-Z]+)=(.*)$/', trim($line), $m)) {
                $values[$m[1]] = trim($m[2], "\"'");
            }
        }

        return $values;
    }

    /**
     * Runs tests/Integration/bin/race.php in one separate PHP process per job,
     * all at once, each with its own database connection, and returns what
     * each printed.
     *
     * @param  list<array<string, mixed>>  $jobs
     * @return list<array<string, mixed>>
     */
    protected function race(array $jobs): array
    {
        $script = __DIR__ . '/bin/race.php';
        $startAt = microtime(true) + 2.0;
        $env = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => self::DATABASE,
            'DB_URL' => '',
            'MAIL_MAILER' => 'array',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
        ]);

        $processes = [];

        foreach ($jobs as $i => $job) {
            $job['start_at'] = $startAt;
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, $script, json_encode($job)],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname(__DIR__, 2),
                $env,
            );
            $processes[$i] = [$process, $pipes];
        }

        $results = [];

        foreach ($processes as $i => [$process, $pipes]) {
            $out = (string) stream_get_contents($pipes[1]);
            $err = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            $lines = array_values(array_filter(array_map('trim', explode("\n", $out))));
            $decoded = json_decode((string) end($lines), true);
            $this->assertIsArray($decoded, "Worker {$i} printed no result. stdout: {$out} stderr: {$err}");
            $results[$i] = $decoded;
        }

        return $results;
    }
}
