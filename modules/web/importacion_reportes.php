<?php
/**
 * Reportes de Importación por Usuario
 * Visualiza historial de importaciones, usuarios y resultados
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

if (!can('view', 'importacion_vehiculos')) {
    http_response_code(403);
    die('Acceso denegado');
}

$db = getDB();

// Filtros
$usuario_filter = (int)($_GET['usuario_id'] ?? 0);
$resultado_filter = trim($_GET['resultado'] ?? ''); // success, partial, error
$from_date = trim($_GET['from'] ?? '');
$to_date = trim($_GET['to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;

// Estadísticas globales
$stats_query = "
SELECT 
    COUNT(*) as total_importaciones,
    COUNT(DISTINCT usuario_id) as usuarios_activos,
    SUM(CASE WHEN resultado='success' THEN 1 ELSE 0 END) as importaciones_exitosas,
    SUM(CASE WHEN resultado='partial' THEN 1 ELSE 0 END) as importaciones_parciales,
    SUM(CASE WHEN resultado='error' THEN 1 ELSE 0 END) as importaciones_fallidas,
    SUM(JSON_EXTRACT(meta, '$.insertados')) as total_insertados,
    SUM(JSON_EXTRACT(meta, '$.actualizados')) as total_actualizados,
    SUM(JSON_EXTRACT(meta, '$.errores')) as total_errores
FROM audit_logs
WHERE entidad='importacion_vehiculos' AND accion IN ('import', 'import_partial', 'import_error', 'import_undo')
";

$stats_params = [];
if ($from_date) {
    $stats_query .= " AND DATE(created_at) >= ?";
    $stats_params[] = $from_date;
}
if ($to_date) {
    $stats_query .= " AND DATE(created_at) <= ?";
    $stats_params[] = $to_date;
}

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute($stats_params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Query por usuario
$usuarios_query = "
SELECT 
    u.id as usuario_id,
    u.nombre,
    COUNT(*) as importaciones,
    SUM(CASE WHEN al.resultado='success' THEN 1 ELSE 0 END) as exitosas,
    SUM(JSON_EXTRACT(al.meta, '$.insertados')) as total_insertados,
    SUM(JSON_EXTRACT(al.meta, '$.actualizados')) as total_actualizados,
    MAX(al.created_at) as ultima_importacion
FROM audit_logs al
JOIN usuarios u ON u.id = al.usuario_id
WHERE al.entidad='importacion_vehiculos' AND al.accion IN ('import', 'import_partial', 'import_error')
";

$usuarios_params = [];
if ($from_date) {
    $usuarios_query .= " AND DATE(al.created_at) >= ?";
    $usuarios_params[] = $from_date;
}
if ($to_date) {
    $usuarios_query .= " AND DATE(al.created_at) <= ?";
    $usuarios_params[] = $to_date;
}

$usuarios_query .= " GROUP BY u.id, u.nombre ORDER BY importaciones DESC";
$usuarios_stmt = $db->prepare($usuarios_query);
$usuarios_stmt->execute($usuarios_params);
$usuarios = $usuarios_stmt->fetchAll(PDO::FETCH_ASSOC);

// Query detalle (historial)
$detail_query = "
SELECT 
    al.id,
    al.usuario_id,
    u.nombre as usuario_nombre,
    al.accion as resultado,
    JSON_EXTRACT(al.meta, '$.archivo') as archivo,
    JSON_EXTRACT(al.meta, '$.insertados') as insertados,
    JSON_EXTRACT(al.meta, '$.actualizados') as actualizados,
    JSON_EXTRACT(al.meta, '$.errores') as errores,
    JSON_EXTRACT(al.meta, '$.update_key_field') as campo_actualizar,
    JSON_EXTRACT(al.meta, '$.duracion_segundos') as duracion,
    al.created_at
FROM audit_logs al
JOIN usuarios u ON u.id = al.usuario_id
WHERE al.entidad='importacion_vehiculos' AND al.accion IN ('import', 'import_partial', 'import_error')
";

$detail_params = [];
if ($usuario_filter) {
    $detail_query .= " AND al.usuario_id = ?";
    $detail_params[] = $usuario_filter;
}
if ($resultado_filter) {
    if ($resultado_filter === 'success') {
        $detail_query .= " AND al.accion = 'import'";
    } elseif ($resultado_filter === 'partial') {
        $detail_query .= " AND al.accion = 'import_partial'";
    } elseif ($resultado_filter === 'error') {
        $detail_query .= " AND al.accion = 'import_error'";
    }
}
if ($from_date) {
    $detail_query .= " AND DATE(al.created_at) >= ?";
    $detail_params[] = $from_date;
}
if ($to_date) {
    $detail_query .= " AND DATE(al.created_at) <= ?";
    $detail_params[] = $to_date;
}

// Total count
$count_stmt = $db->prepare(str_replace('SELECT al.id,', 'SELECT COUNT(*) as cnt,', $detail_query));
$count_stmt->execute($detail_params);
$total = $count_stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;

// Fetch with pagination
$detail_query .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
$detail_params[] = $per_page;
$detail_params[] = ($page - 1) * $per_page;

$stmt = $db->prepare($detail_query);
$stmt->execute($detail_params);
$importaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Normalize JSON fields
foreach ($importaciones as &$imp) {
    $imp['insertados'] = json_decode($imp['insertados'], true) ?? 0;
    $imp['actualizados'] = json_decode($imp['actualizados'], true) ?? 0;
    $imp['errores'] = json_decode($imp['errores'], true) ?? 0;
    $imp['archivo'] = json_decode($imp['archivo'], true) ?? '—';
    $imp['campo_actualizar'] = json_decode($imp['campo_actualizar'], true) ?? 'placa';
    $imp['duracion'] = json_decode($imp['duracion'], true) ?? 0;
}

$total_pages = ceil($total / $per_page);
?>

<?php layout_head("Reportes de Importación", ['chart', 'table']); ?>

<div class="panel">
    <h2>📥 Reportes de Importación de Vehículos</h2>
    
    <!-- Estadísticas Globales -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['total_importaciones'] ?? 0) ?></div>
            <div class="stat-label">Total Importaciones</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['total_insertados'] ?? 0) ?></div>
            <div class="stat-label">Vehículos Insertados</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['total_actualizados'] ?? 0) ?></div>
            <div class="stat-label">Vehículos Actualizados</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['usuarios_activos'] ?? 0 ?></div>
            <div class="stat-label">Usuarios Activos</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['importaciones_exitosas'] ?? 0 ?></div>
            <div class="stat-label">Importaciones Exitosas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['total_errores'] ?? 0) ?></div>
            <div class="stat-label">Errores Totales</div>
        </div>
    </div>

    <!-- Resumen por Usuario -->
    <div style="margin-top: 40px; margin-bottom: 40px;">
        <h3>📊 Resumen por Usuario</h3>
        <table class="table-responsive">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Importaciones</th>
                    <th>Exitosas</th>
                    <th>Vehículos Insertados</th>
                    <th>Vehículos Actualizados</th>
                    <th>Última Importación</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td>
                        <a href="?usuario_id=<?= $u['usuario_id'] ?>">
                            <?= htmlspecialchars($u['nombre']) ?>
                        </a>
                    </td>
                    <td><?= $u['importaciones'] ?></td>
                    <td><?= $u['exitosas'] ?></td>
                    <td><?= number_format($u['total_insertados'] ?? 0) ?></td>
                    <td><?= number_format($u['total_actualizados'] ?? 0) ?></td>
                    <td>
                        <small><?= date('Y-m-d H:i', strtotime($u['ultima_importacion'])) ?></small>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Filtros -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <select name="usuario_id">
                <option value="">— Todos los usuarios —</option>
                <?php foreach ($usuarios as $u): ?>
                <option value="<?= $u['usuario_id'] ?>" <?= $usuario_filter === $u['usuario_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="resultado">
                <option value="">— Todos los resultados —</option>
                <option value="success" <?= $resultado_filter === 'success' ? 'selected' : '' ?>>Exitosas</option>
                <option value="partial" <?= $resultado_filter === 'partial' ? 'selected' : '' ?>>Parciales</option>
                <option value="error" <?= $resultado_filter === 'error' ? 'selected' : '' ?>>Fallidas</option>
            </select>

            <input type="date" name="from" value="<?= htmlspecialchars($from_date) ?>">
            <input type="date" name="to" value="<?= htmlspecialchars($to_date) ?>">
            
            <button type="submit" class="btn btn-sm">Filtrar</button>
            <a href="?page=1" class="btn btn-sm btn-secondary">Limpiar</a>
        </form>
    </div>

    <!-- Historial Detallado -->
    <h3 style="margin-top: 40px;">📋 Historial de Importaciones</h3>
    <table class="table-responsive">
        <thead>
            <tr>
                <th>Fecha/Hora</th>
                <th>Usuario</th>
                <th>Archivo</th>
                <th>Campo Clave</th>
                <th>Insertados</th>
                <th>Actualizados</th>
                <th>Errores</th>
                <th>Duración</th>
                <th>Resultado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($importaciones) > 0): ?>
                <?php foreach ($importaciones as $imp): ?>
                <tr>
                    <td>
                        <small><?= date('Y-m-d H:i:s', strtotime($imp['created_at'])) ?></small>
                    </td>
                    <td><?= htmlspecialchars($imp['usuario_nombre']) ?></td>
                    <td><small><?= htmlspecialchars($imp['archivo']) ?></small></td>
                    <td><?= htmlspecialchars($imp['campo_actualizar']) ?></td>
                    <td><?= $imp['insertados'] ?></td>
                    <td><?= $imp['actualizados'] ?></td>
                    <td>
                        <?php if ($imp['errores'] > 0): ?>
                            <span class="badge badge-red"><?= $imp['errores'] ?></span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= $imp['duracion'] ?> seg</td>
                    <td>
                        <?php 
                            $class = 'badge-green';
                            $text = '✓ Exitosa';
                            if ($imp['resultado'] === 'import_partial') {
                                $class = 'badge-yellow';
                                $text = '⚠ Parcial';
                            } elseif ($imp['resultado'] === 'import_error') {
                                $class = 'badge-red';
                                $text = '✗ Error';
                            }
                        ?>
                        <span class="badge <?= $class ?>"><?= $text ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="text-center text-muted">
                        No hay importaciones registradas
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Paginación -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1" class="btn btn-sm">« Primera</a>
            <a href="?page=<?= $page - 1 ?>" class="btn btn-sm">‹ Anterior</a>
        <?php endif; ?>
        
        <span class="pagination-info">Página <?= $page ?> de <?= $total_pages ?> (<?= $total ?> registros)</span>
        
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>" class="btn btn-sm">Siguiente ›</a>
            <a href="?page=<?= $total_pages ?>" class="btn btn-sm">Última »</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 12px;
    opacity: 0.9;
    text-transform: uppercase;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
}

.badge-green { background: #2ecc71; color: white; }
.badge-yellow { background: #f39c12; color: white; }
.badge-red { background: #e74c3c; color: white; }

.filter-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
}

.pagination-info {
    display: inline-block;
    margin: 0 15px;
    color: #666;
}
</style>

<?php layout_foot(); ?>
