<?php
// Migration: Add role column to users table
require_once BASE_PATH . '/app/core/Database.php';

$db = Database::getInstance();

try {
    $stmt = $db->prepare("ALTER TABLE users ADD COLUMN role ENUM('admin', 'gerente', 'operario') DEFAULT 'operario'");
    $stmt->execute();
    echo "Migration completed successfully: role column added to users table.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}