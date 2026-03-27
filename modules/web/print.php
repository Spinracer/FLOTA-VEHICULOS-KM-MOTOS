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
$title    = 'Documento';
$subtitle = '';
$content  = '';
$folio    = '';
$fecha    = date('Y-m-d H:i');

switch ($type) {

// ─── PDF ASIGNACIÓN (ENTREGA/RETORNO) ───────────────
case 'asignacion':
    if ($id <= 0) die('ID de asignación requerido.');
    $stmt = $db->prepare("
        SELECT a.*, v.placa, v.marca, v.modelo, v.anio, v.color, v.km_actual, v.vin,
               o.nombre AS operador_nombre, o.licencia, o.telefono, o.categoria_lic, o.dni,
               dep.nombre AS departamento_nombre
        FROM asignaciones a
        LEFT JOIN vehiculos v ON v.id = a.vehiculo_id
        LEFT JOIN operadores o ON o.id = a.operador_id
        LEFT JOIN departamentos dep ON dep.id = o.departamento_id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $a = $stmt->fetch();
    if (!$a) die('Asignación no encontrada.');

    $title = 'Acta de Inspección y Asignación';
    $subtitle = 'Control de Flota Vehicular | Inspección Pre-Salida';
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
    $content .= "<tr><td><strong>Teléfono:</strong></td><td>" . ($a['telefono'] ?? '—') . "</td><td><strong>DNI:</strong></td><td>" . ($a['dni'] ?? '—') . "</td></tr>";
    $content .= "<tr><td><strong>Departamento:</strong></td><td colspan=\"3\">" . ($a['departamento_nombre'] ?? '—') . "</td></tr>";
    $content .= '</tbody></table></div>';

    $content .= '<div class="section"><h3>Datos de la Asignación</h3>';
    $content .= '<table class="info-table"><tbody>';
    $folioPase = 'PS-' . str_pad($id, 6, '0', STR_PAD_LEFT);
    $content .= "<tr><td><strong>Folio:</strong></td><td>{$folio}</td><td><strong>Inicio:</strong></td><td>{$a['start_at']}</td></tr>";
    $content .= "<tr><td><strong>Pase de Salida:</strong></td><td><strong>{$folioPase}</strong></td><td><strong>KM Inicio:</strong></td><td>" . number_format((float)($a['start_km'] ?? 0), 0) . " km</td></tr>";
    $content .= "<tr><td><strong>Fin:</strong></td><td>" . ($a['end_at'] ?? 'Vigente') . "</td><td></td><td></td></tr>";
    if ($a['end_at']) {
        $content .= "<tr><td><strong>KM Retorno:</strong></td><td>" . number_format((float)($a['end_km'] ?? 0), 0) . " km</td><td><strong>KM Recorridos:</strong></td><td>" . number_format(((float)($a['end_km'] ?? 0) - (float)($a['start_km'] ?? 0)), 0) . " km</td></tr>";
    }
    $content .= "<tr><td><strong>Notas:</strong></td><td colspan=\"3\">" . htmlspecialchars($a['notas'] ?? '—') . "</td></tr>";
    $content .= '</tbody></table></div>';

    // Checklist de entrega
    $ck = fn($v) => ((int)$v) ? '✓' : '✗';
    $ckStyle = fn($v) => ((int)$v) ? 'color:#22c55e;font-weight:700' : 'color:#ccc';
    $checklistItems = [
        ['checklist_herramientas', 'Herramientas en buen estado'],
        ['checklist_llanta',       'Llanta de repuesto equipada'],
        ['checklist_liquidos',     'Nivel de líquidos operativos'],
        ['checklist_motor',        'Motor en buen estado'],
        ['checklist_parabrisas',   'Parabrisas sin daños'],
        ['checklist_luces',        'Luces en buen estado'],
        ['checklist_frenos',       'Frenos operativos'],
        ['checklist_espejos',      'Espejos completos'],
        ['checklist_gata',         'Gata disponible'],
        ['checklist_bac',          'BAC Flota'],
        ['checklist_documentacion','Documentación en regla'],
        ['checklist_revision',     'Revisión general OK'],
    ];
    if (isset($a['checklist_gata'])) {
        $content .= '<div class="section"><h3>Lista de Verificación Pre-Salida</h3>';
        $content .= '<table class="data-table"><thead><tr><th style="width:40%">Ítem de Verificación</th><th style="width:15%;text-align:center">Estado</th><th>Observación</th></tr></thead><tbody>';
        $obsMap = json_decode($a['checklist_detalles'] ?? '', true) ?: [];
        foreach ($checklistItems as [$col, $label]) {
            $val = $a[$col] ?? 0;
            $obs = htmlspecialchars($obsMap[$col] ?? '');
            $content .= "<tr><td>{$label}</td><td style=\"text-align:center;font-size:16px;{$ckStyle($val)}\">{$ck($val)}</td><td style=\"font-size:11px\">{$obs}</td></tr>";
        }
        $content .= '</tbody></table>';
        // If checklist_detalles is plain text (legacy), show it
        if (($a['checklist_detalles'] ?? '') && !$obsMap) {
            $content .= '<p style="margin-top:6px;font-size:11px"><strong>Observaciones:</strong> ' . htmlspecialchars($a['checklist_detalles']) . '</p>';
        }
        $content .= '</div>';
    }

    // Checklist de retorno (si cerrada)
    if ($a['end_at'] && isset($a['end_checklist_gata'])) {
        $content .= '<div class="section"><h3>Lista de Verificación Post-Retorno</h3>';
        $content .= '<table class="data-table"><thead><tr><th style="width:40%">Ítem de Verificación</th><th style="width:15%;text-align:center">Estado</th><th>Observación</th></tr></thead><tbody>';
        $endObsMap = json_decode($a['end_checklist_detalles'] ?? '', true) ?: [];
        foreach ($checklistItems as [$col, $label]) {
            $endCol = 'end_' . $col;
            $val = $a[$endCol] ?? 0;
            $obs = htmlspecialchars($endObsMap[$endCol] ?? '');
            $content .= "<tr><td>{$label}</td><td style=\"text-align:center;font-size:16px;{$ckStyle($val)}\">{$ck($val)}</td><td style=\"font-size:11px\">{$obs}</td></tr>";
        }
        $content .= '</tbody></table>';
        if (($a['end_checklist_detalles'] ?? '') && !$endObsMap) {
            $content .= '<p style="margin-top:6px;font-size:11px"><strong>Observaciones:</strong> ' . htmlspecialchars($a['end_checklist_detalles']) . '</p>';
        }
        $content .= '</div>';
    }

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

    // Estado General del Vehículo
    $content .= '<div class="section" style="text-align:center;padding:16px 0;border:2px solid #22c55e;border-radius:6px;margin:16px 0">';
    $content .= '<h3 style="background:transparent;color:#22c55e;margin:0;padding:0;text-transform:uppercase;letter-spacing:1px">Estado General del Vehículo: Regular</h3>';
    $content .= '</div>';

    // Observaciones / Notas
    $content .= '<div class="section"><h3>Observaciones / Notas</h3>';
    $content .= '<div style="border:1px solid #ddd;min-height:50px;padding:8px;font-size:11px">' . htmlspecialchars($a['notas'] ?? '') . '</div>';
    $content .= '</div>';

    // Firma digital de entrega
    if (!empty($a['firma_entrega_data'])) {
        $content .= '<div class="section"><h3>Firma de Entrega</h3>';
        $content .= '<div style="text-align:center;padding:8px">';
        $content .= '<img src="' . htmlspecialchars($a['firma_entrega_data']) . '" alt="Firma Entrega" style="max-width:280px;border:1px solid #ccc;padding:4px;background:#fff">';
        $content .= '<p style="font-size:10px;color:#888;margin-top:4px">Tipo: ' . htmlspecialchars($a['firma_entrega_tipo'] ?? 'digital') . ' — Fecha: ' . ($a['firma_entrega_fecha'] ?? '—') . '</p>';
        $content .= '</div></div>';
    }

    // Firma digital de retorno (operador)
    if (!empty($a['firma_data'])) {
        $content .= '<div class="section"><h3>Firma de Retorno / Operador</h3>';
        $content .= '<div style="text-align:center;padding:8px">';
        $content .= '<img src="' . htmlspecialchars($a['firma_data']) . '" alt="Firma" style="max-width:280px;border:1px solid #ccc;padding:4px;background:#fff">';
        $content .= '<p style="font-size:10px;color:#888;margin-top:4px">Tipo: ' . htmlspecialchars($a['firma_tipo'] ?? 'digital') . ' — Fecha: ' . ($a['firma_fecha'] ?? '—') . ' — IP: ' . ($a['firma_ip'] ?? '—') . '</p>';
        $content .= '</div></div>';
    }

    // Documentos vehiculares vigentes
    try {
        $stDocs = $db->prepare("SELECT tipo, titulo, numero_documento, fecha_emision, fecha_vencimiento FROM vehiculo_documentos WHERE vehiculo_id = ? AND deleted_at IS NULL ORDER BY tipo, titulo");
        $stDocs->execute([$a['vehiculo_id']]);
        $docs = $stDocs->fetchAll();
        if ($docs) {
            $tipoLabels = ['revision'=>'Revisión','factura'=>'Factura','permiso'=>'Permiso','placa_digital'=>'Placa Digital','seguro'=>'Seguro','otro'=>'Otro'];
            $content .= '<div class="section"><h3>Documentos del Vehículo</h3>';
            $content .= '<table class="data-table"><thead><tr><th>Tipo</th><th>Título</th><th>Nº Documento</th><th>Emisión</th><th>Vencimiento</th><th>Estado</th></tr></thead><tbody>';
            foreach ($docs as $doc) {
                $vencLabel = '—';
                $vencStyle = '';
                if ($doc['fecha_vencimiento']) {
                    $vencDate = new DateTime($doc['fecha_vencimiento']);
                    $hoy = new DateTime('today');
                    $diff = (int)$hoy->diff($vencDate)->format('%r%a');
                    if ($diff < 0) { $vencLabel = 'VENCIDO'; $vencStyle = 'color:#ff4757;font-weight:700'; }
                    elseif ($diff <= 30) { $vencLabel = "Vence en {$diff}d"; $vencStyle = 'color:#ffa502'; }
                    else { $vencLabel = 'Vigente'; $vencStyle = 'color:#2ed573'; }
                }
                $content .= '<tr>';
                $content .= '<td>' . ($tipoLabels[$doc['tipo']] ?? $doc['tipo']) . '</td>';
                $content .= '<td>' . htmlspecialchars($doc['titulo']) . '</td>';
                $content .= '<td>' . htmlspecialchars($doc['numero_documento'] ?? '—') . '</td>';
                $content .= '<td>' . ($doc['fecha_emision'] ?? '—') . '</td>';
                $content .= '<td>' . ($doc['fecha_vencimiento'] ?? '—') . '</td>';
                $content .= '<td style="' . $vencStyle . '">' . $vencLabel . '</td>';
                $content .= '</tr>';
            }
            $content .= '</tbody></table></div>';
        }
    } catch (Throwable $e) { /* table may not exist yet */ }

    // Signature block
    $responsable = htmlspecialchars(current_user()['nombre'] ?? 'Responsable de Flota');
    $content .= '<div class="signatures">';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Entrega:</strong> ' . $responsable . '</p><p>Responsable de Flota</p></div>';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Recibe:</strong> ' . htmlspecialchars($a['operador_nombre']) . '</p><p>Operador / Conductor</p></div>';
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
    $content .= "<tr><td><strong>Litros:</strong></td><td>" . number_format((float)$c['litros'], 2) . " L</td><td><strong>Costo/L:</strong></td><td>L " . number_format((float)$c['costo_litro'], 2) . "</td></tr>";
    $content .= "<tr><td><strong>Total:</strong></td><td><strong>L " . number_format((float)$c['total'], 2) . "</strong></td><td><strong>Tipo:</strong></td><td>" . ($c['tipo_carga'] ?? 'Lleno') . "</td></tr>";
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
        $content .= "<tr><td>{$n}</td><td>{$r['fecha']}</td><td>{$r['placa']} {$r['marca']}</td><td>{$r['operador_nombre']}</td><td>" . number_format((float)$r['litros'], 2) . "</td><td>L " . number_format((float)$r['costo_litro'], 2) . "</td><td>L " . number_format((float)$r['total'], 2) . "</td><td>" . ($r['proveedor_nombre'] ?? '—') . "</td><td>" . ($r['numero_recibo'] ?? '—') . "</td></tr>";
        $n++;
    }
    $content .= "<tr class=\"total-row\"><td colspan=\"4\"><strong>TOTALES ({$n} registros)</strong></td><td><strong>" . number_format($totalLitros, 2) . " L</strong></td><td></td><td><strong>L " . number_format($totalGasto, 2) . "</strong></td><td colspan=\"2\"></td></tr>";
    $content .= '</tbody></table></div>';

    $content .= '<div class="signatures">';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Elaboró</strong></p><p>Responsable de Flota</p></div>';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Autorizó</strong></p><p>Coordinación</p></div>';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Vo.Bo.</strong></p><p>Contabilidad</p></div>';
    $content .= '</div>';
    break;

// ─── PDF ORDEN DE TRABAJO (MANTENIMIENTO) ───────────
case 'mantenimiento':
    if ($id <= 0) die('ID de mantenimiento requerido.');
    $stmt = $db->prepare("
        SELECT m.*, v.placa, v.marca, v.modelo, v.anio, v.vin, v.km_actual,
               p.nombre AS proveedor_nombre, p.telefono AS proveedor_tel, p.email AS proveedor_email,
               uc.nombre AS completado_por_nombre
        FROM mantenimientos m
        LEFT JOIN vehiculos v ON v.id = m.vehiculo_id
        LEFT JOIN proveedores p ON p.id = m.proveedor_id
        LEFT JOIN usuarios uc ON uc.id = m.completed_by
        WHERE m.id = ?
    ");
    $stmt->execute([$id]);
    $m = $stmt->fetch();
    if (!$m) die('Mantenimiento no encontrado.');

    $title = 'Orden de Trabajo — Mantenimiento';
    $folio = 'OT-' . str_pad($id, 6, '0', STR_PAD_LEFT);

    $estadoBadge = ['Completado'=>'🟢','En proceso'=>'🟡','Pendiente'=>'🔵','Cancelado'=>'🔴'][$m['estado']] ?? '⚪';

    $content .= '<div class="section"><h3>Datos del Vehículo</h3>';
    $content .= '<table class="info-table"><tbody>';
    $content .= "<tr><td><strong>Placa:</strong></td><td>{$m['placa']}</td><td><strong>Marca/Modelo:</strong></td><td>{$m['marca']} {$m['modelo']} {$m['anio']}</td></tr>";
    $content .= "<tr><td><strong>VIN:</strong></td><td>" . ($m['vin'] ?? '—') . "</td><td><strong>KM Actual:</strong></td><td>" . number_format((float)($m['km_actual'] ?? 0), 0) . " km</td></tr>";
    $content .= '</tbody></table></div>';

    $content .= '<div class="section"><h3>Datos de la OT</h3>';
    $content .= '<table class="info-table"><tbody>';
    $content .= "<tr><td><strong>Folio:</strong></td><td>{$folio}</td><td><strong>Fecha:</strong></td><td>{$m['fecha']}</td></tr>";
    $content .= "<tr><td><strong>Tipo:</strong></td><td>{$m['tipo']}</td><td><strong>Estado:</strong></td><td>{$estadoBadge} {$m['estado']}</td></tr>";
    $content .= "<tr><td><strong>KM Entrada:</strong></td><td>" . ($m['km'] ? number_format((float)$m['km'], 0) . ' km' : '—') . "</td><td><strong>KM Salida:</strong></td><td>" . ($m['exit_km'] ? number_format((float)$m['exit_km'], 0) . ' km' : '—') . "</td></tr>";
    $content .= "<tr><td><strong>Taller:</strong></td><td>" . htmlspecialchars($m['proveedor_nombre'] ?? '—') . "</td><td><strong>Próx. servicio:</strong></td><td>" . ($m['proximo_km'] ? number_format((float)$m['proximo_km'], 0) . ' km' : '—') . "</td></tr>";
    if (!empty($m['orden_compra_id'])) {
        $ocFolio = 'OC-' . str_pad($m['orden_compra_id'], 6, '0', STR_PAD_LEFT);
        $content .= "<tr><td><strong>OC Vinculada:</strong></td><td colspan=\"3\"><strong>{$ocFolio}</strong></td></tr>";
    }
    if ($m['descripcion']) {
        $content .= "<tr><td><strong>Descripción:</strong></td><td colspan=\"3\">" . htmlspecialchars($m['descripcion']) . "</td></tr>";
    }
    if ($m['resumen']) {
        $content .= "<tr><td><strong>Resumen:</strong></td><td colspan=\"3\">" . htmlspecialchars($m['resumen']) . "</td></tr>";
    }
    if ($m['completed_at']) {
        $content .= "<tr><td><strong>Completado:</strong></td><td>{$m['completed_at']}</td><td><strong>Por:</strong></td><td>" . htmlspecialchars($m['completado_por_nombre'] ?? '—') . "</td></tr>";
    }
    $content .= '</tbody></table></div>';

    // Items / Partidas
    $stItems = $db->prepare("SELECT * FROM mantenimiento_items WHERE mantenimiento_id = ? ORDER BY id ASC");
    $stItems->execute([$id]);
    $items = $stItems->fetchAll();
    if (count($items)) {
        $totalItems = 0;
        $content .= '<div class="section"><h3>Partidas / Refacciones</h3>';
        $content .= '<table class="data-table"><thead><tr><th>#</th><th>Descripción</th><th>Cant.</th><th>Unidad</th><th>P. Unitario</th><th>Subtotal</th><th>Notas</th></tr></thead><tbody>';
        $n = 1;
        foreach ($items as $it) {
            $sub = (float)$it['subtotal'];
            $totalItems += $sub;
            $content .= "<tr><td>{$n}</td><td>" . htmlspecialchars($it['descripcion']) . "</td><td>" . number_format((float)$it['cantidad'], 2) . "</td><td>{$it['unidad']}</td><td>L " . number_format((float)$it['precio_unitario'], 2) . "</td><td><strong>L " . number_format($sub, 2) . "</strong></td><td>" . htmlspecialchars($it['notas'] ?? '') . "</td></tr>";
            $n++;
        }
        $content .= "<tr class=\"total-row\"><td colspan=\"5\"><strong>TOTAL ({$n} partidas)</strong></td><td><strong>L " . number_format($totalItems, 2) . "</strong></td><td></td></tr>";
        $content .= '</tbody></table></div>';
    }

    // Costo total
    $content .= '<div class="section" style="text-align:right;font-size:14px;padding:10px 0"><strong>Costo Total OT: L ' . number_format((float)$m['costo'], 2) . '</strong></div>';

    $content .= '<div class="signatures">';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Solicitante</strong></p><p>Responsable de Flota</p></div>';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Ejecutó</strong></p><p>' . htmlspecialchars($m['proveedor_nombre'] ?? 'Taller') . '</p></div>';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Autorizó</strong></p><p>Coordinación</p></div>';
    $content .= '</div>';
    break;

// ─── PDF PASE DE SALIDA DE VEHÍCULO ──────────────────
case 'pase_salida':
    if ($id <= 0) die('ID de asignación requerido.');
    $stmt = $db->prepare("
        SELECT a.*, v.placa, v.marca, v.modelo, v.anio, v.color, v.vin, v.km_actual,
               o.nombre AS operador_nombre, o.licencia, o.telefono, o.categoria_lic, o.dni,
               dep.nombre AS departamento_nombre,
               uc.nombre AS creado_por_nombre
        FROM asignaciones a
        LEFT JOIN vehiculos v ON v.id = a.vehiculo_id
        LEFT JOIN operadores o ON o.id = a.operador_id
        LEFT JOIN departamentos dep ON dep.id = o.departamento_id
        LEFT JOIN usuarios uc ON uc.id = a.created_by
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $a = $stmt->fetch();
    if (!$a) die('Asignación no encontrada.');

    $title = 'Pase de Salida de Vehículo';
    $subtitle = 'Control de Flota Vehicular';
    $folio = 'PS-' . str_pad($id, 6, '0', STR_PAD_LEFT);
    $folioAsignacion = 'ASG-' . str_pad($id, 6, '0', STR_PAD_LEFT);

    // Header del pase
    $content .= '<div class="section" style="text-align:center;border:2px solid #1a1a1a;border-radius:8px;padding:16px;margin-bottom:16px">';
    $content .= '<h3 style="background:transparent;color:#1a1a1a;margin:0;padding:0;font-size:18px;text-transform:uppercase;letter-spacing:2px">PASE DE SALIDA DE VEHÍCULO</h3>';
    $content .= '<p style="font-size:12px;color:#555;margin-top:4px">Folio: <strong>' . $folio . '</strong> | Fecha: <strong>' . date('d/m/Y', strtotime($a['start_at'])) . '</strong></p>';
    $content .= '<p style="font-size:11px;color:#666;margin-top:2px">Hoja de Asignación: <strong>' . $folioAsignacion . '</strong></p>';
    $content .= '</div>';

    // Datos del vehículo
    $content .= '<div class="section"><h3>Datos del Vehículo</h3>';
    $content .= '<table class="info-table"><tbody>';
    $content .= "<tr><td><strong>Placa:</strong></td><td style=\"font-size:14px;font-weight:700\">{$a['placa']}</td><td><strong>Marca/Modelo:</strong></td><td>{$a['marca']} {$a['modelo']} {$a['anio']}</td></tr>";
    $content .= "<tr><td><strong>Color:</strong></td><td>" . ($a['color'] ?? '—') . "</td><td><strong>VIN:</strong></td><td>" . ($a['vin'] ?? '—') . "</td></tr>";
    $content .= "<tr><td><strong>KM Salida:</strong></td><td>" . number_format((float)($a['start_km'] ?? 0), 0) . " km</td><td><strong>Estado:</strong></td><td>" . ($a['estado'] ?? '—') . "</td></tr>";
    $content .= '</tbody></table></div>';

    // Datos del personal
    $content .= '<div class="section"><h3>Personal</h3>';
    $content .= '<table class="info-table"><tbody>';
    $content .= "<tr><td><strong>Conductor:</strong></td><td>{$a['operador_nombre']}</td><td><strong>DNI:</strong></td><td>" . ($a['dni'] ?? '—') . "</td></tr>";
    $content .= "<tr><td><strong>Licencia:</strong></td><td>" . ($a['licencia'] ?? '—') . " (" . ($a['categoria_lic'] ?? '') . ")</td><td><strong>Teléfono:</strong></td><td>" . ($a['telefono'] ?? '—') . "</td></tr>";
    $content .= "<tr><td><strong>Departamento:</strong></td><td>" . ($a['departamento_nombre'] ?? '—') . "</td><td><strong>Asignado por:</strong></td><td>" . htmlspecialchars($a['creado_por_nombre'] ?? '—') . "</td></tr>";
    $content .= '</tbody></table></div>';

    // Detalles del viaje
    $content .= '<div class="section"><h3>Detalles del Viaje</h3>';
    $content .= '<table class="info-table"><tbody>';
    $content .= "<tr><td><strong>Destino:</strong></td><td colspan=\"3\" style=\"font-weight:600\">" . htmlspecialchars($a['destino'] ?? '(No especificado)') . "</td></tr>";
    $horaSalida = $a['hora_salida'] ? date('H:i', strtotime($a['hora_salida'])) : date('H:i', strtotime($a['start_at']));
    $horaRegreso = $a['hora_regreso'] ? date('H:i', strtotime($a['hora_regreso'])) : '—';
    $content .= "<tr><td><strong>Fecha:</strong></td><td>" . date('d/m/Y', strtotime($a['start_at'])) . "</td><td><strong>Hora de Salida:</strong></td><td>{$horaSalida}</td></tr>";
    $content .= "<tr><td><strong>Hora de Regreso:</strong></td><td>{$horaRegreso}</td><td><strong>KM Regreso:</strong></td><td>" . ($a['end_km'] ? number_format((float)$a['end_km'], 0) . ' km' : '—') . "</td></tr>";
    $content .= "<tr><td><strong>Observaciones:</strong></td><td colspan=\"3\">" . htmlspecialchars($a['observaciones_pase'] ?? ($a['start_notes'] ?? '—')) . "</td></tr>";
    $content .= '</tbody></table></div>';

    // Sección de firmas digitales (si existen)
    $hasFirmaDigital = !empty($a['firma_entrega_data']) || !empty($a['firma_responsable_data']) || !empty($a['firma_guardia_data']);
    if ($hasFirmaDigital) {
        $content .= '<div class="section"><h3>Firmas Digitales</h3>';
        $content .= '<div style="display:flex;justify-content:space-around;flex-wrap:wrap;gap:12px;text-align:center">';
        if (!empty($a['firma_entrega_data'])) {
            $content .= '<div style="flex:1;min-width:180px">';
            $content .= '<img src="' . htmlspecialchars($a['firma_entrega_data']) . '" alt="Firma Autorización" style="max-width:200px;border:1px solid #ccc;padding:4px;background:#fff;display:block;margin:0 auto">';
            $content .= '<p style="font-size:10px;color:#888;margin-top:4px">Autorizado por<br>' . htmlspecialchars($a['creado_por_nombre'] ?? 'Encargado') . '</p>';
            $content .= '</div>';
        }
        if (!empty($a['firma_responsable_data'])) {
            $content .= '<div style="flex:1;min-width:180px">';
            $content .= '<img src="' . htmlspecialchars($a['firma_responsable_data']) . '" alt="Firma Responsable" style="max-width:200px;border:1px solid #ccc;padding:4px;background:#fff;display:block;margin:0 auto">';
            $content .= '<p style="font-size:10px;color:#888;margin-top:4px">Responsable del Vehículo<br>' . htmlspecialchars($a['operador_nombre']) . '</p>';
            $content .= '</div>';
        }
        if (!empty($a['firma_guardia_data'])) {
            $content .= '<div style="flex:1;min-width:180px">';
            $content .= '<img src="' . htmlspecialchars($a['firma_guardia_data']) . '" alt="Firma Guardia" style="max-width:200px;border:1px solid #ccc;padding:4px;background:#fff;display:block;margin:0 auto">';
            $content .= '<p style="font-size:10px;color:#888;margin-top:4px">Guardia de Seguridad</p>';
            $content .= '</div>';
        }
        $content .= '</div></div>';
    }

    // Bloque de firmas físicas (siempre presente para impresión)
    $encargado = htmlspecialchars($a['creado_por_nombre'] ?? current_user()['nombre'] ?? 'Encargado');
    $content .= '<div class="signatures" style="margin-top:40px">';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Autorizado por:</strong></p><p>' . $encargado . '</p><p style="font-size:10px;color:#888">Encargado de Flota</p></div>';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Responsable del Vehículo:</strong></p><p>' . htmlspecialchars($a['operador_nombre']) . '</p><p style="font-size:10px;color:#888">Conductor / Operador</p></div>';
    $content .= '<div class="sig-block"><div class="sig-line"></div><p><strong>Guardia de Seguridad:</strong></p><p>_________________________</p><p style="font-size:10px;color:#888">Nombre y Firma</p></div>';
    $content .= '</div>';

    // Nota al pie
    $content .= '<div style="margin-top:20px;border-top:1px solid #ddd;padding-top:8px;text-align:center;font-size:10px;color:#888">';
    $content .= 'Este documento constituye un pase de salida oficial del vehículo descrito. Debe ser presentado al guardia de seguridad al momento de la salida.';
    $content .= '</div>';
    break;

default:
    die('Tipo de documento no soportado: ' . htmlspecialchars($type));
}

$generadoPor = htmlspecialchars(current_user()['nombre'] ?? 'Sistema');

// ═══════════════════════════════════════════════════════
// QR Code generation (inline SVG, no external dependency)
// ═══════════════════════════════════════════════════════
$qrUrl = '';
if ($folio) {
    // Build verification URL with folio and type
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
             . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $qrData = $baseUrl . '/print.php?type=' . urlencode($type) . '&id=' . $id;
    // Use Google Charts QR API for simplicity (works offline when printed)
    $qrUrl = 'https://chart.googleapis.com/chart?chs=120x120&cht=qr&chl=' . urlencode($qrData) . '&choe=UTF-8';
}

// ═══════════════════════════════════════════════════════
// Save PDF as attachment option (?save=1)
// ═══════════════════════════════════════════════════════
$saveAsAttachment = (int)($_GET['save'] ?? 0);

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

  /* QR Code */
  .qr-wrap { margin-top: 16px; text-align: center; }
  .qr-wrap img { width: 100px; height: 100px; }
  .qr-wrap .qr-label { font-size: 9px; color: #888; margin-top: 4px; }
</style>
</head>
<body>
<div class="print-bar no-print">
  <span><?= htmlspecialchars($title) ?> — <?= $folio ?></span>
  <div>
    <button onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
    <button onclick="saveAsAttachment()" style="background:#4ecdc4;color:#000;margin-left:8px" title="Guardar una copia como adjunto del registro">💾 Guardar como adjunto</button>
    <button onclick="window.close()" style="background:#555;color:#fff;margin-left:8px">✕ Cerrar</button>
  </div>
</div>
<div class="page" style="margin-top:50px">
  <div class="header">
    <div class="header-left">
      <h1>FlotaCtrl</h1>
      <div class="subtitle"><?= htmlspecialchars($title) ?></div>
      <?php if (!empty($subtitle)): ?><div class="subtitle" style="font-size:10px;margin-top:2px"><?= htmlspecialchars($subtitle) ?></div><?php endif; ?>
    </div>
    <div class="header-right">
      <div class="folio"><?= $folio ?></div>
      <div>Generado: <?= $fecha ?></div>
      <div>Por: <?= $generadoPor ?></div>
    </div>
  </div>

  <?= $content ?>

  <?php if ($qrUrl): ?>
  <div class="qr-wrap">
    <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Verificación" />
    <div class="qr-label">Escanea para verificar documento — <?= $folio ?></div>
  </div>
  <?php endif; ?>

  <div class="footer">
    <span>Documento generado por FlotaCtrl — Sistema de Gestión de Flota</span>
    <span><?= $folio ?> — <?= $fecha ?></span>
  </div>
</div>

<script>
async function saveAsAttachment() {
  const type = '<?= addslashes($type) ?>';
  const id = <?= $id ?>;
  const folio = '<?= addslashes($folio) ?>';

  // Determine entity for attachment
  let entidad = '';
  let entidadId = id;
  if (type === 'asignacion' || type === 'pase_salida') { entidad = 'asignaciones'; }
  else if (type === 'mantenimiento') { entidad = 'mantenimientos'; }
  else if (type === 'combustible') { entidad = 'combustible'; }
  else if (type === 'combustible_lote') {
    alert('Para lotes, guarda cada registro individual.');
    return;
  }
  if (!entidad) { alert('Tipo no soportado para guardar adjunto.'); return; }

  // Capture page HTML as a Blob (HTML snapshot)
  const pageEl = document.querySelector('.page');
  const styles = document.querySelector('style').outerHTML;
  const htmlContent = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>' + folio + '</title>' + styles + '</head><body style="background:#fff;padding:20px">' + pageEl.outerHTML + '</body></html>';
  const blob = new Blob([htmlContent], { type: 'text/html' });
  const fileName = folio + '.html';

  const fd = new FormData();
  fd.append('entidad', entidad);
  fd.append('entidad_id', entidadId);
  fd.append('archivo[]', blob, fileName);
  fd.append('_csrf_token', getCsrfToken());

  try {
    const res = await fetch('/api/attachments.php', { method: 'POST', body: fd });
    if (!res.ok) throw new Error('Error al guardar');
    const d = await res.json();
    alert('✅ Documento guardado como adjunto: ' + fileName);
  } catch(e) {
    alert('❌ Error al guardar adjunto: ' + e.message);
  }
}
</script>

</body>
</html>
