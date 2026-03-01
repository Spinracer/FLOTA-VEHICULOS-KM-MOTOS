<?php
/**
 * Generador de vistas de impresión / PDF.
 * Uso: /print.php?type=asignacion&id=123
 *       /print.php?type=combustible&id=456
 *       /print.php?type=combustible_lote&ids=1,2,3
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_login();

$db   = getDB();
$type = trim($_GET['type'] ?? '');
$id   = (int)($_GET['id'] ?? 0);

// ═══════════════════════════════════════════════════════
// Data fetching based on type
// ═══════════════════════════════════════════════════════
$title   = 'Documento';
$content = '';
$folio   = '';
$fecha   = date('Y-m-d H:i');

switch ($type) {

// ─── PDF ASIGNACIÓN (ENTREGA/RETORNO) ───────────────
case 'asignacion':
    if ($id <= 0) die('ID de asignación requerido.');
    $stmt = $db->prepare("
        SELECT a.*, v.placa, v.marca, v.modelo, v.anio, v.color, v.km_actual, v.vin,
               o.nombre AS operador_nombre, o.licencia, o.telefono, o.categoria_lic
        FROM asignaciones a
        LEFT JOIN vehiculos v ON v.id = a.vehiculo_id
        LEFT JOIN operadores o ON o.id = a.operador_id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $a = $stmt->fetch();
    if (!$a) die('Asignación no encontrada.');

    $title = 'Acta de ' . ($a['end_at'] ? 'Retorno' : 'Entrega') . ' de Vehículo';
    $folio = 'ASG-' . str_pad($id, 6, '0', STR_PAD_LEFT);
    $momento = $a['end_at'] ? 'retorno' : 'entrega';

    // Snapshots
    $stSnap = $db->prepare("SELECT * FROM assignment_component_snapshots WHERE asignacion_id = ? AND momento = ? ORDER BY componente_tipo, componente_nombre");
    $stSnap->execute([$id, $momento]);
    $snaps = $stSnap->fetchAll();

    $content .= '<div class="section"><h3>Datos del Vehículo</h3>';
    $content .= '<table class="info-table"><tbody>';
    $content .= "<tr><td><strong>Placa:</strong></td><td>{$a['placa']}</td><td><strong>Marca/Modelo:</strong></td><td>{$a['marca']} {$a['modelo']} {$a['anio']}</td></tr>";
    $content .= "<tr><td><strong>Color:</strong></td><td>" . ($a['color'] ?? '—') . "</td><td><strong>VIN:</strong></td><td>" . ($a['vin'] ?? '—') . "</td></tr>";
    $content .= "<tr><td><strong>KM Actual:</strong></td><td>" . number_format((float)$a['km_actual'], 0) . " km</td><td><strong>Estado:</strong></td><td>" . ($a['estado'] ?? '—') . "</td></tr>";
    $content .= '</tbody></table></div>';

    $content .= '<div class="section"><h3>Datos del Operador</h3>';
    $content .= '<table class="info-table"><tbody>';
    $content .= "<tr><td><strong>Nombre:</strong></td><td>{$a['operador_nombre']}</td><td><strong>Licencia:</strong></td><td>" . ($a['licencia'] ?? '—') . " ({$a['categoria_lic']})</td></tr>";
    $content .= "<tr><td><strong>Teléfono:</strong></td><td>" . ($a['telefono'] ?? '—') . "</td><td></td><td></td></tr>";
    $content .= '</tbody></table></div>';

    $content .= '<div class="section"><h3>Datos de la Asignación</h3>';
    $content .= '<table class="info-table"><tbody>';
    $content .= "<tr><td><strong>Folio:</strong></td><td>{$folio}</td><td><strong>Inicio:</strong></td><td>{$a['start_at']}</td></tr>";
    $content .= "<tr><td><strong>Fin:</strong></td><td>" . ($a['end_at'] ?? 'Vigente') . "</td><td><strong>KM Inicio:</strong></td><td>" . number_format((float)($a['start_km'] ?? 0), 0) . " km</td></tr>";
    if ($a['end_at']) {
        $content .= "<tr><td><strong>KM Retorno:</strong></td><td>" . number_format((float)($a['end_km'] ?? 0), 0) . " km</td><td><strong>KM Recorridos:</strong></td><td>" . number_format(((float)($a['end_km'] ?? 0) - (float)($a['start_km'] ?? 0)), 0) . " km</td></tr>";
    }
    $content .= "<tr><td><strong>Notas:</strong></td><td colspan=\"3\">" . htmlspecialchars($a['notas'] ?? '—') . "</td></tr>";
    $content .= '</tbody></table></div>';

    if (count($snaps)) {
        $content .= '<div class="section"><h3>Checklist de Componentes (' . ucfirst($momento) . ')</h3>';
        $content .= '<table class="data-table"><thead><tr><th>#</th><th>Componente</th><th>Tipo</th><th>Cantidad</th><th>N° Serie</th><th>Estado</th><th>Observaciones</th></tr></thead><tbody>';
        $n = 1;
        foreach ($snaps as $s) {
            $content .= "<tr><td>{$n}</td><td>{$s['componente_nombre']}</td><td>{$s['componente_tipo']}</td><td>{$s['cantidad']}</td><td>" . ($s['numero_serie'] ?? '—') . "</td><td>{$s['estado']}</td><td>" . htmlspecialchars($s['observaciones'] ?? '') . "</td></tr>";
            $n++;
        }
        $content .= '</tbody></table></div>';
    }

    // Signature block
    $content .= '<div class="signatures">';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Entrega:</strong> ' . htmlspecialchars($a['operador_nombre']) . '</p><p>Operador / Conductor</p></div>';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Recibe:</strong> Responsable de Flota</p><p>Coordinación</p></div>';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Vo.Bo.:</strong> Administración</p><p>Gerencia</p></div>';
    $content .= '</div>';
    break;

// ─── PDF AUTORIZACIÓN COMBUSTIBLE ───────────────────
case 'combustible':
    if ($id <= 0) die('ID de carga requerido.');
    $stmt = $db->prepare("
        SELECT c.*, v.placa, v.marca, v.modelo,
               o.nombre AS operador_nombre, o.licencia,
               p.nombre AS proveedor_nombre
        FROM combustible c
        LEFT JOIN vehiculos v ON v.id = c.vehiculo_id
        LEFT JOIN operadores o ON o.id = c.operador_id
        LEFT JOIN proveedores p ON p.id = c.proveedor_id
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    $c = $stmt->fetch();
    if (!$c) die('Registro de combustible no encontrado.');

    $title = 'Autorización de Carga de Combustible';
    $folio = 'COMB-' . str_pad($id, 6, '0', STR_PAD_LEFT);

    $content .= '<div class="section"><h3>Datos del Vehículo</h3>';
    $content .= '<table class="info-table"><tbody>';
    $content .= "<tr><td><strong>Placa:</strong></td><td>{$c['placa']}</td><td><strong>Vehículo:</strong></td><td>{$c['marca']} {$c['modelo']}</td></tr>";
    $content .= "<tr><td><strong>KM al cargar:</strong></td><td>" . number_format((float)($c['km'] ?? 0), 0) . " km</td><td><strong>Conductor:</strong></td><td>{$c['operador_nombre']}</td></tr>";
    $content .= '</tbody></table></div>';

    $content .= '<div class="section"><h3>Detalle de Carga</h3>';
    $content .= '<table class="info-table"><tbody>';
    $content .= "<tr><td><strong>Folio:</strong></td><td>{$folio}</td><td><strong>Fecha:</strong></td><td>{$c['fecha']}</td></tr>";
    $content .= "<tr><td><strong>Litros:</strong></td><td>" . number_format((float)$c['litros'], 2) . " L</td><td><strong>Costo/L:</strong></td><td>$" . number_format((float)$c['costo_litro'], 2) . "</td></tr>";
    $content .= "<tr><td><strong>Total:</strong></td><td><strong>$" . number_format((float)$c['total'], 2) . "</strong></td><td><strong>Tipo:</strong></td><td>" . ($c['tipo_carga'] ?? 'Lleno') . "</td></tr>";
    $content .= "<tr><td><strong>Proveedor:</strong></td><td>" . ($c['proveedor_nombre'] ?? '—') . "</td><td><strong>No. Recibo:</strong></td><td>" . ($c['numero_recibo'] ?? '—') . "</td></tr>";
    $content .= "<tr><td><strong>Método pago:</strong></td><td>" . ($c['metodo_pago'] ?? '—') . "</td><td><strong>Licencia:</strong></td><td>" . ($c['licencia'] ?? '—') . "</td></tr>";
    if ($c['notas']) {
        $content .= "<tr><td><strong>Notas:</strong></td><td colspan=\"3\">" . htmlspecialchars($c['notas']) . "</td></tr>";
    }
    $content .= '</tbody></table></div>';

    $content .= '<div class="signatures">';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>' . htmlspecialchars($c['operador_nombre']) . '</strong></p><p>Conductor</p></div>';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Responsable de Flota</strong></p><p>Autorización</p></div>';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Contabilidad</strong></p><p>Vo.Bo.</p></div>';
    $content .= '</div>';
    break;

// ─── PDF LOTE COMBUSTIBLE ───────────────────────────
case 'combustible_lote':
    $ids = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));
    if (empty($ids)) die('IDs de cargas requeridos.');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("
        SELECT c.*, v.placa, v.marca, v.modelo,
               o.nombre AS operador_nombre,
               p.nombre AS proveedor_nombre
        FROM combustible c
        LEFT JOIN vehiculos v ON v.id = c.vehiculo_id
        LEFT JOIN operadores o ON o.id = c.operador_id
        LEFT JOIN proveedores p ON p.id = c.proveedor_id
        WHERE c.id IN ({$placeholders})
        ORDER BY c.fecha ASC
    ");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    if (!$rows) die('No se encontraron registros.');

    $title = 'Autorización de Cargas - Lote';
    $folio = 'LOTE-' . date('Ymd-His');
    $totalLitros = 0;
    $totalGasto  = 0;

    $content .= '<div class="section">';
    $content .= '<table class="data-table"><thead><tr><th>#</th><th>Fecha</th><th>Vehículo</th><th>Conductor</th><th>Litros</th><th>Costo/L</th><th>Total</th><th>Proveedor</th><th>Recibo</th></tr></thead><tbody>';
    $n = 1;
    foreach ($rows as $r) {
        $totalLitros += (float)$r['litros'];
        $totalGasto  += (float)$r['total'];
        $content .= "<tr><td>{$n}</td><td>{$r['fecha']}</td><td>{$r['placa']} {$r['marca']}</td><td>{$r['operador_nombre']}</td><td>" . number_format((float)$r['litros'], 2) . "</td><td>$" . number_format((float)$r['costo_litro'], 2) . "</td><td>$" . number_format((float)$r['total'], 2) . "</td><td>" . ($r['proveedor_nombre'] ?? '—') . "</td><td>" . ($r['numero_recibo'] ?? '—') . "</td></tr>";
        $n++;
    }
    $content .= "<tr class=\"total-row\"><td colspan=\"4\"><strong>TOTALES ({$n} registros)</strong></td><td><strong>" . number_format($totalLitros, 2) . " L</strong></td><td></td><td><strong>$" . number_format($totalGasto, 2) . "</strong></td><td colspan=\"2\"></td></tr>";
    $content .= '</tbody></table></div>';

    $content .= '<div class="signatures">';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Elaboró</strong></p><p>Responsable de Flota</p></div>';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Autorizó</strong></p><p>Coordinación</p></div>';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Vo.Bo.</strong></p><p>Contabilidad</p></div>';
    $content .= '</div>';
    break;

default:
    die('Tipo de documento no soportado: ' . htmlspecialchars($type));
}

$generadoPor = htmlspecialchars(current_user()['nombre'] ?? 'Sistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($title) ?> — <?= $folio ?></title>
<style>
  @page { size: letter; margin: 15mm 12mm; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #1a1a1a; background: #fff; line-height: 1.5; }
  .page { max-width: 760px; margin: 0 auto; padding: 20px; }

  /* Header */
  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1a1a1a; padding-bottom: 12px; margin-bottom: 16px; }
  .header-left h1 { font-size: 18px; font-weight: 700; margin-bottom: 2px; }
  .header-left .subtitle { font-size: 12px; color: #555; }
  .header-right { text-align: right; font-size: 11px; color: #555; }
  .header-right .folio { font-size: 14px; font-weight: 700; color: #1a1a1a; }

  /* Sections */
  .section { margin-bottom: 16px; }
  .section h3 { font-size: 13px; background: #1a1a1a; color: #fff; padding: 4px 10px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }

  /* Info tables (key-value) */
  .info-table { width: 100%; border-collapse: collapse; }
  .info-table td { padding: 4px 8px; border: 1px solid #ddd; font-size: 11px; }
  .info-table td:nth-child(odd) { width: 18%; background: #f5f5f5; }

  /* Data tables */
  .data-table { width: 100%; border-collapse: collapse; font-size: 11px; }
  .data-table th { background: #333; color: #fff; padding: 5px 6px; text-align: left; font-weight: 600; }
  .data-table td { padding: 4px 6px; border: 1px solid #ddd; }
  .data-table tr:nth-child(even) { background: #f9f9f9; }
  .total-row td { background: #eee; border-top: 2px solid #333; }

  /* Signatures */
  .signatures { display: flex; justify-content: space-between; margin-top: 50px; gap: 20px; }
  .sig-block { flex: 1; text-align: center; }
  .sig-line { border-top: 1px solid #333; margin: 0 20px 8px; padding-top: 40px; }
  .sig-block p { font-size: 11px; margin: 0; }

  /* Footer */
  .footer { margin-top: 24px; border-top: 1px solid #ccc; padding-top: 8px; display: flex; justify-content: space-between; font-size: 10px; color: #888; }

  /* Print specific */
  @media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none !important; }
    .page { padding: 0; }
  }
  @media screen {
    body { background: #e0e0e0; padding: 20px; }
    .page { background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.15); border-radius: 4px; padding: 30px; }
  }

  /* Print button bar */
  .print-bar { position: fixed; top: 0; left: 0; right: 0; background: #1a1a1a; color: #fff; padding: 8px 20px; display: flex; justify-content: space-between; align-items: center; z-index: 100; font-size: 13px; }
  .print-bar button { background: #e8ff47; color: #000; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer; font-weight: 700; font-size: 13px; }
  .print-bar button:hover { background: #d4eb3c; }
</style>
</head>
<body>
<div class="print-bar no-print">
  <span><?= htmlspecialchars($title) ?> — <?= $folio ?></span>
  <div>
    <button onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
    <button onclick="window.close()" style="background:#555;color:#fff;margin-left:8px">✕ Cerrar</button>
  </div>
</div>
<div class="page" style="margin-top:50px">
  <div class="header">
    <div class="header-left">
      <h1>FlotaCtrl</h1>
      <div class="subtitle"><?= htmlspecialchars($title) ?></div>
    </div>
    <div class="header-right">
      <div class="folio"><?= $folio ?></div>
      <div>Generado: <?= $fecha ?></div>
      <div>Por: <?= $generadoPor ?></div>
    </div>
  </div>

  <?= $content ?>

  <div class="footer">
    <span>Documento generado por FlotaCtrl — Sistema de Gestión de Flota</span>
    <span><?= $folio ?> — <?= $fecha ?></span>
  </div>
</div>
</body>
</html>
