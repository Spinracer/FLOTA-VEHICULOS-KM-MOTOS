<?php
/**
 * Dashboard de Sincronización OC ↔ OT
 * Visualiza historial de sincronizaciones entre Órdenes de Compra y Órdenes de Trabajo
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

// Requiere permisos de visualización
if (!can('view', 'ordenes_compra') || !can('view', 'mantenimientos')) {
    http_response_code(403);
    die('Acceso denegado');
}

$db = getDB();

// Filtros
$estado_filter = trim($_GET['estado'] ?? '');
$type_filter   = trim($_GET['type'] ?? ''); // 'sync' o 'desync'
$from_date     = trim($_GET['from'] ?? '');
$to_date       = trim($_GET['to'] ?? '');
$page          = (int)($_GET['page'] ?? 1);
$per_page      = 50;

// Queries de estadísticas
$stats_sql = "
SELECT 
    COUNT(*) as total_eventos,
    SUM(CASE WHEN tipo='sync' THEN 1 ELSE 0 END) as sincronizaciones,
    SUM(CASE WHEN tipo='desync' THEN 1 ELSE 0 END) as desincronizaciones,
    COUNT(DISTINCT DATE(created_at)) as dias_activos,
    COUNT(DISTINCT orden_compra_id) as oc_afectadas,
    COUNT(DISTINCT mantenimiento_id) as ot_afectadas
FROM audit_logs
WHERE entidad='oc_to_ot_sync'
";

$params = [];
if ($from_date) {
    $stats_sql .= " AND DATE(created_at) >= ?";
    $params[] = $from_date;
}
if ($to_date) {
    $stats_sql .= " AND DATE(created_at) <= ?";
    $params[] = $to_date;
}

$stats_stmt = $db->prepare($stats_sql);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Query principal de historial
$query_sql = "
SELECT 
    al.id,
    al.accion as tipo,
    al.usuario_id,
    u.nombre as usuario_nombre,
    JSON_EXTRACT(al.meta, '$.orden_compra_id') as oc_id,
    JSON_EXTRACT(al.meta, '$.mantenimiento_id') as ot_id,
    JSON_EXTRACT(al.meta, '$.items_count') as items_sync,
    JSON_EXTRACT(al.meta, '$.razon') as razon,
    JSON_EXTRACT(al.meta, '$.oc_estado') as oc_estado,
    JSON_EXTRACT(al.meta, '$.ot_estado') as ot_estado,
    al.created_at,
    (SELECT placa FROM vehiculos WHERE id = (SELECT vehiculo_id FROM ordenes_compra WHERE id = JSON_EXTRACT(al.meta, '$.orden_compra_id') LIMIT 1) LIMIT 1) as placa,
    (SELECT estado FROM ordenes_compra WHERE id = JSON_EXTRACT(al.meta, '$.orden_compra_id') LIMIT 1) as oc_estado_actual
FROM audit_logs al
LEFT JOIN usuarios u ON u.id = al.usuario_id
WHERE al.entidad='oc_to_ot_sync'
";

$count_params = [];
if ($estado_filter) {
    $query_sql .= " AND al.accion = ?";
    $count_params[] = $estado_filter;
}
if ($type_filter) {
    $query_sql .= " AND (
        (? = 'sync' AND al.accion = 'sync') OR
        (? = 'desync' AND al.accion IN ('delete', 'cancel'))
    )";
    $count_params[] = $type_filter;
    $count_params[] = $type_filter;
}
if ($from_date) {
    $query_sql .= " AND DATE(al.created_at) >= ?";
    $count_params[] = $from_date;
}
if ($to_date) {
    $query_sql .= " AND DATE(al.created_at) <= ?";
    $count_params[] = $to_date;
}

// Total records
$total_stmt = $db->prepare("SELECT COUNT(*) FROM audit_logs al WHERE al.entidad='oc_to_ot_sync' " . 
    ($estado_filter ? "AND al.accion IN ('" . implode("','", array_fill(0, 1, $estado_filter)) . "')" : "") .
    ($from_date ? " AND DATE(al.created_at) >= ?" : "") .
    ($to_date ? " AND DATE(al.created_at) <= ?" : "")
);
$total_stmt->execute(array_filter([$from_date, $to_date]));
$total = $total_stmt->fetchColumn();

// Query with pagination
$query_sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
$count_params[] = $per_page;
$count_params[] = ($page - 1) * $per_page;

$stmt = $db->prepare($query_sql);
$stmt->execute($count_params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Normalizamos JSON
foreach ($registros as &$r) {
    $r['oc_id'] = json_decode($r['oc_id'], true) ?? $r['oc_id'];
    $r['ot_id'] = json_decode($r['ot_id'], true) ?? $r['ot_id'];
    $r['items_sync'] = json_decode($r['items_sync'], true) ?? 0;
    $r['razon'] = json_decode($r['razon'], true) ?? '';
}

$total_pages = ceil($total / $per_page);
?>

<?php layout_head("Dashboard de Sincronización OC↔OT", ['table']); ?>

<div class="panel">
    <h2>📊 Dashboard de Sincronización OC ↔ OT</h2>
    
    <!-- Estadísticas Rápidas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $stats['total_eventos'] ?? 0 ?></div>
            <div class="stat-label">Eventos Totales</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['sincronizaciones'] ?? 0 ?></div>
            <div class="stat-label">Sincronizaciones</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['desincronizaciones'] ?? 0 ?></div>
            <div class="stat-label">Desincronizaciones</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['oc_afectadas'] ?? 0 ?></div>
            <div class="stat-label">OC Afectadas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['ot_afectadas'] ?? 0 ?></div>
            <div class="stat-label">OT Afectadas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['dias_activos'] ?? 0 ?></div>
            <div class="stat-label">Días Activos</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <input type="text" name="placa" placeholder="Buscar vehículo..." value="">
            
            <select name="estado">
                <option value="">— Todos los eventos —</option>
                <option value="sync" <?= $estado_filter === 'sync' ? 'selected' : '' ?>>Sincronizaciones</option>
                <option value="desync" <?= $estado_filter === 'desync' ? 'selected' : '' ?>>Desincronizaciones</option>
            </select>

            <input type="date" name="from" value="<?= htmlspecialchars($from_date) ?>" placeholder="Desde">
            <input type="date" name="to" value="<?= htmlspecialchars($to_date) ?>" placeholder="Hasta">
            
            <button type="submit" class="btn btn-sm">Filtrar</button>
            <a href="?page=1" class="btn btn-sm btn-secondary">Limpiar</a>
        </form>
    </div>

    <!-- Tabla de Sincronizaciones -->
    <table class="table-responsive">
        <thead>
            <tr>
                <th>Fecha/Hora</th>
                <th>Tipo</th>
                <th>Vehículo</th>
                <th>OC</th>
                <th>OT</th>
                <th>Estado OC</th>
                <th>Items</th>
                <th>Usuario</th>
                <th>Razón</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($registros) > 0): ?>
                <?php foreach ($registros as $reg): ?>
                <tr>
                    <td title="<?= $reg['created_at'] ?>">
                        <small><?= date('Y-m-d', strtotime($reg['created_at'])) ?></small><br>
                        <small style="color:#666"><?= date('H:i:s', strtotime($reg['created_at'])) ?></small>
                    </td>
                    <td>
                        <?php if ($reg['tipo'] === 'sync'): ?>
                            <span class="badge badge-green">✓ Sync</span>
                        <?php elseif ($reg['tipo'] === 'delete'): ?>
                            <span class="badge badge-orange">✗ Delete</span>
                        <?php elseif ($reg['tipo'] === 'cancel'): ?>
                            <span class="badge badge-gray">⊘ Cancel</span>
                        <?php else: ?>
                            <span class="badge"><?= htmlspecialchars($reg['tipo']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($reg['placa'] ?? '—') ?></td>
                    <td>
                        <a href="/ordenes_compra.php?id=<?= $reg['oc_id'] ?>" class="link">OC-<?= $reg['oc_id'] ?></a>
                    </td>
                    <td>
                        <a href="/mantenimientos.php?id=<?= $reg['ot_id'] ?>" class="link">OT-<?= $reg['ot_id'] ?></a>
                    </td>
                    <td>
                        <span class="badge badge-<?= 
                            $reg['oc_estado_actual'] === 'Aprobada' ? 'green' : 
                            ($reg['oc_estado_actual'] === 'Pendiente' ? 'yellow' : 'gray') 
                        ?>">
                            <?= htmlspecialchars($reg['oc_estado_actual'] ?? '—') ?>
                        </span>
                    </td>
                    <td><?= $reg['items_sync'] ?? 0 ?> componentes</td>
                    <td><?= htmlspecialchars($reg['usuario_nombre'] ?? '—') ?></td>
                    <td><small><?= htmlspecialchars($reg['razon'] ?? '—') ?></small></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="text-center text-muted">
                        No hay eventos de sincronización registrados
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

.badge-green { background: #2ecc71; color: white; }
.badge-orange { background: #e67e22; color: white; }
.badge-yellow { background: #f39c12; color: white; }
.badge-gray { background: #95a5a6; color: white; }

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
}

.filter-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
    align-items: end;
}

.pagination-info {
    display: inline-block;
    margin: 0 15px;
    color: #666;
}
</style>

<?php layout_foot(); ?>
