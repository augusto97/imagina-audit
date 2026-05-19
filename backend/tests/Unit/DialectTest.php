<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests del Dialect SQL — emisión correcta de upsert + tipos por driver.
 * No requieren conexión a DB.
 */
final class DialectTest extends TestCase
{
    #[Test]
    public function sqlite_upsert_with_update_uses_on_conflict_do_update(): void
    {
        $d = new SqliteDialect();
        $sql = $d->upsert('settings', ['key', 'value'], ['key'], ['value']);
        $this->assertStringContainsString('INSERT INTO settings', $sql);
        $this->assertStringContainsString('ON CONFLICT(key) DO UPDATE SET value = excluded.value', $sql);
    }

    #[Test]
    public function sqlite_upsert_without_update_uses_do_nothing(): void
    {
        $d = new SqliteDialect();
        $sql = $d->upsert('languages', ['code'], ['code'], []);
        $this->assertStringContainsString('ON CONFLICT(code) DO NOTHING', $sql);
    }

    #[Test]
    public function mysql_upsert_with_update_uses_on_duplicate_key(): void
    {
        $d = new MysqlDialect();
        $sql = $d->upsert('settings', ['key', 'value'], ['key'], ['value']);
        $this->assertStringContainsString('INSERT INTO `settings`', $sql);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', $sql);
    }

    #[Test]
    public function mysql_upsert_without_update_uses_insert_ignore(): void
    {
        $d = new MysqlDialect();
        $sql = $d->upsert('languages', ['code'], ['code'], []);
        $this->assertStringContainsString('INSERT IGNORE INTO `languages`', $sql);
        $this->assertStringNotContainsString('ON DUPLICATE', $sql);
    }

    #[Test]
    public function now_returns_driver_specific_expression(): void
    {
        $this->assertSame('CURRENT_TIMESTAMP', (new MysqlDialect())->now());
        $this->assertSame("datetime('now')", (new SqliteDialect())->now());
    }

    #[Test]
    public function auto_pk_returns_driver_specific_syntax(): void
    {
        // BIGINT (signed) — sin UNSIGNED para que coincida con los FK
        // que referencian este PK; UNSIGNED causaba MySQL 1005.
        $this->assertSame('BIGINT AUTO_INCREMENT PRIMARY KEY', (new MysqlDialect())->autoIncrementPk());
        $this->assertSame('INTEGER PRIMARY KEY AUTOINCREMENT', (new SqliteDialect())->autoIncrementPk());
    }

    #[Test]
    public function name_returns_canonical_driver(): void
    {
        $this->assertSame('mysql', (new MysqlDialect())->name());
        $this->assertSame('sqlite', (new SqliteDialect())->name());
    }
}
