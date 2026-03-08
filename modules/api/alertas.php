<?php
/**
 * API Centro de Alertas — FlotaControl v3.6
 *
 * GET       → lista alertas con filtros (tipo, estado, prioridad, responsable)
 * GET  ?action=stats        → KPIs y conteos
 * GET  ?action=historial&id=X → historial de una alerta
 * GET  ?action=scan         → escaneo automático de alertas desde otros módulos
 * POST      → crear alerta manual
 * PUT       → actualizar estado/responsable/notas
 * DELETE    → eliminar alerta
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/cache.php';
require_login();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
$user = current_user();
$action = $_GET['action'] ?? '';

try {
    // ──── Escaneo automático: genera alertas desde otros módulos ────
    if ($action === 'scan') {
        $created = 0;
        // Bulk-load existing alert keys to avoid N+1 queries
        $existingKeys = loadExistingAlertKeys($db);

        // 1. Licencias de operadores por vencer (30 días)
        $stmt = $db->query("SELECT id, nombre, venc_licencia, DATEDIFF(venc_licencia, CURDATE()) AS dias
            FROM operadores WHERE deleted_at IS NULL AND venc_licencia IS NOT NULL
            AND DATEDIFF(venc_licencia, CURDATE()) <= 30 AND estado='Activo'");
        foreach ($stmt->fetchAll() as $op) {
            if (!alertExistsBatch($existingKeys, 'licencia', 'operadores', $op['id'])) {
                $lbl = $op['dias'] < 0 ? 'VENCIDA' : "vence en {$op['dias']}d";
                createAlert($db, 'licencia', $op['dias'] <= 0 ? 'Urgente' : ($op['dias'] <= 15 ? 'Alta' : 'Normal'),
                    "Licencia {$lbl}: {$op['nombre']}", "Vencimiento: {$op['venc_licencia']}", 'operadores', $op['id'], null, $op['venc_licencia']);
                $created++;
            }
        }

        // 2. Seguros de vehículos por vencer (30 días)
        $stmt = $db->query("SELECT id, placa, marca, venc_seguro, DATEDIFF(venc_seguro, CURDATE()) AS dias
            FROM vehiculos WHERE venc_seguro IS NOT NULL AND DATEDIFF(venc_seguro, CURDATE()) <= 30 AND estado='Activo'");
        foreach ($stmt->fetchAll() as $v) {
            if (!alertExistsBatch($existingKeys, 'seguro', 'vehiculos', $v['id'])) {
                $lbl = $v['dias'] < 0 ? 'VENCIDO' : "vence en {$v['dias']}d";
                createAlert($db, 'seguro', $v['dias'] <= 0 ? 'Urgente' : ($v['dias'] <= 15 ? 'Alta' : 'Normal'),
                    "Seguro {$lbl}: {$v['placa']}", "Vehículo {$v['marca']} — Vencimiento: {$v['venc_seguro']}", 'vehiculos', $v['id'], $v['id'], $v['venc_seguro']);
                $created++;
            }
        }

        // 3. Componentes por vencer (30 días)
        $stmt = $db->query("SELECT vc.id, c.nombre AS comp, v.id AS vid, v.placa, vc.fecha_vencimiento AS fv,
            DATEDIFF(vc.fecha_vencimiento, CURDATE()) AS dias
            FROM vehicle_components vc
            JOIN components c ON c.id=vc.component_id
            JOIN vehiculos v ON v.id=vc.vehiculo_id
            WHERE vc.fecha_vencimiento IS NOT NULL AND DATEDIFF(vc.fecha_vencimiento, CURDATE()) <= 30");
        foreach ($stmt->fetchAll() as $vc) {
            if (!alertExistsBatch($existingKeys, 'componente', 'vehicle_components', $vc['id'])) {
                $lbl = $vc['dias'] < 0 ? 'VENCIDO' : "vence en {$vc['dias']}d";
                createAlert($db, 'componente', $vc['dias'] <= 0 ? 'Urgente' : ($vc['dias'] <= 15 ? 'Alta' : 'Normal'),
                    "Componente {$lbl}: {$vc['comp']} ({$vc['placa']})", "Vencimiento: {$vc['fv']}", 'vehicle_components', $vc['id'], $vc['vid'], $vc['fv']);
                $created++;
            }
        }

        // 4. Recordatorios pendientes próximos (7 días)
        $stmt = $db->query("SELECT r.id, r.tipo, r.descripcion, v.placa, v.id AS vid, r.fecha_limite,
            DATEDIFF(r.fecha_limite, CURDATE()) AS dias
            FROM recordatorios r
            JOIN vehiculos v ON v.id=r.vehiculo_id
            WHERE r.deleted_at IS NULL AND r.estado='Pendiente' AND DATEDIFF(r.fecha_limite, CURDATE()) <= 7");
        foreach ($stmt->fetchAll() as $r) {
            if (!alertExistsBatch($existingKeys, 'recordatorio', 'recordatorios', $r['id'])) {
                $lbl = $r['dias'] < 0 ? 'VENCIDO' : "en {$r['dias']}d";
                createAlert($db, 'recordatorio', $r['dias'] <= 0 ? 'Alta' : 'Normal',
                    "Recordatorio {$lbl}: {$r['tipo']} — {$r['placa']}", $r['descripcion'] ?: '', 'recordatorios', $r['id'], $r['vid'], $r['fecha_limite']);
                $created++;
            }
        }

        // 5. Stock bajo en componentes
        $stmt = $db->query("SELECT id, nombre, stock, stock_minimo FROM components
            WHERE activo=1 AND stock_minimo > 0 AND stock <= stock_minimo");
        foreach ($stmt->fetchAll() as $c) {
            if (!alertExistsBatch($existingKeys, 'inventario', 'components', $c['id'])) {
                createAlert($db, 'inventario', $c['stock'] <= 0 ? 'Urgente' : 'Alta',
                    "Stock bajo: {$c['nombre']} (quedan {$c['stock']})", "Mínimo: {$c['stock_minimo']}", 'components', $c['id'], null, null);
                $created++;
            }
        }

        // 6. Incidentes abiertos sin atender (> 3 días)
        $stmt = $db->query("SELECT i.id, i.tipo, i.severidad, v.placa, v.id AS vid, i.fecha,
            DATEDIFF(CURDATE(), i.fecha) AS dias
            FROM incidentes i JOIN vehiculos v ON v.id=i.vehiculo_id
            WHERE i.estado='Abierto' AND DATEDIFF(CURDATE(), i.fecha) > 3");
        foreach ($stmt->fetchAll() as $i) {
            if (!alertExistsBatch($existingKeys, 'incidente', 'incidentes', $i['id'])) {
                createAlert($db, 'incidente', $i['severidad'] === 'Crítica' ? 'Urgente' : 'Alta',
                    "Incidente sin atender ({$i['dias']}d): {$i['tipo']} — {$i['placa']}", "Severidad: {$i['severidad']}", 'incidentes', $i['id'], $i['vid'], $i['fecha']);
                $created++;
            }
        }

        // 7. Contratos de proveedores por vencer (30 días)
        $stmt = $db->query("SELECT pc.id, pc.titulo, p.nombre, pc.fecha_fin,
            DATEDIFF(pc.fecha_fin, CURDATE()) AS dias
            FROM proveedor_contratos pc JOIN proveedores p ON p.id=pc.proveedor_id
            WHERE pc.estado='Vigente' AND pc.fecha_fin IS NOT NULL AND DATEDIFF(pc.fecha_fin, CURDATE()) <= 30");
        foreach ($stmt->fetchAll() as $c) {
            if (!alertExistsBatch($existingKeys, 'contrato', 'proveedor_contratos', $c['id'])) {
                $lbl = $c['dias'] < 0 ? 'VENCIDO' : "vence en {$c['dias']}d";
                createAlert($db, 'contrato', $c['dias'] <= 0 ? 'Urgente' : ($c['dias'] <= 15 ? 'Alta' : 'Normal'),
                    "Contrato {$lbl}: {$c['titulo']} ({$c['nombre']})", "Fin: {$c['fecha_fin']}", 'proveedor_contratos', $c['id'], null, $c['fecha_fin']);
                $created++;
            }
        }

        // 8. Mantenimientos preventivos vencidos
        $stmt = $db->query("SELECT p.id, p.concepto, v.placa, v.id AS vid, p.prox_fecha,
            DATEDIFF(CURDATE(), p.prox_fecha) AS dias
            FROM preventivos p JOIN vehiculos v ON v.id=p.vehiculo_id
            WHERE p.activo=1 AND p.prox_fecha IS NOT NULL AND p.prox_fecha <= CURDATE()");
        foreach ($stmt->fetchAll() as $p) {
            if (!alertExistsBatch($existingKeys, 'mantenimiento', 'preventivos', $p['id'])) {
                createAlert($db, 'mantenimiento', $p['dias'] > 15 ? 'Urgente' : 'Alta',
                    "Preventivo vencido ({$p['dias']}d): {$p['concepto']} — {$p['placa']}", "Fecha programada: {$p['prox_fecha']}", 'preventivos', $p['id'], $p['vid'], $p['prox_fecha']);
                $created++;
            }
        }

        // Auto-resolve: marcar Resuelta alertas cuyo registro fuente ya no aplica
        $db->exec("UPDATE alertas SET estado='Resuelta', resuelto_at=NOW()
            WHERE estado='Activa' AND tipo='recordatorio'
            AND entidad_id IN (SELECT id FROM recordatorios WHERE estado != 'Pendiente')");

        echo json_encode(['ok' => true, 'created' => $created]);
        cache_invalidate_prefix('alertas');
        cache_invalidate_prefix('dashboard');
        exit;
    }

    // ──── Stats / KPIs ──── (consolidated into fewer queries + cached)
    if ($action === 'stats') {
        require_once __DIR__ . '/../../includes/cache.php';
        $data = cache_remember('alertas:stats', function() use ($db) {
            // Single query for all KPI counts using conditional aggregation
            $counts = $db->query("SELECT
                COUNT(*) as total,
                SUM(prioridad='Urgente') as urgentes,
                SUM(prioridad='Alta') as altas,
                SUM(responsable_id IS NULL) as sin_asignar
                FROM alertas WHERE estado='Activa'")->fetch();

            $byTipo = $db->query("SELECT tipo, COUNT(*) as cnt FROM alertas WHERE estado='Activa' GROUP BY tipo ORDER BY cnt DESC")->fetchAll();
            $byPrioridad = $db->query("SELECT prioridad, COUNT(*) as cnt FROM alertas WHERE estado='Activa' GROUP BY prioridad")->fetchAll();
            $recientes = $db->query("SELECT DATE(created_at) as dia, COUNT(*) as cnt FROM alertas WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY dia ORDER BY dia")->fetchAll();

            return [
                'activas' => (int)$counts['total'], 'urgentes' => (int)$counts['urgentes'],
                'altas' => (int)$counts['altas'], 'sin_asignar' => (int)$counts['sin_asignar'],
                'by_tipo' => $byTipo, 'by_prioridad' => $byPrioridad, 'recientes' => $recientes,
            ];
        }, 'alertas');
        echo json_encode($data);
        exit;
    }

    // ──── Historial de una alerta ────
    if ($action === 'historial') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT h.*, u.nombre AS usuario_nombre FROM alerta_historial h LEFT JOIN usuarios u ON u.id=h.usuario_id WHERE h.alerta_id=? ORDER BY h.created_at ASC");
        $stmt->execute([$id]);
        echo json_encode(['rows' => $stmt->fetchAll()]);
        exit;
    }

    // ──── CRUD Principal ────
    switch ($method) {
        case 'GET':
            $q = '%' . trim($_GET['q'] ?? '') . '%';
            $tipo = trim($_GET['tipo'] ?? '');
            $estado = trim($_GET['estado'] ?? '');
            $prioridad = trim($_GET['prioridad'] ?? '');
            $responsable = trim($_GET['responsable_id'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per = min(100, max(5, (int)($_GET['per'] ?? 25)));
            $off = ($page - 1) * $per;

            $where = "WHERE (a.titulo LIKE ? OR a.mensaje LIKE ?)";
            $params = [$q, $q];
            if ($tipo) { $where .= " AND a.tipo = ?"; $params[] = $tipo; }
            if ($estado) { $where .= " AND a.estado = ?"; $params[] = $estado; }
            else { $where .= " AND a.estado = 'Activa'"; } // default solo activas
            if ($prioridad) { $where .= " AND a.prioridad = ?"; $params[] = $prioridad; }
            if ($responsable) { $where .= " AND a.responsable_id = ?"; $params[] = (int)$responsable; }

            $total = $db->prepare("SELECT COUNT(*) FROM alertas a $where");
            $total->execute($params);
            $totalCount = (int)$total->fetchColumn();

            $stmt = $db->prepare("SELECT a.*, v.placa, v.marca, ur.nombre AS responsable_nombre
                FROM alertas a
                LEFT JOIN vehiculos v ON v.id=a.vehiculo_id
                LEFT JOIN usuarios ur ON ur.id=a.responsable_id
                $where
                ORDER BY FIELD(a.prioridad,'Urgente','Alta','Normal','Baja'), a.created_at DESC
                LIMIT ? OFFSET ?");
            $stmt->execute(array_merge($params, [$per, $off]));

            echo json_encode(['total' => $totalCount, 'rows' => $stmt->fetchAll()]);
            break;

        case 'POST':
            if (!can('create')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos']); exit; }
            $d = json_decode(file_get_contents('php://input'), true);
            if (empty($d['titulo'])) { http_response_code(422); echo json_encode(['error' => 'titulo es obligatorio']); exit; }

            $db->prepare("INSERT INTO alertas (tipo,prioridad,titulo,mensaje,estado,entidad,entidad_id,vehiculo_id,responsable_id,fecha_referencia,notas) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([
                   $d['tipo'] ?? 'recordatorio', $d['prioridad'] ?? 'Normal', $d['titulo'], $d['mensaje'] ?? null,
                   'Activa', $d['entidad'] ?? null, $d['entidad_id'] ?? null, $d['vehiculo_id'] ?? null,
                   $d['responsable_id'] ?? null, $d['fecha_referencia'] ?? null, $d['notas'] ?? null
               ]);
            $newId = (int)$db->lastInsertId();
            logHistorial($db, $newId, $user['id'] ?? null, 'creada', 'Alerta creada manualmente');
            audit_log('alertas', 'create', $newId, [], $d);

            // Notificar al responsable si se asignó
            if (!empty($d['responsable_id'])) {
                notify_user($db, (int)$d['responsable_id'], 'alerta', 'Alerta asignada', $d['titulo'], 'alertas', $newId);
            }

            echo json_encode(['id' => $newId, 'ok' => true]);
            cache_invalidate_prefix('alertas');
            break;

        case 'PUT':
            if (!can('edit')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos']); exit; }
            $d = json_decode(file_get_contents('php://input'), true);
            $id = (int)$d['id'];

            $prev = $db->prepare("SELECT * FROM alertas WHERE id=?");
            $prev->execute([$id]);
            $prevData = $prev->fetch();
            if (!$prevData) { http_response_code(404); echo json_encode(['error' => 'Alerta no encontrada']); exit; }

            $newEstado = $d['estado'] ?? $prevData['estado'];
            $newResp = $d['responsable_id'] ?? $prevData['responsable_id'];
            $newNotas = $d['notas'] ?? $prevData['notas'];
            $newPrioridad = $d['prioridad'] ?? $prevData['prioridad'];

            $resolvedAt = $prevData['resuelto_at'];
            $resolvedBy = $prevData['resuelto_por'];
            if (in_array($newEstado, ['Resuelta', 'Descartada']) && !$prevData['resuelto_at']) {
                $resolvedAt = date('Y-m-d H:i:s');
                $resolvedBy = $user['id'] ?? null;
            }

            $db->prepare("UPDATE alertas SET estado=?,prioridad=?,responsable_id=?,notas=?,resuelto_at=?,resuelto_por=? WHERE id=?")
               ->execute([$newEstado, $newPrioridad, $newResp, $newNotas, $resolvedAt, $resolvedBy, $id]);

            // Historial
            if ($prevData['estado'] !== $newEstado) {
                logHistorial($db, $id, $user['id'] ?? null, strtolower($newEstado), "Estado: {$prevData['estado']} → {$newEstado}" . (!empty($d['comentario']) ? " — {$d['comentario']}" : ''));
            }
            if ($prevData['responsable_id'] != $newResp && $newResp) {
                logHistorial($db, $id, $user['id'] ?? null, 'asignada', "Responsable asignado");
                notify_user($db, (int)$newResp, 'alerta', 'Alerta asignada', $prevData['titulo'], 'alertas', $id);
            }
            if (!empty($d['comentario']) && $prevData['estado'] === $newEstado) {
                logHistorial($db, $id, $user['id'] ?? null, 'nota', $d['comentario']);
            }

            audit_log('alertas', 'update', $id, (array)$prevData, $d);
            cache_invalidate_prefix('alertas');
            echo json_encode(['ok' => true]);
            break;

        case 'DELETE':
            if (!can('delete')) { http_response_code(403); echo json_encode(['error' => 'Sin permisos']); exit; }
            $id = (int)$_GET['id'];
            $db->prepare("DELETE FROM alertas WHERE id=?")->execute([$id]);
            audit_log('alertas', 'delete', $id, [], []);
            cache_invalidate_prefix('alertas');
            echo json_encode(['ok' => true]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// ──── Funciones auxiliares ────

/**
 * Load all existing active alert keys into a Set for batch lookup.
 * Replaces N+1 individual queries with a single bulk query.
 */
function loadExistingAlertKeys(PDO $db): array {
    $stmt = $db->query("SELECT CONCAT(tipo,':',entidad,':',entidad_id) AS k FROM alertas WHERE estado IN ('Activa','Atendida')");
    return array_flip(array_column($stmt->fetchAll(), 'k'));
}

function alertExistsBatch(array &$existingKeys, string $tipo, string $entidad, int $entidadId): bool {
    return isset($existingKeys["{$tipo}:{$entidad}:{$entidadId}"]);
}

function alertExists(PDO $db, string $tipo, string $entidad, int $entidadId): bool {
    $stmt = $db->prepare("SELECT id FROM alertas WHERE tipo=? AND entidad=? AND entidad_id=? AND estado IN ('Activa','Atendida') LIMIT 1");
    $stmt->execute([$tipo, $entidad, $entidadId]);
    return (bool)$stmt->fetch();
}

function createAlert(PDO $db, string $tipo, string $prioridad, string $titulo, ?string $mensaje, ?string $entidad, ?int $entidadId, ?int $vehiculoId, ?string $fechaRef): int {
    $db->prepare("INSERT INTO alertas (tipo,prioridad,titulo,mensaje,entidad,entidad_id,vehiculo_id,fecha_referencia) VALUES (?,?,?,?,?,?,?,?)")
       ->execute([$tipo, $prioridad, $titulo, $mensaje, $entidad, $entidadId, $vehiculoId, $fechaRef]);
    $id = (int)$db->lastInsertId();
    logHistorial($db, $id, null, 'creada', 'Generada por escaneo automático');
    return $id;
}

function logHistorial(PDO $db, int $alertaId, ?int $userId, string $accion, ?string $comentario): void {
    $db->prepare("INSERT INTO alerta_historial (alerta_id,usuario_id,accion,comentario) VALUES (?,?,?,?)")
       ->execute([$alertaId, $userId, $accion, $comentario]);
}
