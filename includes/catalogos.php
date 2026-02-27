<?php
/**
 * Helper para cargar catálogos dinámicos desde la BD.
 * Usado por las vistas web para llenar selects dinámicamente.
 */
require_once __DIR__ . '/db.php';

/**
 * Obtiene ítems activos de un catálogo.
 * @param string $catalog Nombre del catálogo (tipos_mantenimiento, estados_vehiculo, etc.)
 * @return array Lista de registros activos [['id'=>..., 'nombre'=>...], ...]
 */
function catalogo_items(string $catalog): array {
    $map = [
        'tipos_mantenimiento'  => 'catalogo_tipos_mantenimiento',
        'estados_vehiculo'     => 'catalogo_estados_vehiculo',
        'categorias_gasto'     => 'catalogo_categorias_gasto',
        'unidades'             => 'catalogo_unidades',
        'servicios_taller'     => 'catalogo_servicios_taller',
    ];
    $table = $map[$catalog] ?? null;
    if (!$table) return [];
    try {
        $db = getDB();
        $stmt = $db->query("SELECT id, nombre FROM {$table} WHERE activo=1 ORDER BY nombre ASC");
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log("catalogo_items({$catalog}): " . $e->getMessage());
        return [];
    }
}
