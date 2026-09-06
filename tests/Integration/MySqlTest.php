<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests\Integration;

use Fnlla\Php\Database\DatabaseManager;
use Fnlla\Php\Database\Migrations\Migrator;
use Fnlla\Php\Support\DeveloperWorkspaceBoard;
use Fnlla\Php\Support\DeveloperNotificationCenter;
use PDO;
use PHPUnit\Framework\TestCase;

final class MySqlTest extends TestCase
{
    private DatabaseManager $db;
    private mixed $previousDb;
    private array $previousConfig;
    private string $table;
    private string $notifications;

    protected function setUp(): void
    {
        $dsn = getenv("FNLLA_TEST_MYSQL_DSN");
        if (!$dsn) {
            self::markTestSkipped("Set FNLLA_TEST_MYSQL_DSN to an isolated fnlla_test_* database.");
        }
        if (!preg_match('/(?:^|;)dbname=fnlla_test_[a-z0-9_]+(?:;|$)/', $dsn)) {
            self::fail("Integration tests require an explicitly named fnlla_test_* database.");
        }
        $this->db = DatabaseManager::using(new PDO($dsn, getenv("FNLLA_TEST_MYSQL_USER") ?: "root", getenv("FNLLA_TEST_MYSQL_PASSWORD") ?: ""));
        $this->previousDb = app(DatabaseManager::class);
        $this->previousConfig = config("developer_workspace");
        app()->instance(DatabaseManager::class, $this->db);
        $this->table = "fnlla_test_board_" . bin2hex(random_bytes(6));
        $this->notifications = "fnlla_test_notices_" . bin2hex(random_bytes(6));
        config_set("developer_workspace.driver", "database");
        config_set("developer_workspace.table", $this->table);
        config_set("developer_workspace.notifications_table", $this->notifications);
    }

    protected function tearDown(): void
    {
        if (!isset($this->db)) {
            return;
        }
        if ($this->db->connection()->inTransaction()) {
            $this->db->connection()->rollBack();
        }
        foreach ([$this->table, $this->notifications] as $table) {
            $this->db->statement("DROP TABLE IF EXISTS `{$table}`");
        }
        app()->instance(DatabaseManager::class, $this->previousDb);
        config_set("developer_workspace", $this->previousConfig);
    }

    public function testNestedRollbackPreservesOuterWriteAndNullQueriesExecute(): void
    {
        $this->db->statement("CREATE TABLE `{$this->table}` (id INT PRIMARY KEY, note VARCHAR(80) NULL) ENGINE=InnoDB");
        $this->db->transaction(function (): void {
            $this->db->statement("INSERT INTO `{$this->table}` VALUES (1, NULL)");
            try {
                $this->db->transaction(function (): void {
                    $this->db->statement("INSERT INTO `{$this->table}` VALUES (2, 'inner')");
                    throw new \RuntimeException("cancel inner");
                });
            } catch (\RuntimeException $error) {
                self::assertSame("cancel inner", $error->getMessage());
            }
            self::assertTrue($this->db->connection()->inTransaction());
        });
        self::assertCount(1, $this->db->table($this->table)->whereNull("note")->get());
        self::assertCount(0, $this->db->table($this->table)->whereNotNull("note")->get());
    }

    public function testParallelFirstWritersPreserveEveryTaskAndNotification(): void
    {
        $workers = [];
        try {
            for ($i = 0; $i < 4; $i++) {
                $process = proc_open([PHP_BINARY, __DIR__ . "/mysql-worker.php", $this->table, $this->notifications, (string) $i],
                    [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
                self::assertIsResource($process);
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [$process, $pipes]) {
                $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process), $output);
            }
        } finally {
            foreach ($workers as [$process, $pipes]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
                    proc_close($process);
                }
            }
        }
        $tasks = (new DeveloperWorkspaceBoard())->state()["tasks"];
        $created = array_filter($tasks, static fn (array $task): bool => str_starts_with($task["title"], "parallel-"));
        self::assertCount(60, $created);
        self::assertCount(60, $this->db->select("SELECT notification_key FROM `{$this->notifications}`"));
    }

    public function testPanelMutationDoesNotCommitCallerTransaction(): void
    {
        $board = new DeveloperWorkspaceBoard();
        $board->state();
        $notifications = new DeveloperNotificationCenter();
        $notifications->acknowledge("initial");
        $this->db->connection()->beginTransaction();
        $board->create(["title" => "must rollback"]);
        $notifications->archive("temporary");
        self::assertTrue($this->db->connection()->inTransaction());
        $this->db->connection()->rollBack();
        self::assertCount(0, $this->db->select("SELECT payload FROM `{$this->table}`"));
        self::assertCount(1, $this->db->select("SELECT payload FROM `{$this->notifications}`"));
    }

    public function testNamedMigrationConnectionOwnsSchemaLedgerAndRollback(): void
    {
        $pdo = new PDO((string) getenv("FNLLA_TEST_MYSQL_DSN"), getenv("FNLLA_TEST_MYSQL_USER") ?: "root", getenv("FNLLA_TEST_MYSQL_PASSWORD") ?: "");
        $named = $this->db->registerConnection("migration-test", $pdo);
        self::assertNotSame($this->db->connection(), $named->connection());
        $directory = sys_get_temp_dir() . "/fnlla-migrations-" . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $previous = config("database.migrations_table");
        config_set("database.migrations_table", $this->notifications);
        $file = "2026_09_06_000001_connection.php";
        // A temporary table is visible only to the PDO session used by the migrator.
        $fixture = '<?php return new class(new \\Fnlla\\Php\\Database\\DatabaseManager("deliberately-missing")) extends \\Fnlla\\Php\\Database\\Migrations\\Migration {
            public function up(): void { $this->statement("CREATE TEMPORARY TABLE `TABLE_NAME` (id INT PRIMARY KEY)"); }
            public function down(): void { $this->statement("DROP TEMPORARY TABLE `TABLE_NAME`"); }
        };';
        file_put_contents($directory . "/" . $file, str_replace("TABLE_NAME", $this->table, $fixture));
        $migrator = new Migrator($named, $directory);
        try {
            self::assertFalse($migrator->status()[0]["ran"]);
            self::assertSame([$file], $migrator->migrate());
            self::assertSame([], $named->select("SELECT * FROM `{$this->table}`"));
            self::assertSame([], $migrator->migrate());
            self::assertTrue($migrator->status()[0]["ran"]);
            self::assertSame(1, $migrator->status()[0]["batch"]);
            self::assertSame([$file], $migrator->rollback());
            self::assertFalse($migrator->status()[0]["ran"]);
            self::assertSame([], $migrator->rollback());
            $failure = "2026_09_06_000002_failure.php";
            file_put_contents($directory . "/" . $failure, '<?php return new class(new \\Fnlla\\Php\\Database\\DatabaseManager()) extends \\Fnlla\\Php\\Database\\Migrations\\Migration { public function up(): void { throw new \\RuntimeException("migration fixture failed"); } };');
            try {
                $migrator->migrate();
                self::fail("Failing migration was accepted.");
            } catch (\RuntimeException $error) {
                self::assertSame("migration fixture failed", $error->getMessage());
            }
            self::assertTrue($migrator->status()[0]["ran"]);
            self::assertFalse($migrator->status()[1]["ran"]);
            self::assertCount(1, $named->select("SELECT * FROM `{$this->notifications}`"));
        } finally {
            $named->statement("DROP TEMPORARY TABLE IF EXISTS `{$this->table}`");
            foreach (glob($directory . "/*.php") ?: [] as $path) { unlink($path); }
            rmdir($directory);
            config_set("database.migrations_table", $previous);
        }
    }
}
