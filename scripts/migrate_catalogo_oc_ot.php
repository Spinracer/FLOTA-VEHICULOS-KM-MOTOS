<?php
/**
 * Migración: Catálogo OC↔OT
 * - Crea tabla orden_compra_items
 * - Agrega orden_compra_id a mantenimientos
 * Idempotente: seguro de ejecutar múltiples veces.
 */
require_once __DIR__ . '/../includes/db.php';

$db = getDB();
$ok = 0;
$skip = 0;

echo "=== Migración: Catálogo OC↔OT ===\n\n";

// 1. Tabla orden_compra_items
echo "1. Tabla orden_compra_items... ";
$db->exec("
    CREATE TABLE IF NOT EXISTS orden_compra_items (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        orden_compra_id  INT NOT NULL,
        descripcion      VARCHAR(255) NOT NULL,
        cantidad         DECIMAL(10,2) NOT NULL DEFAULT 1,
        unidad           VARCHAR(20) NOT NULL DEFAULT 'PZA',
        precio_unitario  DECIMAL(10,2) NOT NULL DEFAULT 0,
        subtotal         DECIMAL(12,2) GENERATED ALWAYS AS (cantidad * precio_unitario) STORED,
        notas            TEXT NULL,
        component_id     INT NULL,
        created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_oci_oc (orden_compra_id),
        CONSTRAINT fk_oci_oc FOREIGN KEY (orden_compra_id) REFERENCES ordenes_compra(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "OK\n";
$ok++;

// 2. Columna mantenimientos.orden_compra_id
echo "2. Columna mantenimientos.orden_compra_id... ";
$cols = $db->query("SHOW COLUMNS FROM mantenimientos LIKE 'orden_compra_id'")->fetchAll();
if (empty($cols)) {
    $db->exec("ALTER TABLE mantenimientos ADD COLUMN orden_compra_id INT NULL COMMENT 'OC vinculada'");
    $db->exec("ALTER TABLE mantenimientos ADD INDEX idx_mant_oc (orden_compra_id)");
    echo "CREADA\n";
    $ok++;
} else {
    echo "ya existe, skip\n";
    $skip++;
}

// 3. FK mantenimientos → ordenes_compra (si no existe)
echo "3. FK mantenimientos.orden_compra_id... ";
$fks = $db->query("
    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mantenimientos' AND COLUMN_NAME = 'orden_compra_id' AND REFERENCED_TABLE_NAME IS NOT NULL
")->fetchAll();
if (empty($fks)) {
    try {
        $db->exec("ALTER TABLE mantenimientos ADD CONSTRAINT fk_mant_oc FOREIGN KEY (orden_compra_id) REFERENCES ordenes_compra(id) ON DELETE SET NULL");
        echo "CREADA\n";
        $ok++;
    } catch (Throwable $e) {
        echo "skip (ya existe o error: " . $e->getMessage() . ")\n";
        $skip++;
    }
} else {
    echo "ya existe, skip\n";
    $skip++;
}

echo "\n=== Migración completada: {$ok} creados, {$skip} omitidos ===\n";
