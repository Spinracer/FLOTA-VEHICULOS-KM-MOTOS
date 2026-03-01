<?php
/**
 * API: Intervalos preventivos por vehículo
 *
 * GET    ?action=intervals&vehiculo_id=X   → intervalos configurados
 * POST   ?action=intervals                 → crear intervalo
 * PUT    ?action=intervals                 → editar intervalo
 * DELETE ?action=intervals&id=X            → desactivar intervalo
 * GET    ?action=check                     → vencimientos próximos (alertas)
 * POST   ?action=create_ot                 → crear OT desde alerta
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? 'check');
$db = getDB();

try {
    // ───── CRUD de intervalos preventivos ─────
    if ($action === 'intervals') {
        switch ($method) {
            case 'GET':
                $vid = (int)($_GET['vehiculo_id'] ?? 0);
                $where = "WHERE pi.activo = 1";
                $params = [];
                if ($vid) { $where .= " AND pi.vehiculo_id = ?"; $params[] = $vid; }
                $stmt = $db->prepare("
                    SELECT pi.*, v.placa, v.marca, v.km_actual, p.nombre AS proveedor_nombre
                    FROM preventive_intervals pi
                    JOIN vehiculos v ON v.id = pi.vehiculo_id
                    LEFT JOIN proveedores p ON p.id = pi.proveedor_id
                    $where
                    ORDER BY v.placa ASC, pi.tipo ASC
                ");
                $stmt->execute($params);
                echo json_encode(['rows' => $stmt->fetchAll()]);
                break;

            case 'POST':
                if (!can('create')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); break; }
                $d = json_decode(file_get_contents('php://input'), true);
                if (empty($d['vehiculo_id']) || empty($d['tipo'])) {
                    http_response_code(422);
                    echo json_encode(['error' => 'vehiculo_id y tipo son obligatorios.']);
                    break;
                }
                if (empty($d['cada_km']) && empty($d['cada_dias'])) {
                    http_response_code(422);
                    echo json_encode(['error' => 'Debe especificar cada_km y/o cada_dias.']);
                    break;
                }
                $db->prepare("INSERT INTO preventive_intervals (vehiculo_id, tipo, cada_km, cada_dias, ultimo_km, ultima_fecha, proveedor_id, notas) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([
                       (int)$d['vehiculo_id'], $d['tipo'],
                       $d['cada_km'] ?: null, $d['cada_dias'] ?: null,
                       $d['ultimo_km'] ?: null, $d['ultima_fecha'] ?: null,
                       $d['proveedor_id'] ?: null, $d['notas'] ?? null,
                   ]);
                $id = (int)$db->lastInsertId();
                audit_log('preventive_intervals', 'create', $id, [], $d);
                echo json_encode(['id' => $id, 'ok' => true]);
                break;

            case 'PUT':
                if (!can('edit')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); break; }
                $d = json_decode(file_get_contents('php://input'), true);
                $prev = $db->prepare("SELECT * FROM preventive_intervals WHERE id = ?");
                $prev->execute([(int)$d['id']]);
                $prevData = $prev->fetch() ?: [];
                $db->prepare("UPDATE preventive_intervals SET tipo = ?, cada_km = ?, cada_dias = ?, ultimo_km = ?, ultima_fecha = ?, proveedor_id = ?, notas = ? WHERE id = ?")
                   ->execute([
                       $d['tipo'], $d['cada_km'] ?: null, $d['cada_dias'] ?: null,
                       $d['ultimo_km'] ?: null, $d['ultima_fecha'] ?: null,
                       $d['proveedor_id'] ?: null, $d['notas'] ?? null, (int)$d['id'],
                   ]);
                audit_log('preventive_intervals', 'update', (int)$d['id'], $prevData, $d);
                echo json_encode(['ok' => true]);
                break;

            case 'DELETE':
                if (!can('delete')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); break; }
                $id = (int)$_GET['id'];
                $db->prepare("UPDATE preventive_intervals SET activo = 0 WHERE id = ?")->execute([$id]);
                audit_log('preventive_intervals', 'deactivate', $id, [], []);
                echo json_encode(['ok' => true]);
                break;
        }
        exit;
    }

    // ───── CHECK: Vencimientos próximos ─────
    if ($action === 'check') {
        $vid = (int)($_GET['vehiculo_id'] ?? 0);
        $where = "WHERE pi.activo = 1";
        $params = [];
        if ($vid) { $where .= " AND pi.vehiculo_id = ?"; $params[] = $vid; }

        $stmt = $db->prepare("
            SELECT pi.*, v.placa, v.marca, v.km_actual, p.nombre AS proveedor_nombre
            FROM preventive_intervals pi
            JOIN vehiculos v ON v.id = pi.vehiculo_id
            LEFT JOIN proveedores p ON p.id = pi.proveedor_id
            $where
            ORDER BY v.placa ASC
        ");
        $stmt->execute($params);
        $intervals = $stmt->fetchAll();
        $alertas = [];
        $hoy = new DateTime();

        foreach ($intervals as $pi) {
            $vencido_km  = false;
            $vencido_dia = false;
            $km_restante = null;
            $dias_restante = null;

            // Check km
            if ($pi['cada_km'] > 0 && $pi['ultimo_km'] > 0) {
                $proximo_km = (float)$pi['ultimo_km'] + (float)$pi['cada_km'];
                $km_restante = round($proximo_km - (float)$pi['km_actual'], 1);
                if ($km_restante <= 0) $vencido_km = true;
            }

            // Check días
            if ($pi['cada_dias'] > 0 && $pi['ultima_fecha']) {
                $proxima_fecha = (new DateTime($pi['ultima_fecha']))->modify("+{$pi['cada_dias']} days");
                $dias_restante = (int)$hoy->diff($proxima_fecha)->format('%r%a');
                if ($dias_restante <= 0) $vencido_dia = true;
            }

            // Solo incluir si está próximo a vencer o vencido
            $urgente = $vencido_km || $vencido_dia;
            $proximo = (!$urgente) && (($km_restante !== null && $km_restante <= 500) || ($dias_restante !== null && $dias_restante <= 15));

            if ($urgente || $proximo) {
                $alertas[] = [
                    'interval_id'     => (int)$pi['id'],
                    'vehiculo_id'     => (int)$pi['vehiculo_id'],
                    'placa'           => $pi['placa'],
                    'marca'           => $pi['marca'],
                    'tipo'            => $pi['tipo'],
                    'estado'          => $urgente ? 'vencido' : 'proximo',
                    'km_restante'     => $km_restante,
                    'dias_restante'   => $dias_restante,
                    'proveedor_nombre'=> $pi['proveedor_nombre'],
                    'proveedor_id'    => $pi['proveedor_id'] ? (int)$pi['proveedor_id'] : null,
                ];
            }
        }

        // Ordenar: vencidos primero, luego por km/días restante
        usort($alertas, function($a, $b) {
            if ($a['estado'] !== $b['estado']) return $a['estado'] === 'vencido' ? -1 : 1;
            return ($a['km_restante'] ?? 999999) <=> ($b['km_restante'] ?? 999999);
        });

        echo json_encode(['alertas' => $alertas, 'total_intervals' => count($intervals)]);
        exit;
    }

    // ───── CREATE OT desde alerta preventiva ─────
    if ($action === 'create_ot' && $method === 'POST') {
        if (!can('create')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); exit; }
        $d = json_decode(file_get_contents('php://input'), true);
        $intervalId = (int)($d['interval_id'] ?? 0);
        $pi = $db->prepare("SELECT * FROM preventive_intervals WHERE id = ? AND activo = 1");
        $pi->execute([$intervalId]);
        $interval = $pi->fetch();
        if (!$interval) { http_response_code(404); echo json_encode(['error' => 'Intervalo no encontrado.']); exit; }

        // Check for existing active OT from this interval
        $dupCheck = $db->prepare("SELECT id FROM mantenimientos WHERE vehiculo_id = ? AND tipo = ? AND estado IN ('Pendiente','En proceso') AND deleted_at IS NULL AND descripcion LIKE ? LIMIT 1");
        $dupCheck->execute([(int)$interval['vehiculo_id'], $interval['tipo'], '%intervalo #' . $intervalId . '%']);
        $dup = $dupCheck->fetch();
        if ($dup) {
            http_response_code(409);
            echo json_encode(['error' => 'Ya existe una OT activa (#' . $dup['id'] . ') para este intervalo.']);
            exit;
        }

        // Obtener km actual del vehículo
        $veh = $db->prepare("SELECT km_actual FROM vehiculos WHERE id = ?");
        $veh->execute([(int)$interval['vehiculo_id']]);
        $kmActual = (float)$veh->fetchColumn();

        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO mantenimientos (fecha, vehiculo_id, tipo, descripcion, km, proveedor_id, estado) VALUES (CURDATE(), ?, ?, ?, ?, ?, 'Pendiente')")
               ->execute([
                   (int)$interval['vehiculo_id'],
                   $interval['tipo'],
                   'Mantenimiento preventivo programado (intervalo #' . $intervalId . '). ' . ($interval['notas'] ?? ''),
                   $kmActual ?: null,
                   $interval['proveedor_id'] ?: null,
               ]);
            $otId = (int)$db->lastInsertId();

            // NOTE: No actualizamos ultimo_km/ultima_fecha aquí.
            // Se actualiza cuando la OT se marca Completado (en mantenimientos PUT).

            $db->commit();
        } catch (Throwable $txe) {
            $db->rollBack();
            throw $txe;
        }

        audit_log('preventive_intervals', 'create_ot', $intervalId, [], ['ot_id' => $otId, 'vehiculo_id' => $interval['vehiculo_id']]);
        echo json_encode(['ok' => true, 'ot_id' => $otId]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Acción no válida.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
