<?php
require_once __DIR__ . '/auth.php';
$user = current_user();

function render_layout(string $page_title, string $active_page, string $content) {
    global $user;
    ob_start();
    $rol = $user['rol'];
    $is_admin   = in_array($rol, ['coordinador_it', 'admin']);
    $can_edit   = can('edit');
    $can_create = can('create');
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>

<aside id="sidebar">
  <div class="logo">
    <div class="logo-text">FlotaCtrl</div>
    <div class="logo-sub">Sistema de Flotas</div>
  </div>
  <nav>
    <div class="nav-section">Principal</div>
    <a href="/dashboard.php" class="nav-item <?= $active_page==='dashboard'?'active':'' ?>">
      <span class="nav-icon">📊</span><span>Dashboard</span>
    </a>
    <a href="/vehiculos.php" class="nav-item <?= $active_page==='vehiculos'?'active':'' ?>">
      <span class="nav-icon">🚗</span><span>Vehículos</span>
    </a>
    <a href="/asignaciones.php" class="nav-item <?= $active_page==='asignaciones'?'active':'' ?>">
      <span class="nav-icon">📝</span><span>Asignaciones</span>
    </a>
    <a href="/combustible.php" class="nav-item <?= $active_page==='combustible'?'active':'' ?>">
      <span class="nav-icon">⛽</span><span>Combustible</span>
    </a>

    <div class="nav-section">Gestión</div>
    <a href="/mantenimientos.php" class="nav-item <?= $active_page==='mantenimientos'?'active':'' ?>">
      <span class="nav-icon">🔧</span><span>Mantenimientos</span>
    </a>
    <a href="/incidentes.php" class="nav-item <?= $active_page==='incidentes'?'active':'' ?>">
      <span class="nav-icon">⚠️</span><span>Incidentes</span>
    </a>
    <a href="/recordatorios.php" class="nav-item <?= $active_page==='recordatorios'?'active':'' ?>">
      <span class="nav-icon">🔔</span><span>Recordatorios</span>
    </a>
    <a href="/reportes.php" class="nav-item <?= $active_page==='reportes'?'active':'' ?>">
      <span class="nav-icon">📈</span><span>Reportes</span>
    </a>

    <?php if ($can_edit || $is_admin): ?>
    <div class="nav-section">Administración</div>
    <a href="/operadores.php" class="nav-item <?= $active_page==='operadores'?'active':'' ?>">
      <span class="nav-icon">👤</span><span>Operadores</span>
    </a>
    <a href="/proveedores.php" class="nav-item <?= $active_page==='proveedores'?'active':'' ?>">
      <span class="nav-icon">🏪</span><span>Proveedores</span>
    </a>
    <?php endif; ?>

    <?php if ($is_admin): ?>
    <div class="nav-section">Sistema</div>
    <a href="/catalogos.php" class="nav-item <?= $active_page==='catalogos'?'active':'' ?>">
      <span class="nav-icon">🗂️</span><span>Catálogos</span>
    </a>
    <a href="/usuarios.php" class="nav-item <?= $active_page==='usuarios'?'active':'' ?>">
      <span class="nav-icon">🔑</span><span>Usuarios</span>
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= strtoupper(substr($user['nombre'], 0, 1)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($user['nombre']) ?></div>
        <div class="user-role badge <?= role_badge($rol) ?>"><?= role_label($rol) ?></div>
      </div>
    </div>
    <a href="/logout.php" class="btn-logout">Cerrar sesión</a>
  </div>
</aside>

<div id="main">
  <header class="topbar">
    <div class="topbar-title"><?= htmlspecialchars($page_title) ?></div>
    <div class="topbar-actions" id="topbar-actions">
      <?php if ($rol === 'monitoreo'): ?>
        <span class="badge badge-cyan" style="padding:6px 12px;font-size:11px">👁 Modo solo lectura</span>
      <?php endif; ?>
    </div>
  </header>
  <script src="/assets/app.js"></script>
  <div class="page-content">
    <?= $content ?>
  </div>
</div>

<div id="toast-container"></div>

<script>
// Pasar permisos del rol al JS
const USER_ROLE = <?= json_encode($rol) ?>;
const USER_CAN  = <?= json_encode(array_values(ROLE_PERMISSIONS[$rol] ?? [])) ?>;
function userCan(perm) { return USER_CAN.includes(perm); }
</script>
</body>
</html>
<?php
    return ob_get_clean();
}
