<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();

$db = getDB();

// KPIs
$total_veh    = $db->query("SELECT COUNT(*) FROM vehiculos")->fetchColumn();
$total_op     = $db->query("SELECT COUNT(*) FROM operadores WHERE estado='Activo'")->fetchColumn();
$inc_abiertos = $db->query("SELECT COUNT(*) FROM incidentes WHERE estado='Abierto'")->fetchColumn();
$total_mant   = $db->query("SELECT COUNT(*) FROM mantenimientos")->fetchColumn();

$mes_actual = date('Y-m');
$stmt = $db->prepare("SELECT COALESCE(SUM(litros),0) as litros, COALESCE(SUM(total),0) as gasto FROM combustible WHERE DATE_FORMAT(fecha,'%Y-%m') = ?");
$stmt->execute([$mes_actual]);
$comb_mes = $stmt->fetch();

// Alertas recordatorios próximos 30 días
$alertas = $db->query("
    SELECT r.*, v.placa, v.marca, v.modelo,
           DATEDIFF(r.fecha_limite, CURDATE()) as dias
    FROM recordatorios r
    JOIN vehiculos v ON v.id = r.vehiculo_id
    WHERE r.estado = 'Pendiente'
      AND r.fecha_limite <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY r.fecha_limite ASC
    LIMIT 8
")->fetchAll();

// Incidentes abiertos recientes
$inc_list = $db->query("
    SELECT i.*, v.placa, v.marca FROM incidentes i
    JOIN vehiculos v ON v.id = i.vehiculo_id
    WHERE i.estado = 'Abierto'
    ORDER BY i.fecha DESC LIMIT 6
")->fetchAll();

// Consumo por vehículo (top 6)
$consumo_chart = $db->query("
    SELECT v.placa as label, COALESCE(SUM(c.litros),0) as value
    FROM vehiculos v
    LEFT JOIN combustible c ON c.vehiculo_id = v.id
    GROUP BY v.id, v.placa
    HAVING value > 0
    ORDER BY value DESC LIMIT 6
")->fetchAll();

// Gasto mantenimiento por vehículo (top 6)
$mant_chart = $db->query("
    SELECT v.placa as label, COALESCE(SUM(m.costo),0) as value
    FROM vehiculos v
    LEFT JOIN mantenimientos m ON m.vehiculo_id = v.id
    GROUP BY v.id, v.placa
    HAVING value > 0
    ORDER BY value DESC LIMIT 6
")->fetchAll();

// Alertas preventivos: intervalos vencidos o próximos
$preventivo_alertas = [];
try {
    $piStmt = $db->query("
        SELECT pi.id, pi.tipo, pi.cada_km, pi.cada_dias, pi.ultimo_km, pi.ultima_fecha,
               v.id AS vid, v.placa, v.marca, v.km_actual
        FROM preventive_intervals pi
        JOIN vehiculos v ON v.id = pi.vehiculo_id
        WHERE pi.activo = 1
        ORDER BY v.placa
    ");
    $piRows = $piStmt->fetchAll();
    foreach ($piRows as $pi) {
        $alerts = [];
        if ($pi['cada_km'] && $pi['ultimo_km'] !== null) {
            $nextKm = (float)$pi['ultimo_km'] + (float)$pi['cada_km'];
            $kmAct  = (float)$pi['km_actual'];
            if ($kmAct >= $nextKm) {
                $alerts[] = ['tipo' => 'vencido', 'msg' => 'Km excedido: ' . number_format($kmAct,0) . ' / ' . number_format($nextKm,0)];
            } elseif ($kmAct >= $nextKm - 500) {
                $alerts[] = ['tipo' => 'proximo', 'msg' => 'Faltan ' . number_format($nextKm - $kmAct,0) . ' km'];
            }
        }
        if ($pi['cada_dias'] && $pi['ultima_fecha']) {
            $nextDate = date('Y-m-d', strtotime($pi['ultima_fecha'] . " +{$pi['cada_dias']} days"));
            $today    = date('Y-m-d');
            $diff     = (int)((strtotime($nextDate) - strtotime($today)) / 86400);
            if ($diff <= 0) {
                $alerts[] = ['tipo' => 'vencido', 'msg' => 'Fecha vencida: ' . $nextDate];
            } elseif ($diff <= 15) {
                $alerts[] = ['tipo' => 'proximo', 'msg' => "Faltan {$diff} días"];
            }
        }
        foreach ($alerts as $a) {
            $preventivo_alertas[] = array_merge($a, [
                'interval_id' => $pi['id'],
                'placa' => $pi['placa'],
                'marca' => $pi['marca'],
                'servicio' => $pi['tipo'],
            ]);
        }
    }
} catch (Throwable $e) {
    // Tabla no existe aún → ignorar
}

// OTs pendientes/en proceso
$ots_activas = $db->query("
    SELECT m.id, m.tipo, m.estado, m.fecha, m.costo,
           v.placa, v.marca, p.nombre AS proveedor
    FROM mantenimientos m
    JOIN vehiculos v ON v.id = m.vehiculo_id
    LEFT JOIN proveedores p ON p.id = m.proveedor_id
    WHERE m.estado IN ('Pendiente','En proceso')
      AND m.deleted_at IS NULL
    ORDER BY m.fecha DESC
    LIMIT 8
")->fetchAll();

ob_start();
?>
<!-- KPIs -->
<div class="kpi-grid">
  <div class="kpi-card yellow">
    <div class="kpi-icon">🚗</div>
    <div class="kpi-label">Vehículos</div>
    <div class="kpi-value"><?= $total_veh ?></div>
    <div class="kpi-sub">en inventario</div>
  </div>
  <div class="kpi-card cyan">
    <div class="kpi-icon">⛽</div>
    <div class="kpi-label">Litros este mes</div>
    <div class="kpi-value"><?= number_format($comb_mes['litros'], 0) ?></div>
    <div class="kpi-sub"><?= date('F Y') ?></div>
  </div>
  <div class="kpi-card green">
    <div class="kpi-icon">💰</div>
    <div class="kpi-label">Gasto combustible</div>
    <div class="kpi-value">$<?= number_format($comb_mes['gasto'], 0) ?></div>
    <div class="kpi-sub">mes actual</div>
  </div>
  <div class="kpi-card red">
    <div class="kpi-icon">⚠️</div>
    <div class="kpi-label">Incidentes abiertos</div>
    <div class="kpi-value"><?= $inc_abiertos ?></div>
    <div class="kpi-sub">pendientes de resolver</div>
  </div>
  <div class="kpi-card blue">
    <div class="kpi-icon">🔧</div>
    <div class="kpi-label">Mantenimientos</div>
    <div class="kpi-value"><?= $total_mant ?></div>
    <div class="kpi-sub">histórico total</div>
  </div>
  <div class="kpi-card orange">
    <div class="kpi-icon">👤</div>
    <div class="kpi-label">Operadores activos</div>
    <div class="kpi-value"><?= $total_op ?></div>
    <div class="kpi-sub">registrados</div>
  </div>
</div>

<!-- Charts -->
<div class="chart-grid">
  <div class="chart-card">
    <div class="chart-title">⛽ Consumo total por vehículo (litros)</div>
    <div id="chart-consumo"></div>
  </div>
  <div class="chart-card">
    <div class="chart-title">💸 Gasto en mantenimiento por vehículo</div>
    <div id="chart-mant"></div>
  </div>
</div>

<!-- Alertas e incidentes -->
<div class="two-col">
  <div>
    <div class="section-title">🔔 Recordatorios próximos (30 días)</div>
    <div class="alert-list">
      <?php if (!$alertas): ?>
        <div class="empty"><div class="empty-icon" style="font-size:28px">✅</div><div style="font-size:13px">Sin alertas próximas</div></div>
      <?php else: foreach ($alertas as $a):
        $dias = (int)$a['dias'];
        $cls  = $dias < 0 ? 'critical' : ($dias <= 7 ? '' : 'info');
        $txt  = $dias < 0 ? "Vencido hace ".abs($dias)."d" : ($dias === 0 ? 'Hoy' : "En {$dias}d");
      ?>
        <div class="alert-item <?= $cls ?>">
          <div class="alert-dot"></div>
          <div class="alert-text"><strong><?= htmlspecialchars($a['placa']) ?></strong> — <?= htmlspecialchars($a['tipo']) ?></div>
          <div class="alert-meta"><?= $txt ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <div>
    <div class="section-title">⚠️ Incidentes abiertos</div>
    <div class="alert-list">
      <?php if (!$inc_list): ?>
        <div class="empty"><div class="empty-icon" style="font-size:28px">✅</div><div style="font-size:13px">Todo en orden</div></div>
      <?php else: foreach ($inc_list as $i): ?>
        <div class="alert-item critical">
          <div class="alert-dot"></div>
          <div class="alert-text"><strong><?= htmlspecialchars($i['placa']) ?> <?= htmlspecialchars($i['marca']) ?></strong> — <?= htmlspecialchars(mb_substr($i['descripcion'],0,55)) ?><?= strlen($i['descripcion'])>55?'...':'' ?></div>
          <div class="alert-meta"><?= $i['fecha'] ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- Preventivos & OTs activas -->
<div class="two-col" style="margin-top:18px;">
  <div>
    <div class="section-title">📅 Alertas Preventivas</div>
    <div class="alert-list">
      <?php if (!$preventivo_alertas): ?>
        <div class="empty"><div class="empty-icon" style="font-size:28px">✅</div><div style="font-size:13px">Sin alertas preventivas</div></div>
      <?php else: foreach (array_slice($preventivo_alertas, 0, 8) as $pa):
        $cls = $pa['tipo'] === 'vencido' ? 'critical' : 'info';
      ?>
        <div class="alert-item <?= $cls ?>">
          <div class="alert-dot"></div>
          <div class="alert-text"><strong><?= htmlspecialchars($pa['placa']) ?></strong> — <?= htmlspecialchars($pa['servicio']) ?></div>
          <div class="alert-meta"><?= htmlspecialchars($pa['msg']) ?></div>
        </div>
      <?php endforeach; endif; ?>
      <?php if (count($preventivo_alertas) > 8): ?>
        <a href="/preventivos.php" style="font-size:12px;color:#47ffe8;text-decoration:none;display:block;text-align:right;margin-top:6px;">Ver todas →</a>
      <?php endif; ?>
    </div>
  </div>
  <div>
    <div class="section-title">🔧 OTs Activas</div>
    <div class="alert-list">
      <?php if (!$ots_activas): ?>
        <div class="empty"><div class="empty-icon" style="font-size:28px">✅</div><div style="font-size:13px">Sin OTs pendientes</div></div>
      <?php else: foreach ($ots_activas as $ot):
        $cls = $ot['estado'] === 'En proceso' ? '' : 'info';
      ?>
        <div class="alert-item <?= $cls ?>">
          <div class="alert-dot"></div>
          <div class="alert-text"><strong><?= htmlspecialchars($ot['placa']) ?></strong> — <?= htmlspecialchars($ot['tipo']) ?> (<?= htmlspecialchars($ot['estado']) ?>)</div>
          <div class="alert-meta">$<?= number_format($ot['costo'],0) ?> · <?= $ot['fecha'] ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<script>
const consumoData = <?= json_encode(array_values($consumo_chart)) ?>;
const mantData    = <?= json_encode(array_values($mant_chart)) ?>;
document.addEventListener('DOMContentLoaded', () => {
  renderBarChart('chart-consumo', consumoData, { unit: 'L',  color: '#47ffe8' });
  renderBarChart('chart-mant',    mantData,    { unit: '$',  color: '#e8ff47' });
});
</script>

<?php
$content = ob_get_clean();
echo render_layout('Dashboard', 'dashboard', $content);
?>
