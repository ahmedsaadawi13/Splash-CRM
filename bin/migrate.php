#!/usr/bin/env php
<?php

/**
 * Database Migration Runner
 *
 * Executes SQL migration files in order
 */

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Database connection
$host = env('DB_HOST', 'localhost');
$port = env('DB_PORT', '3306');
$database = env('DB_DATABASE', 'splash_crm');
$username = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "Connected to MySQL server\n";

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '{$database}' ensured\n";

    // Use the database
    $pdo->exec("USE `{$database}`");

    // Create migrations table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Get executed migrations
    $stmt = $pdo->query("SELECT migration FROM migrations ORDER BY id");
    $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get migration files
    $migrationsPath = __DIR__ . '/../database/migrations';
    $files = glob($migrationsPath . '/*.sql');
    sort($files);

    if (empty($files)) {
        echo "No migration files found\n";
        exit(0);
    }

    // Calculate next batch number
    $stmt = $pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 as next_batch FROM migrations");
    $nextBatch = $stmt->fetchColumn();

    $newMigrations = 0;

    foreach ($files as $file) {
        $migrationName = basename($file);

        if (in_array($migrationName, $executed)) {
            echo "✓ {$migrationName} (already executed)\n";
            continue;
        }

        echo "→ Executing {$migrationName}...\n";

        try {
            $sql = file_get_contents($file);

            // Execute migration
            $pdo->exec($sql);

            // Record migration
            $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
            $stmt->execute([$migrationName, $nextBatch]);

            echo "✓ {$migrationName} executed successfully\n";
            $newMigrations++;

        } catch (PDOException $e) {
            echo "✗ {$migrationName} failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    if ($newMigrations === 0) {
        echo "\nNo new migrations to execute\n";
    } else {
        echo "\n✓ Executed {$newMigrations} migration(s) in batch {$nextBatch}\n";
    }

} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}
