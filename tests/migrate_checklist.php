<?php
// Migration: add new checklist columns to asignaciones table
require __DIR__.'/../includes/db.php';
$pdo = getDB();

$newCols = [
  'checklist_luces'        => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_liquidos'     => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_motor'        => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_parabrisas'   => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_documentacion'=> "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_frenos'       => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_espejos'      => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_luces'        => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_liquidos'     => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_motor'        => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_parabrisas'   => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_documentacion'=> "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_frenos'       => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_espejos'      => "TINYINT(1) NOT NULL DEFAULT 0",
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
