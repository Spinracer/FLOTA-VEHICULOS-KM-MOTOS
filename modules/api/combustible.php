<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/odometro.php';
require_login();
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

function combustible_bloqueo_mantenimiento(PDO $db, int $vehiculoId, ?int $excludeMaintenanceId = null): ?array {
    $sql = "SELECT id, estado FROM mantenimientos WHERE vehiculo_id=? AND estado IN ('En proceso','Pendiente')";
    $params = [$vehiculoId];
    if ($excludeMaintenanceId) {
        $sql .= " AND id<>?";
        $params[] = $excludeMaintenanceId;
    }
    $sql .= " ORDER BY id DESC LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    if ($row) {
        return [
            'reason' => 'El vehículo tiene mantenimiento activo. No se permite carga de combustible.',
            'blocking_type' => 'mantenimiento',
            'blocking_id' => (int)$row['id'],
            'estado' => $row['estado'],
        ];
    }
    return null;
}

try {
    // ─── Sub-endpoint: Anomalías de combustible ───
    $action = trim($_GET['action'] ?? '');
    if ($action === 'anomalias') {
        // Genera alertas por: cargas muy cercanas, rendimiento muy bajo, odómetro sospechoso
        $vid = (int)($_GET['vehiculo_id'] ?? 0);
        $limit = min(200, max(10, (int)($_GET['limit'] ?? 50)));

        // Obtener umbral de configuración
        $threshStmt = $db->prepare("SELECT value_num FROM system_settings WHERE key_name = 'fuel.anomaly_threshold' LIMIT 1");
        $threshStmt->execute();
        $threshold = (float)($threshStmt->fetchColumn() ?: 15);

        $where = "WHERE c.km > 0 AND c.litros > 0";
        $params = [];
        if ($vid) { $where .= " AND c.vehiculo_id = ?"; $params[] = $vid; }

        $stmt = $db->prepare("
            SELECT c.id, c.fecha, c.vehiculo_id, c.litros, c.km, c.total, v.placa, v.marca,
                   LAG(c.km) OVER (PARTITION BY c.vehiculo_id ORDER BY c.km ASC) AS prev_km,
                   LAG(c.fecha) OVER (PARTITION BY c.vehiculo_id ORDER BY c.fecha ASC, c.id ASC) AS prev_fecha,
                   LAG(c.litros) OVER (PARTITION BY c.vehiculo_id ORDER BY c.km ASC) AS prev_litros
            FROM combustible c
            JOIN vehiculos v ON v.id = c.vehiculo_id
            $where
            ORDER BY c.fecha DESC, c.id DESC
            LIMIT ?
        ");
        $params[] = $limit;
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Calcular promedio móvil por vehículo (últimos 10 registros)
        $avgStmt = $db->prepare("
            SELECT vehiculo_id,
                   AVG((km - prev_km) / litros) AS avg_kml
            FROM (
                SELECT c2.vehiculo_id, c2.litros, c2.km,
                       LAG(c2.km) OVER (PARTITION BY c2.vehiculo_id ORDER BY c2.km ASC) AS prev_km
                FROM combustible c2
                WHERE c2.km > 0 AND c2.litros > 0
            ) sub
            WHERE prev_km IS NOT NULL AND prev_km > 0 AND km > prev_km
            GROUP BY vehiculo_id
        ");
        $avgStmt->execute();
        $promedios = [];
        while ($r = $avgStmt->fetch()) {
            $promedios[(int)$r['vehiculo_id']] = round((float)$r['avg_kml'], 2);
        }

        $alertas = [];
        foreach ($rows as $r) {
            $alerts_for_row = [];
            $kml = null;
            if ($r['prev_km'] && $r['km'] > $r['prev_km'] && $r['litros'] > 0) {
                $kml = round(($r['km'] - $r['prev_km']) / $r['litros'], 2);
            }

            // 1) Rendimiento muy bajo vs promedio
            $avgVeh = $promedios[(int)$r['vehiculo_id']] ?? null;
            if ($kml !== null && $avgVeh !== null && $avgVeh > 0) {
                $desvPct = round((($avgVeh - $kml) / $avgVeh) * 100, 1);
                if ($desvPct >= $threshold) {
                    $alerts_for_row[] = ['tipo' => 'rendimiento_bajo', 'msg' => "Rendimiento {$kml} km/L vs promedio {$avgVeh} km/L ({$desvPct}% menor)", 'severidad' => $desvPct >= 40 ? 'alta' : 'media'];
                }
            }

            // 2) Cargas muy cercanas en tiempo (< 24h entre cargas)
            if ($r['prev_fecha']) {
                $diff = (strtotime($r['fecha']) - strtotime($r['prev_fecha'])) / 3600;
                if ($diff >= 0 && $diff < 24) {
                    $alerts_for_row[] = ['tipo' => 'carga_cercana', 'msg' => "Carga a " . round($diff, 1) . " horas de la anterior", 'severidad' => $diff < 6 ? 'alta' : 'media'];
                }
            }

            // 3) Odómetro sospechoso (retroceso o salto enorme > 2000 km entre cargas)
            if ($r['prev_km'] && $r['km'] > 0) {
                if ($r['km'] < $r['prev_km']) {
                    $alerts_for_row[] = ['tipo' => 'odometro_retroceso', 'msg' => "Odómetro retrocedió: {$r['km']} < {$r['prev_km']}", 'severidad' => 'alta'];
                } elseif (($r['km'] - $r['prev_km']) > 2000 && $r['litros'] < 80) {
                    $alerts_for_row[] = ['tipo' => 'salto_odometro', 'msg' => "Salto inusual de " . ($r['km'] - $r['prev_km']) . " km con solo {$r['litros']} L", 'severidad' => 'media'];
                }
            }

            if (!empty($alerts_for_row)) {
                $alertas[] = [
                    'registro_id' => (int)$r['id'],
                    'fecha' => $r['fecha'],
                    'vehiculo_id' => (int)$r['vehiculo_id'],
                    'placa' => $r['placa'],
                    'marca' => $r['marca'],
                    'litros' => (float)$r['litros'],
                    'km' => (float)$r['km'],
                    'rendimiento' => $kml,
                    'alertas' => $alerts_for_row,
                ];
            }
        }

        echo json_encode(['alertas' => $alertas, 'promedios' => $promedios, 'threshold' => $threshold]);
        exit;
    }

    switch ($method) {
        case 'GET':
            $q    = '%'.trim($_GET['q']??'').'%';
            $vid  = (int)($_GET['vehiculo_id'] ?? 0);
            $from = trim((string)($_GET['from'] ?? ''));
            $to   = trim((string)($_GET['to'] ?? ''));
            $page = max(1,(int)($_GET['page']??1));
            $per  = min(100,max(5,(int)($_GET['per']??25)));
            $off  = ($page-1)*$per;

            $where = "WHERE c.deleted_at IS NULL AND (v.placa LIKE ? OR v.marca LIKE ? OR p.nombre LIKE ? OR o.nombre LIKE ? OR c.numero_recibo LIKE ?)";
            $params = [$q, $q, $q, $q, $q];
            if ($vid) { $where .= " AND c.vehiculo_id = ?"; $params[] = $vid; }
            if ($from !== '') { $where .= " AND c.fecha >= ?"; $params[] = $from; }
            if ($to !== '') { $where .= " AND c.fecha <= ?"; $params[] = $to; }

            // Obtener total y consumir inmediatamente
            $totalStmt = $db->prepare("SELECT COUNT(*) FROM combustible c
                LEFT JOIN vehiculos v ON v.id=c.vehiculo_id
                LEFT JOIN operadores o ON o.id=c.operador_id
                LEFT JOIN proveedores p ON p.id=c.proveedor_id $where");
            $totalStmt->execute($params);
            $total = (int)$totalStmt->fetchColumn();

            $statsStmt = $db->prepare("SELECT COALESCE(SUM(c.litros),0) as litros, COALESCE(SUM(c.total),0) as gasto
                FROM combustible c LEFT JOIN vehiculos v ON v.id=c.vehiculo_id
                LEFT JOIN operadores o ON o.id=c.operador_id
                LEFT JOIN proveedores p ON p.id=c.proveedor_id $where");
            $statsStmt->execute($params);
            $stats = $statsStmt->fetch();

            $listParams = $params;
            $listParams[] = $per;
            $listParams[] = $off;
            // Incluir la carga previa con LAG() para evitar N+1 queries en rendimiento
            $stmt = $db->prepare("SELECT c.*, v.placa, v.marca, p.nombre AS proveedor_nombre, o.nombre AS operador_nombre,
                (SELECT c2.km FROM combustible c2 WHERE c2.vehiculo_id=c.vehiculo_id AND c2.km>0 AND c2.km<c.km AND c2.litros>0 ORDER BY c2.km DESC LIMIT 1) AS prev_km
                FROM combustible c
                LEFT JOIN vehiculos v ON v.id=c.vehiculo_id
                LEFT JOIN operadores o ON o.id=c.operador_id
                LEFT JOIN proveedores p ON p.id=c.proveedor_id
                $where ORDER BY c.fecha DESC, c.id DESC LIMIT ? OFFSET ?");
            $stmt->execute($listParams);
            $rows = $stmt->fetchAll();

            // Calcular rendimiento usando prev_km ya obtenido (sin N+1)
            foreach ($rows as &$row) {
                if ($row['km'] > 0 && $row['prev_km'] > 0 && $row['litros'] > 0) {
                    $row['rendimiento'] = round(($row['km'] - $row['prev_km']) / $row['litros'], 2);
                } else {
                    $row['rendimiento'] = null;
                }
                unset($row['prev_km']);
            }
            unset($row);

            echo json_encode(['total' => $total, 'stats' => $stats, 'rows' => $rows]);
            break;

        case 'POST':
            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para crear registros de combustible.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'), true);
            $vehiculoId = (int)($d['vehiculo_id'] ?? 0);
            $operadorId = (int)($d['operador_id'] ?? 0);
            if ($operadorId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Debes seleccionar un conductor responsable.']);
                break;
            }
            $opStmt = $db->prepare("SELECT estado FROM operadores WHERE id=? LIMIT 1");
            $opStmt->execute([$operadorId]);
            $op = $opStmt->fetch();
            if (!$op || ($op['estado'] ?? '') !== 'Activo') {
                http_response_code(400);
                echo json_encode(['error' => 'El conductor seleccionado no está activo.']);
                break;
            }
            $km = isset($d['km']) && $d['km'] !== '' ? (float)$d['km'] : null;
            $allowOverride = can('manage_permissions') && !empty($d['override_reason']);
            $bloqueo = combustible_bloqueo_mantenimiento($db, $vehiculoId);
            if ($bloqueo && !$allowOverride) {
                http_response_code(409);
                echo json_encode(['error' => $bloqueo['reason'], 'reason' => $bloqueo['reason'], 'blocking_type' => $bloqueo['blocking_type'], 'blocking_id' => $bloqueo['blocking_id']]);
                break;
            }
            odometro_validar_km($db, $vehiculoId, $km, $allowOverride, trim((string)($d['override_reason'] ?? '')) ?: null);
            // Validar máximo de litros por evento
            $maxLitrosStmt = $db->prepare("SELECT value_num FROM system_settings WHERE key_name = 'fuel.max_litros_evento' LIMIT 1");
            $maxLitrosStmt->execute();
            $maxLitros = (float)($maxLitrosStmt->fetchColumn() ?: 0);
            $l = (float)$d['litros']; $c = (float)$d['costo_litro'];
            if ($maxLitros > 0 && $l > $maxLitros && !$allowOverride) {
                http_response_code(422);
                echo json_encode(['error' => "La cantidad de litros ({$l}) excede el máximo permitido ({$maxLitros} L). Requiere justificación de override."]);
                break;
            }
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("INSERT INTO combustible (fecha,vehiculo_id,operador_id,litros,costo_litro,total,km,proveedor_id,metodo_pago,numero_recibo,tipo_carga,notas) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$d['fecha'],$d['vehiculo_id'],$operadorId,$l,$c,round($l*$c,2),$d['km']?:null,$d['proveedor_id']?:null,$d['metodo_pago'] ?: 'Efectivo',$d['numero_recibo']?:null,$d['tipo_carga']??'Lleno',$d['notas']?:null]);
                // Actualizar km del vehículo si es mayor
                if ($km) {
                    odometro_registrar($db, (int)$d['vehiculo_id'], $km, 'fuel', (int)($_SESSION['user_id'] ?? 0));
                }
                $newId = (int)$db->lastInsertId();
                $db->commit();
            } catch (Throwable $txe) {
                $db->rollBack();
                throw $txe;
            }
            if ($allowOverride && $bloqueo) {
                audit_log('combustible', 'maintenance_override', $newId, [], ['vehiculo_id' => $vehiculoId], ['reason' => $d['override_reason'], 'bloqueo' => $bloqueo]);
            }
            if ($allowOverride) {
                audit_log('combustible', 'odometro_override', $newId, [], ['km_nuevo' => $km], ['reason' => $d['override_reason']]);
            }
            audit_log('combustible', 'create', $newId, [], $d);
            echo json_encode(['id'=>$newId,'ok'=>true]);
            break;

        case 'PUT':
            if (!can('edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para editar registros de combustible.']);
                break;
            }
            $d = json_decode(file_get_contents('php://input'), true);
            $prevStmt = $db->prepare("SELECT * FROM combustible WHERE id=? LIMIT 1");
            $prevStmt->execute([(int)$d['id']]);
            $prev = $prevStmt->fetch() ?: [];
            $vehiculoId = (int)($d['vehiculo_id'] ?? 0);
            $operadorId = (int)($d['operador_id'] ?? 0);
            if ($operadorId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Debes seleccionar un conductor responsable.']);
                break;
            }
            $opStmt = $db->prepare("SELECT estado FROM operadores WHERE id=? LIMIT 1");
            $opStmt->execute([$operadorId]);
            $op = $opStmt->fetch();
            if (!$op || ($op['estado'] ?? '') !== 'Activo') {
                http_response_code(400);
                echo json_encode(['error' => 'El conductor seleccionado no está activo.']);
                break;
            }
            $km = isset($d['km']) && $d['km'] !== '' ? (float)$d['km'] : null;
            $allowOverride = can('manage_permissions') && !empty($d['override_reason']);
            $bloqueo = combustible_bloqueo_mantenimiento($db, $vehiculoId);
            if ($bloqueo && !$allowOverride) {
                http_response_code(409);
                echo json_encode(['error' => $bloqueo['reason'], 'reason' => $bloqueo['reason'], 'blocking_type' => $bloqueo['blocking_type'], 'blocking_id' => $bloqueo['blocking_id']]);
                break;
            }
            odometro_validar_km($db, $vehiculoId, $km, $allowOverride, trim((string)($d['override_reason'] ?? '')) ?: null);
            $l=(float)$d['litros']; $c=(float)$d['costo_litro'];
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("UPDATE combustible SET fecha=?,vehiculo_id=?,operador_id=?,litros=?,costo_litro=?,total=?,km=?,proveedor_id=?,metodo_pago=?,numero_recibo=?,tipo_carga=?,notas=? WHERE id=?");
                $stmt->execute([$d['fecha'],$d['vehiculo_id'],$operadorId,$l,$c,round($l*$c,2),$d['km']?:null,$d['proveedor_id']?:null,$d['metodo_pago'] ?: 'Efectivo',$d['numero_recibo']?:null,$d['tipo_carga'],$d['notas']?:null,$d['id']]);
                if ($km) {
                    odometro_registrar($db, (int)$d['vehiculo_id'], $km, 'fuel', (int)($_SESSION['user_id'] ?? 0));
                }
                $db->commit();
            } catch (Throwable $txe) {
                $db->rollBack();
                throw $txe;
            }
            if ($allowOverride) {
                if ($bloqueo) {
                    audit_log('combustible', 'maintenance_override', (int)$d['id'], $prev, ['vehiculo_id' => $vehiculoId], ['reason' => $d['override_reason'], 'bloqueo' => $bloqueo]);
                }
                audit_log('combustible', 'odometro_override', (int)$d['id'], ['km_anterior' => $prev['km'] ?? null], ['km_nuevo' => $km], ['reason' => $d['override_reason']]);
            }
            audit_log('combustible', 'update', (int)$d['id'], $prev, $d);
            echo json_encode(['ok'=>true]);
            break;

        case 'DELETE':
            if (!can('delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para eliminar registros de combustible.']);
                break;
            }
            $id = (int)$_GET['id'];
            $prevStmt = $db->prepare("SELECT * FROM combustible WHERE id=? LIMIT 1");
            $prevStmt->execute([$id]);
            $prev = $prevStmt->fetch() ?: [];
            $db->prepare("UPDATE combustible SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            audit_log('combustible', 'soft_delete', $id, $prev, []);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
