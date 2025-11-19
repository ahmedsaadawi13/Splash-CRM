#!/usr/bin/env php
<?php

/**
 * Database Seeder Runner
 *
 * Populates database with sample data
 */

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configure Database
$capsule = new Capsule();
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => env('DB_HOST', 'localhost'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'splash_crm'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "\n========================================\n";
echo "Splash CRM - Database Seeder\n";
echo "========================================\n\n";

try {
    // Run seeders
    $permissionsSeeder = new \Database\Seeders\PermissionsSeeder();
    $permissionsSeeder->run();

    echo "\n";

    $demoTenantSeeder = new \Database\Seeders\DemoTenantSeeder();
    $demoTenantSeeder->run();

    echo "✓ All seeders completed successfully\n\n";

} catch (\Exception $e) {
    echo "\n✗ Seeding failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}
