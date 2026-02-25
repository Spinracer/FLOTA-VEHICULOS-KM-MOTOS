<?php
require_once __DIR__ . '/auth.php';
$user = current_user();

function render_layout(string $page_title, string $active_page, string $content) {
    global $user;
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> — FlotaControl</title>
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
    <div class="nav-section">Administración</div>
    <a href="/operadores.php" class="nav-item <?= $active_page==='operadores'?'active':'' ?>">
      <span class="nav-icon">👤</span><span>Operadores</span>
    </a>
    <a href="/proveedores.php" class="nav-item <?= $active_page==='proveedores'?'active':'' ?>">
      <span class="nav-icon">🏪</span><span>Proveedores</span>
    </a>
    <?php if ($user['rol'] === 'admin'): ?>
    <div class="nav-section">Sistema</div>
    <a href="/usuarios.php" class="nav-item <?= $active_page==='usuarios'?'active':'' ?>">
      <span class="nav-icon">🔑</span><span>Usuarios</span>
    </a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= strtoupper(substr($user['nombre'],0,1)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($user['nombre']) ?></div>
        <div class="user-role badge badge-<?= $user['rol']==='admin'?'yellow':($user['rol']==='operador'?'blue':'gray') ?>"><?= $user['rol'] ?></div>
      </div>
    </div>
    <a href="/logout.php" class="btn-logout">Cerrar sesión</a>
  </div>
</aside>

<div id="main">
  <header class="topbar">
    <div class="topbar-title"><?= htmlspecialchars($page_title) ?></div>
    <div class="topbar-actions" id="topbar-actions"></div>
  </header>
  <div class="page-content">
    <?= $content ?>
  </div>
</div>

<div id="toast-container"></div>

<script src="/assets/app.js"></script>
</body>
</html>
<?php
    return ob_get_clean();
}
