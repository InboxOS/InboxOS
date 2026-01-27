<?php

namespace App\Tests\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use PHPUnit\Framework\TestCase;

class MigrationTest extends TestCase
{
    public function testMigrationStructure(): void
    {
        // Test that migration files exist and are properly structured
        $migrationFiles = glob(__DIR__ . '/../../migrations/*.php');

        $this->assertNotEmpty($migrationFiles, 'No migration files found');

        foreach ($migrationFiles as $migrationFile) {
            $this->assertFileExists($migrationFile);

            // Check that file contains required migration structure
            $content = file_get_contents($migrationFile);

            $this->assertStringContainsString('extends AbstractMigration', $content,
                "Migration {$migrationFile} does not extend AbstractMigration");

            $this->assertStringContainsString('public function up(Schema $schema): void', $content,
                "Migration {$migrationFile} does not have up() method");

            $this->assertStringContainsString('public function down(Schema $schema): void', $content,
                "Migration {$migrationFile} does not have down() method");
        }
    }

    public function testMigrationVersionOrdering(): void
    {
        $migrationFiles = glob(__DIR__ . '/../../migrations/Version*.php');

        $versions = [];
        foreach ($migrationFiles as $file) {
            if (preg_match('/Version(\d{14})\.php$/', basename($file), $matches)) {
                $versions[] = $matches[1];
            }
        }

        // Check that versions are in ascending order
        $sortedVersions = $versions;
        sort($sortedVersions);

        $this->assertEquals($sortedVersions, $versions,
            'Migration versions are not in chronological order');
    }

    public function testMigrationContentValidation(): void
    {
        $migrationFiles = glob(__DIR__ . '/../../migrations/*.php');

        foreach ($migrationFiles as $migrationFile) {
            $content = file_get_contents($migrationFile);

            // Check for common migration patterns
            $this->assertStringContainsString('$this->addSql(', $content,
                "Migration {$migrationFile} does not contain SQL statements");

            // Check for proper schema usage
            $this->assertStringContainsString('$schema', $content,
                "Migration {$migrationFile} does not use schema parameter");

            // Check for proper table creation/modification
            $hasTableOperation = strpos($content, 'createTable') !== false ||
                               strpos($content, 'dropTable') !== false ||
                               strpos($content, 'addColumn') !== false ||
                               strpos($content, 'dropColumn') !== false ||
                               strpos($content, 'createIndex') !== false ||
                               strpos($content, 'dropIndex') !== false ||
                               stripos($content, 'CREATE TABLE') !== false ||
                               stripos($content, 'DROP TABLE') !== false ||
                               stripos($content, 'ALTER TABLE') !== false;

            $this->assertTrue($hasTableOperation,
                "Migration {$migrationFile} does not contain table operations");
        }
    }
}