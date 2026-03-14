<?php
/**
 * Motor de exportación de reportes (CSV, XLSX, PDF).
 * Genera archivos directamente al output para descarga del navegador.
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
 * Exporta datos como archivo XLSX (tabla HTML con MIME de Excel).
 * Compatible con Excel, LibreOffice Calc y Google Sheets.
 * @param string $filename Nombre del archivo .xlsx
 * @param array $headers Cabeceras de columnas
 * @param array $rows Filas de datos
 * @param string $title Título del reporte (opcional)
 */
function export_xlsx(string $filename, array $headers, array $rows, string $title = 'Reporte'): void {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $esc = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

    echo '<!DOCTYPE html><html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    echo '<x:Name>' . $esc($title) . '</x:Name>';
    echo '<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>';
    echo '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>
        td, th { mso-number-format:\@; font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
        th { background-color: #1a1f2e; color: #47ffe8; font-weight: bold; padding: 6px 10px; text-align: left; }
        td { padding: 4px 10px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) td { background-color: #f5f5f5; }
        .title { font-size: 16pt; font-weight: bold; color: #1a1f2e; padding: 8px 0; }
        .meta { font-size: 9pt; color: #666; padding-bottom: 10px; }
    </style></head><body>';
    echo '<div class="title">' . $esc($title) . ' — FlotaControl</div>';
    echo '<div class="meta">Generado: ' . date('d/m/Y H:i:s') . '</div>';
    echo '<table border="1" cellpadding="4" cellspacing="0">';
    echo '<thead><tr>';
    foreach ($headers as $h) {
        echo '<th>' . $esc($h) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . $esc($cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
}

/**
 * Exporta datos como PDF (página HTML con estilos de impresión y auto-print).
 * Abre vista previa de impresión en el navegador → el usuario guarda como PDF.
 * @param string $filename Nombre sugerido (para referencia, usando Content-Disposition inline)
 * @param array $headers Cabeceras de columnas
 * @param array $rows Filas de datos
 * @param string $title Título del reporte
 * @param array $totals Fila de totales opcional ['label' => 'Total', 'values' => ['','','L 1,234.00',...]]
 */
function export_pdf(string $filename, array $headers, array $rows, string $title = 'Reporte', array $totals = []): void {
    header('Content-Type: text/html; charset=utf-8');
    // No forzamos descarga, se abre en navegador para imprimir/guardar como PDF
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $esc = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
    $totalRows = count($rows);

    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">';
    echo '<title>' . $esc($title) . ' — FlotaControl</title>';
    echo '<style>
        @page { size: landscape; margin: 12mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; font-size: 10pt; color: #1a1a2e; background: #fff; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #1a1f2e; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { font-size: 18pt; color: #1a1f2e; }
        .header .logo { font-size: 13pt; color: #47ffe8; background: #1a1f2e; padding: 6px 14px; border-radius: 6px; font-weight: bold; }
        .meta { font-size: 8pt; color: #666; margin-bottom: 12px; }
        .meta span { margin-right: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        th { background: #1a1f2e; color: #fff; padding: 6px 8px; text-align: left; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e0e0e0; }
        tr:nth-child(even) td { background: #f8f9fa; }
        tr:hover td { background: #e8f4fd; }
        .footer { margin-top: 15px; padding-top: 8px; border-top: 1px solid #ccc; font-size: 7pt; color: #999; display: flex; justify-content: space-between; }
        .summary { background: #f0f4ff; border: 1px solid #d0d8f0; border-radius: 6px; padding: 8px 14px; margin-bottom: 12px; font-size: 9pt; }
        .btn-bar { display: flex; gap: 8px; margin-bottom: 15px; }
        .btn-bar button { padding: 8px 18px; border: none; border-radius: 6px; cursor: pointer; font-size: 10pt; font-weight: 600; }
        .btn-print { background: #1a1f2e; color: #47ffe8; }
        .btn-close { background: #e0e0e0; color: #333; }
        @media print {
            .btn-bar { display: none !important; }
            body { padding: 0; }
        }
    </style></head><body>';

    // Barra de acciones (visible en pantalla, oculta al imprimir)
    echo '<div class="btn-bar">';
    echo '<button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>';
    echo '<button class="btn-close" onclick="window.close()">✖ Cerrar</button>';
    echo '</div>';

    // Encabezado
    echo '<div class="header">';
    echo '<h1>' . $esc($title) . '</h1>';
    echo '<div class="logo">FlotaControl</div>';
    echo '</div>';

    // Metadatos
    echo '<div class="meta">';
    echo '<span>📅 Fecha: ' . date('d/m/Y H:i') . '</span>';
    echo '<span>📋 Registros: ' . $totalRows . '</span>';
    echo '<span>📄 ' . $esc($filename) . '</span>';
    echo '</div>';

    // Resumen
    echo '<div class="summary">Total de registros exportados: <strong>' . $totalRows . '</strong></div>';

    // Tabla
    echo '<table><thead><tr>';
    foreach ($headers as $h) {
        echo '<th>' . $esc($h) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . $esc($cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';

    // Fila de totales monetarios (si se proporcionan)
    if (!empty($totals)) {
        echo '<table style="width:100%;border-collapse:collapse;margin-top:12px;font-size:10pt">';
        echo '<tr style="background:#f0f4ff;font-weight:bold;border:2px solid #1a1f2e">';
        $vals = $totals['values'] ?? [];
        $label = $totals['label'] ?? 'Total';
        foreach ($headers as $i => $h) {
            $v = $vals[$i] ?? '';
            if ($i === 0) $v = $label;
            echo '<td style="padding:8px 10px;' . ($v ? 'font-weight:bold;' : '') . '">' . $esc($v) . '</td>';
        }
        echo '</tr></table>';
    }

    // Pie de página
    echo '<div class="footer">';
    echo '<span>FlotaControl — Sistema de Gestión de Flota Vehicular</span>';
    echo '<span>Generado: ' . date('d/m/Y H:i:s') . '</span>';
    echo '</div>';

    echo '<script>
        // Auto-abrir diálogo de impresión si se solicita
        if (window.location.search.includes("autoprint=1")) {
            window.addEventListener("load", () => setTimeout(() => window.print(), 400));
        }
    </script>';
    echo '</body></html>';
    exit;
}

/**
 * Despacha la exportación al formato correcto según el parámetro format.
 * @param string $format csv|xlsx|pdf
 * @param string $baseFilename Nombre base sin extensión
 * @param array $headers Cabeceras
 * @param array $rows Datos
 * @param string $title Título del reporte
 * @param array $totals Totales opcionales para PDF
 */
function export_dispatch(string $format, string $baseFilename, array $headers, array $rows, string $title = 'Reporte', array $totals = []): void {
    $ts = date('Ymd_His');
    switch ($format) {
        case 'xlsx':
            export_xlsx("{$baseFilename}_{$ts}.xlsx", $headers, $rows, $title);
            break;
        case 'pdf':
            export_pdf("{$baseFilename}_{$ts}.pdf", $headers, $rows, $title, $totals);
            break;
        case 'csv':
        default:
            export_csv("{$baseFilename}_{$ts}.csv", $headers, $rows);
            break;
    }
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
