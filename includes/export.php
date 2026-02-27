<?php
/**
 * Motor de exportación de reportes (CSV).
 * Genera CSV directamente al output para descarga del navegador.
 */

/**
 * Envía cabeceras HTTP para descarga CSV y escribe las filas.
 * @param string $filename Nombre del archivo .csv
 * @param array $headers Cabeceras de columnas ['Fecha', 'Placa', ...]
 * @param array $rows Filas de datos [['2026-01-01', 'ABC-123', ...], ...]
 */
function export_csv(string $filename, array $headers, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // BOM para Excel
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

/**
 * Construye WHERE con parámetros comunes de filtrado.
 * @return array [string $whereSql, array $params]
 */
function report_build_filters(array $filters, string $dateColumn = 'fecha', string $tableAlias = ''): array {
    $where = [];
    $params = [];
    $prefix = $tableAlias ? $tableAlias . '.' : '';

    if (!empty($filters['from'])) {
        $where[] = "{$prefix}{$dateColumn} >= ?";
        $params[] = $filters['from'];
    }
    if (!empty($filters['to'])) {
        $where[] = "{$prefix}{$dateColumn} <= ?";
        $params[] = $filters['to'];
    }
    if (!empty($filters['vehiculo_id'])) {
        $where[] = "{$prefix}vehiculo_id = ?";
        $params[] = (int)$filters['vehiculo_id'];
    }

    $sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    return [$sql, $params];
}
