<?php
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

// Add firma_entrega_token column
try {
    $db->exec("ALTER TABLE asignaciones ADD COLUMN firma_entrega_token VARCHAR(128) NULL");
    echo "firma_entrega_token: ADDED\n";
} catch (Throwable $e) {
    echo "firma_entrega_token: " . $e->getMessage() . "\n";
}

// Create vehicle_checklist_items table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS vehicle_checklist_items (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        vehiculo_id   INT NOT NULL,
        label         VARCHAR(120) NOT NULL,
        requerido     TINYINT(1) NOT NULL DEFAULT 0,
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_vci_vehiculo (vehiculo_id),
        FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "vehicle_checklist_items: OK\n";
} catch (Throwable $e) {
    echo "vehicle_checklist_items: " . $e->getMessage() . "\n";
}
echo "DONE\n";
