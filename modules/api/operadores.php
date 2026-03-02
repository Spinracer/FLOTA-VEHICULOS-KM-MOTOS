<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
$user = current_user();
try {
    $action = $_GET['action'] ?? '';

    // ──── Capacitaciones CRUD ────
    if ($action === 'capacitaciones') {
        $opId = (int)($_GET['operador_id'] ?? ($_POST['operador_id'] ?? 0));
        if ($method === 'GET') {
            $opId = (int)($_GET['operador_id'] ?? 0);
            if ($opId <= 0) { http_response_code(400); echo json_encode(['error'=>'operador_id requerido']); exit; }
            $stmt = $db->prepare("SELECT * FROM operador_capacitaciones WHERE operador_id=? ORDER BY fecha DESC");
            $stmt->execute([$opId]);
            echo json_encode(['rows'=>$stmt->fetchAll()]);
        } elseif ($method === 'POST') {
            if (!can('create')) { http_response_code(403); echo json_encode(['error'=>'Sin permisos']); exit; }
            $d = json_decode(file_get_contents('php://input'), true);
            if (empty($d['operador_id']) || empty($d['titulo']) || empty($d['fecha'])) {
                http_response_code(422); echo json_encode(['error'=>'operador_id, titulo y fecha son obligatorios']); exit;
            }
            $db->prepare("INSERT INTO operador_capacitaciones (operador_id,titulo,descripcion,tipo,horas,fecha,certificado_url,vencimiento) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([(int)$d['operador_id'],$d['titulo'],$d['descripcion']??null,$d['tipo']??'Interna',(float)($d['horas']??0),$d['fecha'],$d['certificado_url']??null,$d['vencimiento']??null]);
            $newId = (int)$db->lastInsertId();
            audit_log('operador_capacitaciones','create',$newId,[],$d);
            echo json_encode(['id'=>$newId,'ok'=>true]);
        } elseif ($method === 'DELETE') {
            if (!can('delete')) { http_response_code(403); echo json_encode(['error'=>'Sin permisos']); exit; }
            $id = (int)($_GET['id'] ?? 0);
            $db->prepare("DELETE FROM operador_capacitaciones WHERE id=?")->execute([$id]);
            audit_log('operador_capacitaciones','delete',$id,[],[]);
            echo json_encode(['ok'=>true]);
        }
        exit;
    }

    // ──── Infracciones CRUD ────
    if ($action === 'infracciones') {
        if ($method === 'GET') {
            $opId = (int)($_GET['operador_id'] ?? 0);
            if ($opId <= 0) { http_response_code(400); echo json_encode(['error'=>'operador_id requerido']); exit; }
            $stmt = $db->prepare("SELECT * FROM operador_infracciones WHERE operador_id=? ORDER BY fecha DESC");
            $stmt->execute([$opId]);
            echo json_encode(['rows'=>$stmt->fetchAll()]);
        } elseif ($method === 'POST') {
            if (!can('create')) { http_response_code(403); echo json_encode(['error'=>'Sin permisos']); exit; }
            $d = json_decode(file_get_contents('php://input'), true);
            if (empty($d['operador_id']) || empty($d['fecha'])) {
                http_response_code(422); echo json_encode(['error'=>'operador_id y fecha son obligatorios']); exit;
            }
            $db->prepare("INSERT INTO operador_infracciones (operador_id,fecha,tipo,descripcion,monto,estado,referencia) VALUES (?,?,?,?,?,?,?)")
               ->execute([(int)$d['operador_id'],$d['fecha'],$d['tipo']??'Multa',$d['descripcion']??null,(float)($d['monto']??0),$d['estado']??'Pendiente',$d['referencia']??null]);
            $newId = (int)$db->lastInsertId();
            audit_log('operador_infracciones','create',$newId,[],$d);
            echo json_encode(['id'=>$newId,'ok'=>true]);
        } elseif ($method === 'PUT') {
            if (!can('edit')) { http_response_code(403); echo json_encode(['error'=>'Sin permisos']); exit; }
            $d = json_decode(file_get_contents('php://input'), true);
            $db->prepare("UPDATE operador_infracciones SET estado=? WHERE id=?")->execute([$d['estado'],(int)$d['id']]);
            audit_log('operador_infracciones','update',(int)$d['id'],[],['estado'=>$d['estado']]);
            echo json_encode(['ok'=>true]);
        } elseif ($method === 'DELETE') {
            if (!can('delete')) { http_response_code(403); echo json_encode(['error'=>'Sin permisos']); exit; }
            $id = (int)($_GET['id'] ?? 0);
            $db->prepare("DELETE FROM operador_infracciones WHERE id=?")->execute([$id]);
            audit_log('operador_infracciones','delete',$id,[],[]);
            echo json_encode(['ok'=>true]);
        }
        exit;
    }

    // ──── KPIs de desempeño ────
    if ($action === 'kpis') {
        $opId = (int)($_GET['id'] ?? 0);
        if ($opId <= 0) { http_response_code(400); echo json_encode(['error'=>'id requerido']); exit; }

        // Total asignaciones y km recorridos
        $asgStmt = $db->prepare("SELECT COUNT(*) as total,
            COALESCE(SUM(CASE WHEN end_km IS NOT NULL AND start_km IS NOT NULL THEN end_km - start_km ELSE 0 END),0) as km_total,
            MIN(start_at) as primera_asig, MAX(COALESCE(end_at,NOW())) as ultima_asig
            FROM asignaciones WHERE operador_id=?");
        $asgStmt->execute([$opId]);
        $asg = $asgStmt->fetch();

        // Incidentes asociados
        $incStmt = $db->prepare("SELECT COUNT(DISTINCT i.id) as total FROM incidentes i
            JOIN asignaciones a ON a.vehiculo_id=i.vehiculo_id AND a.operador_id=?
            AND i.fecha BETWEEN DATE(a.start_at) AND DATE(COALESCE(a.end_at, NOW()))");
        $incStmt->execute([$opId]);
        $incidentes = (int)$incStmt->fetchColumn();

        // Infracciones
        $infStmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(monto),0) as monto_total FROM operador_infracciones WHERE operador_id=?");
        $infStmt->execute([$opId]);
        $infracciones = $infStmt->fetch();

        // Capacitaciones
        $capStmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(horas),0) as horas_total FROM operador_capacitaciones WHERE operador_id=?");
        $capStmt->execute([$opId]);
        $capacitaciones = $capStmt->fetch();

        // Eficiencia combustible promedio
        $fuelStmt = $db->prepare("SELECT AVG(c.km/NULLIF(c.litros,0)) as avg_kml FROM combustible c
            JOIN asignaciones a ON a.vehiculo_id=c.vehiculo_id AND a.operador_id=?
            AND c.fecha BETWEEN DATE(a.start_at) AND DATE(COALESCE(a.end_at, NOW()))
            WHERE c.litros > 0 AND c.km > 0");
        $fuelStmt->execute([$opId]);
        $avgKml = $fuelStmt->fetchColumn();

        // Días activo
        $diasActivo = 0;
        if ($asg['primera_asig']) {
            $diasActivo = (int)((strtotime($asg['ultima_asig']) - strtotime($asg['primera_asig'])) / 86400);
        }

        echo json_encode([
            'total_asignaciones' => (int)$asg['total'],
            'km_recorridos' => round((float)$asg['km_total'], 1),
            'incidentes' => $incidentes,
            'infracciones' => (int)$infracciones['total'],
            'infracciones_monto' => round((float)$infracciones['monto_total'], 2),
            'capacitaciones' => (int)$capacitaciones['total'],
            'horas_capacitacion' => round((float)$capacitaciones['horas_total'], 1),
            'eficiencia_kml' => $avgKml ? round((float)$avgKml, 2) : null,
            'dias_activo' => $diasActivo,
            'km_por_dia' => $diasActivo > 0 ? round((float)$asg['km_total'] / $diasActivo, 1) : 0,
        ]);
        exit;
    }

    switch ($method) {
        case 'GET':
            if (($_GET['action'] ?? '') === 'history') {
                $id = (int)($_GET['id'] ?? 0);
                if ($id <= 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de operador inválido.']);
                    break;
                }

                $opStmt = $db->prepare("SELECT id,nombre,estado FROM operadores WHERE id=? LIMIT 1");
                $opStmt->execute([$id]);
                $op = $opStmt->fetch();
                if (!$op) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Operador no encontrado.']);
                    break;
                }

                $asgStmt = $db->prepare("SELECT a.id, a.vehiculo_id, v.placa, v.marca, a.start_at, a.end_at, a.start_km, a.end_km, a.estado
                    FROM asignaciones a
                    JOIN vehiculos v ON v.id=a.vehiculo_id
                    WHERE a.operador_id=?
                    ORDER BY a.id DESC
                    LIMIT 100");
                $asgStmt->execute([$id]);
                $asignaciones = $asgStmt->fetchAll();

                $fuelStmt = $db->prepare("SELECT DISTINCT c.id, c.fecha, c.vehiculo_id, v.placa, c.litros, c.total, c.km, a.id AS asignacion_id
                    FROM combustible c
                    JOIN asignaciones a ON a.vehiculo_id=c.vehiculo_id AND a.operador_id=?
                        AND c.fecha BETWEEN DATE(a.start_at) AND DATE(COALESCE(a.end_at, NOW()))
                    JOIN vehiculos v ON v.id=c.vehiculo_id
                    ORDER BY c.fecha DESC, c.id DESC
                    LIMIT 200");
                $fuelStmt->execute([$id]);
                $combustible = $fuelStmt->fetchAll();

                $incStmt = $db->prepare("SELECT DISTINCT i.id, i.fecha, i.vehiculo_id, v.placa, i.tipo, i.severidad, i.estado, a.id AS asignacion_id
                    FROM incidentes i
                    JOIN asignaciones a ON a.vehiculo_id=i.vehiculo_id AND a.operador_id=?
                        AND i.fecha BETWEEN DATE(a.start_at) AND DATE(COALESCE(a.end_at, NOW()))
                    JOIN vehiculos v ON v.id=i.vehiculo_id
                    ORDER BY i.fecha DESC, i.id DESC
                    LIMIT 200");
                $incStmt->execute([$id]);
                $incidentes = $incStmt->fetchAll();

                echo json_encode([
                    'operador' => $op,
                    'asignaciones' => $asignaciones,
                    'combustible' => $combustible,
                    'incidentes' => $incidentes,
                ]);
                break;
            }

            $q='%'.trim($_GET['q']??'').'%';
            $page=max(1,(int)($_GET['page']??1)); $per=min(100,max(5,(int)($_GET['per']??25))); $off=($page-1)*$per;
            $total=$db->prepare("SELECT COUNT(*) FROM operadores WHERE deleted_at IS NULL AND (nombre LIKE ? OR licencia LIKE ? OR telefono LIKE ?)");
            $total->execute([$q,$q,$q]);
            $stmt=$db->prepare("SELECT o.*, v.placa as vehiculo_placa, v.marca as vehiculo_marca,
                DATEDIFF(o.venc_licencia, CURDATE()) as dias_licencia
                FROM operadores o LEFT JOIN vehiculos v ON v.operador_id=o.id
                WHERE o.deleted_at IS NULL AND (o.nombre LIKE ? OR o.licencia LIKE ? OR o.telefono LIKE ?)
                ORDER BY o.nombre ASC LIMIT ? OFFSET ?");
            $stmt->execute([$q,$q,$q,$per,$off]);
            echo json_encode(['total'=>(int)$total->fetchColumn(),'rows'=>$stmt->fetchAll()]);
            break;
        case 'POST':
            if (!can('create')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para crear operadores.']);
                break;
            }
            $d=json_decode(file_get_contents('php://input'),true);
            $db->prepare("INSERT INTO operadores (nombre,licencia,categoria_lic,venc_licencia,telefono,email,estado,notas) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$d['nombre'],$d['licencia']?:null,$d['categoria_lic']?:null,$d['venc_licencia']?:null,$d['telefono']?:null,$d['email']?:null,$d['estado'],$d['notas']?:null]);
                $newId = (int)$db->lastInsertId();
                audit_log('operadores', 'create', $newId, [], $d);
                echo json_encode(['id'=>$newId,'ok'=>true]);
            break;
        case 'PUT':
            if (!can('edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para editar operadores.']);
                break;
            }
            $d=json_decode(file_get_contents('php://input'),true);
                $prevStmt = $db->prepare("SELECT * FROM operadores WHERE id=? LIMIT 1");
                $prevStmt->execute([(int)$d['id']]);
                $prev = $prevStmt->fetch() ?: [];
            $db->prepare("UPDATE operadores SET nombre=?,licencia=?,categoria_lic=?,venc_licencia=?,telefono=?,email=?,estado=?,notas=? WHERE id=?")
               ->execute([$d['nombre'],$d['licencia']?:null,$d['categoria_lic']?:null,$d['venc_licencia']?:null,$d['telefono']?:null,$d['email']?:null,$d['estado'],$d['notas']?:null,$d['id']]);
                audit_log('operadores', 'update', (int)$d['id'], $prev, $d);
            echo json_encode(['ok'=>true]);
            break;
        case 'DELETE':
            if (!can('delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes permisos para eliminar operadores.']);
                break;
            }
            $id = (int)$_GET['id'];
            $prevStmt = $db->prepare("SELECT * FROM operadores WHERE id=? LIMIT 1");
            $prevStmt->execute([$id]);
            $prev = $prevStmt->fetch() ?: [];
            // Soft-delete
            $db->prepare("UPDATE operadores SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            audit_log('operadores', 'soft_delete', $id, $prev, []);
            echo json_encode(['ok'=>true]);
            break;
    }
} catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
