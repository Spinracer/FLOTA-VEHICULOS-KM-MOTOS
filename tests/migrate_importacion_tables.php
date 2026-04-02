<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * MIGRACIÓN: Tablas de Importación (import_runs)
 * ═══════════════════════════════════════════════════════════════
 * 
 * Crea la tabla `import_runs` necesaria para rastrear importaciones.
 * 
 * Esta tabla es CRÍTICA para que funcione:
 *  - api/importacion_vehiculos.php
 *  - modules/api/importacion_operadores.php
 * 
 * Uso:
 *   Local:  php tests/migrate_importacion_tables.php
 *   Docker: docker exec flotacontrol-app php /var/www/html/tests/migrate_importacion_tables.php
 * 
 * Status: SEGURA (idempotente - puede ejecutarse múltiples veces)
 */

require_once __DIR__ . '/../includes/db.php';

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  MIGRACIÓN: Tablas de Importación (import_runs)                ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

try {
    $db = getDB();
    
    echo "⏳ Verificando tabla 'import_runs'...\n";
    
    // Verificar si la tabla ya existe
    $checkStmt = $db->prepare("
        SELECT COUNT(*) as cnt 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'import_runs'
    ");
    $checkStmt->execute();
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['cnt'] > 0) {
        echo "✅ Tabla 'import_runs' ya existe.\n";
        
        // Verificar estructura
        $structStmt = $db->prepare("DESCRIBE import_runs");
        $structStmt->execute();
        $columns = $structStmt->fetchAll(PDO::FETCH_COLUMN, 0);
        echo "   Columnas: " . implode(', ', $columns) . "\n";
    } else {
        echo "🔨 Creando tabla 'import_runs'...\n";
        
        $db->exec("CREATE TABLE import_runs (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            tipo_importacion    VARCHAR(50) NOT NULL DEFAULT 'vehiculos' COMMENT 'vehiculos, operadores, etc.',
            nombre_archivo      VARCHAR(255) NOT NULL,
            usuario_id          INT NOT NULL,
            total_filas         INT NOT NULL DEFAULT 0,
            creados             INT NOT NULL DEFAULT 0,
            actualizados        INT NOT NULL DEFAULT 0,
            errores             INT NOT NULL DEFAULT 0,
            detalle_errores     JSON NULL COMMENT 'Array de objetos con fila, tipo, errores, placa',
            estado              ENUM('procesando', 'completado', 'fallido') NOT NULL DEFAULT 'procesando',
            created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at        DATETIME NULL,
            
            INDEX idx_tipo (tipo_importacion),
            INDEX idx_usuario (usuario_id),
            INDEX idx_estado (estado),
            INDEX idx_created (created_at),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        echo "✅ Tabla 'import_runs' creada exitosamente.\n";
    }
    
    // Verificar directorio de uploads
    echo "\n⏳ Verificando directorios de upload...\n";
    $dirs = [
        __DIR__ . '/../uploads/importaciones',
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
            echo "✅ Directorio creado: $dir\n";
        } else {
            echo "✅ Directorio existe: $dir\n";
        }
    }
    
    // Resumen
    echo "\n╔════════════════════════════════════════════════════════════════╗\n";
    echo "║ ✅ MIGRACIÓN COMPLETADA EXITOSAMENTE                          ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    echo "Los siguientes módulos ya están listos:\n";
    echo "  ✓ POST api/importacion_vehiculos.php?action=import\n";
    echo "  ✓ POST api/importacion_operadores.php?action=import\n\n";
    
    exit(0);
    
} catch (Throwable $e) {
    echo "\n╔════════════════════════════════════════════════════════════════╗\n";
    echo "║ ❌ ERROR EN LA MIGRACIÓN                                       ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n\n";
    exit(1);
}
