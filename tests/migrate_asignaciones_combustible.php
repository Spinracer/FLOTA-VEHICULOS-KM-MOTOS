<?php
// Migration: add combustible status fields to asignaciones table
require __DIR__.'/../includes/db.php';
$pdo = getDB();

$newCols = [
  'start_combustible' => "VARCHAR(40) NULL",
  'end_combustible'   => "VARCHAR(40) NULL",
];

foreach ($newCols as $col => $def) {
    try {
        $exists = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='asignaciones' AND COLUMN_NAME='{$col}'")->fetchColumn();
        if (!$exists) {
            $pdo->exec("ALTER TABLE asignaciones ADD COLUMN {$col} {$def}");
            echo "✅ Added: {$col}\n";
        } else {
            echo "⏭️  Exists: {$col}\n";
        }
    } catch (Throwable $e) {
        echo "❌ Error {$col}: {$e->getMessage()}\n";
    }
}

echo "\nDone.\n";
