<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests del Migrator: aplicar, idempotencia, rollback, preprocesado.
 * Usa una carpeta temporal de migraciones controlada por el test.
 */
final class MigratorTest extends TestCase
{
    private string $dbPath;
    private string $migDir;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/imagina_mig_test_' . uniqid() . '.db';
        $this->migDir = sys_get_temp_dir() . '/imagina_mig_dir_' . uniqid();
        mkdir($this->migDir);
        putenv('DB_DRIVER=sqlite');
        putenv('DB_SQLITE_PATH=' . $this->dbPath);
        Database::reset();
    }

    protected function tearDown(): void
    {
        Database::reset();
        @unlink($this->dbPath);
        if (is_dir($this->migDir)) {
            foreach (glob($this->migDir . '/*') ?: [] as $f) @unlink($f);
            @rmdir($this->migDir);
        }
        putenv('DB_SQLITE_PATH');
    }

    private function write(string $name, string $sql): void
    {
        file_put_contents($this->migDir . '/' . $name, $sql);
    }

    #[Test]
    public function applies_pending_migrations_in_order(): void
    {
        $this->write('0001_widgets.sql', "CREATE TABLE widgets (id {{AUTO_PK}}, name TEXT NOT NULL){{TABLE_OPTIONS}};");
        $this->write('0002_gadgets.sql', "CREATE TABLE gadgets (id {{AUTO_PK}}, name TEXT NOT NULL){{TABLE_OPTIONS}};");

        $db = Database::getInstance();
        $migrator = new Migrator($db, $this->migDir);
        $applied = $migrator->up();

        $this->assertCount(2, $applied);
        $this->assertSame('0001_widgets', $applied[0]);
        $this->assertSame('0002_gadgets', $applied[1]);
    }

    #[Test]
    public function up_is_idempotent_on_rerun(): void
    {
        $this->write('0001_thing.sql', "CREATE TABLE thing (id {{AUTO_PK}}){{TABLE_OPTIONS}};");

        $db = Database::getInstance();
        $migrator = new Migrator($db, $this->migDir);
        $migrator->up();

        $secondRun = $migrator->up();
        $this->assertEmpty($secondRun);
    }

    #[Test]
    public function rollback_reverts_last_and_only_if_down_exists(): void
    {
        $this->write('0001_first.sql', "CREATE TABLE first_t (id {{AUTO_PK}}){{TABLE_OPTIONS}};");
        $this->write('0001_first.down.sql', "DROP TABLE first_t;");
        $this->write('0002_second.sql', "CREATE TABLE second_t (id {{AUTO_PK}}){{TABLE_OPTIONS}};");
        // 0002 sin .down.sql

        $db = Database::getInstance();
        $migrator = new Migrator($db, $this->migDir);
        $migrator->up();

        // Rollback debe fallar porque 0002 no tiene down
        $this->expectException(RuntimeException::class);
        $migrator->rollback(1);
    }

    #[Test]
    public function rollback_works_when_down_exists(): void
    {
        $this->write('0001_table.sql', "CREATE TABLE just_one (id {{AUTO_PK}}){{TABLE_OPTIONS}};");
        $this->write('0001_table.down.sql', "DROP TABLE just_one;");

        $db = Database::getInstance();
        $migrator = new Migrator($db, $this->migDir);
        $migrator->up();
        $this->assertNotNull($db->queryOne("SELECT name FROM sqlite_master WHERE name = 'just_one'"));

        $rolledBack = $migrator->rollback(1);
        $this->assertCount(1, $rolledBack);
        $this->assertNull($db->queryOne("SELECT name FROM sqlite_master WHERE name = 'just_one'"));

        // schema_migrations debe estar limpia
        $applied = $migrator->applied();
        $this->assertEmpty($applied);
    }

    #[Test]
    public function preprocessor_resolves_placeholders_for_active_driver(): void
    {
        $this->write('0001_x.sql', "x"); // dummy

        $db = Database::getInstance();
        $migrator = new Migrator($db, $this->migDir);

        $sql = "id {{AUTO_PK}}, active {{BOOL}}, data {{JSON}}, created {{NOW}}";
        $out = $migrator->preprocess($sql);

        // Driver activo es sqlite (setUp)
        $this->assertStringContainsString('INTEGER PRIMARY KEY AUTOINCREMENT', $out);
        $this->assertStringContainsString('INTEGER', $out);
        $this->assertStringContainsString("datetime('now')", $out);
        $this->assertStringNotContainsString('{{', $out);
    }

    #[Test]
    public function preprocessor_strips_other_driver_blocks(): void
    {
        $this->write('dummy.sql', '');
        $db = Database::getInstance();
        $migrator = new Migrator($db, $this->migDir);

        $sql = "common;\n--{mysql}\nmysql-only;\n--{/mysql}\n--{sqlite}\nsqlite-only;\n--{/sqlite}";
        $out = $migrator->preprocess($sql);
        $this->assertStringContainsString('common', $out);
        $this->assertStringContainsString('sqlite-only', $out);
        $this->assertStringNotContainsString('mysql-only', $out);
    }

    #[Test]
    public function status_reports_pending_count(): void
    {
        $this->write('0001_a.sql', "CREATE TABLE table_a (id {{AUTO_PK}}){{TABLE_OPTIONS}};");
        $this->write('0002_b.sql', "CREATE TABLE table_b (id {{AUTO_PK}}){{TABLE_OPTIONS}};");

        $db = Database::getInstance();
        $migrator = new Migrator($db, $this->migDir);
        $migrator->bootstrap();

        $s = $migrator->status();
        $this->assertSame(2, $s['totalAvailable']);
        $this->assertSame(0, $s['totalApplied']);
        $this->assertCount(2, $s['pending']);

        $migrator->up();
        $s2 = $migrator->status();
        $this->assertSame(2, $s2['totalApplied']);
        $this->assertCount(0, $s2['pending']);
    }
}
