<?php
/**
 * Migración: Agregar columna de correlativo a asignaciones
 * Propósito: Guardar el correlativo correlacionado con mes/año para búsquedas más rápidas
 * 
 * Uso: php tests/migrate_correlativos.php
 * O en Docker: docker exec flotacontrol-app php /var/www/html/tests/migrate_correlativos.php
 */

require_once __DIR__ . '/../includes/db.php';

try {
    $db = getDB();
    
    // Agregar columna si no existe
    $checkCol = $db->query("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'asignaciones' 
        AND COLUMN_NAME = 'correlativo'
    ");
    
    if ($checkCol->rowCount() === 0) {
        echo "📝 Agregando columna 'correlativo' a tabla asignaciones...\n";
        $db->exec("
            ALTER TABLE asignaciones 
            ADD COLUMN correlativo VARCHAR(50) UNIQUE AFTER id,
            ADD INDEX idx_correlativo (correlativo),
            ADD INDEX idx_start_at (start_at)
        ");
        echo "✅ Columna agregada exitosamente.\n";
    } else {
        echo "ℹ️  La columna 'correlativo' ya existe.\n";
    }
    
    // Generar y guardar correlativos para asignaciones existentes (sin correlativo)
    echo "\n📊 Procesando asignaciones existentes...\n";
    $stmt = $db->prepare("
        SELECT id, start_at FROM asignaciones 
        WHERE correlativo IS NULL 
        ORDER BY start_at ASC
    ");
    $stmt->execute();
    $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($asignaciones)) {
        echo "ℹ️  No hay asignaciones para procesar.\n";
    } else {
        // Agrupar por mes/año
        $porMes = [];
        $mesesEsp = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                     'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        
        foreach ($asignaciones as $asg) {
            $fecha = new DateTime($asg['start_at']);
            $mesNum = (int)$fecha->format('m');
            $anio = $fecha->format('Y');
            $mesKey = $anio . '-' . $mesNum;
            
            if (!isset($porMes[$mesKey])) {
                $porMes[$mesKey] = [];
            }
            $porMes[$mesKey][] = $asg['id'];
        }
        
        // Guardar correlativos
        $updateStmt = $db->prepare("UPDATE asignaciones SET correlativo = ? WHERE id = ?");
        $processed = 0;
        
        foreach ($porMes as $mesKey => $ids) {
            [$anio, $mesNum] = explode('-', $mesKey);
            $mesNombre = strtoupper($mesesEsp[(int)$mesNum - 1]);
            
            foreach ($ids as $index => $id) {
                $numeroSeq = str_pad($index + 1, 3, '0', STR_PAD_LEFT);
                $correlativo = 'ASG-' . $mesNombre . '-' . $anio . '-' . $numeroSeq;
                $updateStmt->execute([$correlativo, $id]);
                $processed++;
            }
        }
        
        echo "✅ Actualizados $processed correlativos.\n";
    }
    
    echo "\n🎉 Migración completada exitosamente.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
