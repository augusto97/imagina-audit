<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests integrados de Database + Migrator usando SQLite efímero.
 * Verifica los helpers cross-driver: upsert, setting, transaction, now.
 */
final class DatabaseTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/imagina_db_test_' . uniqid() . '.db';
        putenv('DB_DRIVER=sqlite');
        putenv('DB_SQLITE_PATH=' . $this->dbPath);
        Database::reset();
    }

    protected function tearDown(): void
    {
        Database::reset();
        @unlink($this->dbPath);
        putenv('DB_SQLITE_PATH');
    }

    #[Test]
    public function init_schema_applies_versioned_migrations(): void
    {
        $db = Database::getInstance();
        $db->initSchema();

        // 0001_initial + 0002_admin_login_attempts deben quedar aplicadas
        $applied = $db->scalar("SELECT COUNT(*) FROM schema_migrations");
        $this->assertGreaterThanOrEqual(2, (int) $applied);

        // Tablas críticas presentes
        foreach (['audits', 'users', 'languages', 'login_attempts', 'settings'] as $t) {
            $row = $db->queryOne("SELECT name FROM sqlite_master WHERE name = ?", [$t]);
            $this->assertNotNull($row, "Tabla $t debería existir");
        }
    }

    #[Test]
    public function setting_helper_upserts_correctly(): void
    {
        $db = Database::getInstance();
        $db->initSchema();

        $db->setting('test_key', 'value1');
        $this->assertSame('value1', $db->scalar("SELECT value FROM settings WHERE `key` = ?", ['test_key']));

        // Re-set actualiza, no duplica
        $db->setting('test_key', 'value2');
        $this->assertSame('value2', $db->scalar("SELECT value FROM settings WHERE `key` = ?", ['test_key']));
        $count = (int) $db->scalar("SELECT COUNT(*) FROM settings WHERE `key` = ?", ['test_key']);
        $this->assertSame(1, $count);
    }

    #[Test]
    public function setting_if_missing_does_not_overwrite(): void
    {
        $db = Database::getInstance();
        $db->initSchema();

        $db->setting('k', 'original');
        $db->settingIfMissing('k', 'replacement');
        $this->assertSame('original', $db->scalar("SELECT value FROM settings WHERE `key` = ?", ['k']));

        // Pero sí inserta keys nuevas
        $db->settingIfMissing('new_k', 'fresh');
        $this->assertSame('fresh', $db->scalar("SELECT value FROM settings WHERE `key` = ?", ['new_k']));
    }

    #[Test]
    public function transaction_commits_on_success(): void
    {
        $db = Database::getInstance();
        $db->initSchema();

        $db->transaction(function ($db) {
            $db->setting('a', '1');
            $db->setting('b', '2');
        });

        $this->assertSame('1', $db->scalar("SELECT value FROM settings WHERE `key` = ?", ['a']));
        $this->assertSame('2', $db->scalar("SELECT value FROM settings WHERE `key` = ?", ['b']));
    }

    #[Test]
    public function transaction_rolls_back_on_throw(): void
    {
        $db = Database::getInstance();
        $db->initSchema();

        try {
            $db->transaction(function ($db) {
                $db->setting('rollback_key', 'should_not_persist');
                throw new RuntimeException('boom');
            });
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $count = (int) $db->scalar("SELECT COUNT(*) FROM settings WHERE `key` = ?", ['rollback_key']);
        $this->assertSame(0, $count);
    }

    #[Test]
    public function upsert_inserts_then_updates(): void
    {
        $db = Database::getInstance();
        $db->initSchema();

        $rows = $db->upsert(
            'languages',
            ['code' => 'tt', 'name' => 'TestLang', 'native_name' => 'Test', 'is_active' => 1, 'is_public' => 1, 'sort_order' => 99],
            ['code']
        );
        $this->assertGreaterThan(0, $rows);
        $this->assertSame('TestLang', $db->scalar("SELECT name FROM languages WHERE code = ?", ['tt']));

        // Re-upsert con name distinto
        $db->upsert(
            'languages',
            ['code' => 'tt', 'name' => 'Updated', 'native_name' => 'Test', 'is_active' => 1, 'is_public' => 1, 'sort_order' => 99],
            ['code']
        );
        $this->assertSame('Updated', $db->scalar("SELECT name FROM languages WHERE code = ?", ['tt']));

        // Solo una fila
        $count = (int) $db->scalar("SELECT COUNT(*) FROM languages WHERE code = ?", ['tt']);
        $this->assertSame(1, $count);
    }

    #[Test]
    public function now_expression_works_inline(): void
    {
        $db = Database::getInstance();
        $db->initSchema();

        $now = $db->now();
        $db->execute("INSERT INTO settings (`key`, value, updated_at) VALUES (?, ?, $now)", ['ts_test', 'x']);
        $stored = $db->scalar("SELECT updated_at FROM settings WHERE `key` = ?", ['ts_test']);
        $this->assertNotEmpty($stored);
    }

    #[Test]
    public function driver_helper_reflects_env(): void
    {
        $db = Database::getInstance();
        $this->assertSame('sqlite', $db->driver());
        $this->assertSame('sqlite', $db->dialect()->name());
    }

    #[Test]
    public function bool_normalizes_to_zero_or_one(): void
    {
        $db = Database::getInstance();
        $this->assertSame(1, $db->bool(true));
        $this->assertSame(1, $db->bool('yes'));
        $this->assertSame(0, $db->bool(false));
        $this->assertSame(0, $db->bool(0));
        $this->assertSame(0, $db->bool(''));
    }

    #[Test]
    public function json_encodes_arrays_passes_strings_through(): void
    {
        $db = Database::getInstance();
        $this->assertSame('{"a":1}', $db->json(['a' => 1]));
        $this->assertSame('already json', $db->json('already json'));
    }
}
