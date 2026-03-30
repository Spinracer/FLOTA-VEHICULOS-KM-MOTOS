<?php
/**
 * Migración segura — Importación de Vehículos V1
 *
 * Crea la tabla import_runs para auditoría de importaciones.
 * Es IDEMPOTENTE: puede ejecutarse múltiples veces sin romper nada.
 *
 * Uso local:     php scripts/migrate_importacion_vehiculos.php
 * Uso Docker:    docker exec flotacontrol-app php /var/www/html/scripts/migrate_importacion_vehiculos.php
 */

// Cargar configuración de BD
require_once __DIR__ . '/../includes/db.php';

echo "=== Migración: Importación de Vehículos V1 ===\n\n";

try {
    $db = getDB();

    // ── Tabla import_runs ──
    echo "[1/2] Creando tabla import_runs (si no existe)...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS import_runs (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        tipo_importacion VARCHAR(50)  NOT NULL DEFAULT 'vehiculos',
        nombre_archivo  VARCHAR(255) NOT NULL,
        usuario_id      INT          NOT NULL,
        total_filas     INT          NOT NULL DEFAULT 0,
        creados         INT          NOT NULL DEFAULT 0,
        errores         INT          NOT NULL DEFAULT 0,
        detalle_errores JSON         NULL,
        estado          ENUM('procesando','completado','fallido') NOT NULL DEFAULT 'procesando',
        created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at    DATETIME     NULL,
        INDEX idx_import_runs_usuario (usuario_id),
        INDEX idx_import_runs_created (created_at),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "    OK\n";

    // ── Directorio temporal para uploads de importación ──
    echo "[2/2] Verificando directorio uploads/importaciones...\n";
    $importDir = __DIR__ . '/../uploads/importaciones';
    if (!is_dir($importDir)) {
        mkdir($importDir, 0775, true);
        echo "    Directorio creado\n";
    } else {
        echo "    Ya existe\n";
    }

    echo "\n=== Migración completada exitosamente ===\n";
    exit(0);

} catch (Throwable $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
