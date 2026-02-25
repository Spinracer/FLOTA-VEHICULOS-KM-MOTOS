<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acceso denegado — FlotaControl</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
<style>
  body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#0a0c10; }
  .err-card { text-align:center; padding:48px 40px; background:#111318; border:1px solid #222730; border-radius:16px; max-width:420px; }
  .err-icon { font-size:56px; margin-bottom:16px; }
  .err-code { font-family:'Syne',sans-serif; font-size:72px; font-weight:800; color:#e8ff47; line-height:1; }
  .err-msg  { font-size:16px; color:#8892a4; margin:12px 0 28px; }
  .err-role { display:inline-block; background:#1a1f28; border:1px solid #2d3340; border-radius:8px;
              padding:8px 14px; font-size:12px; color:#e8ff47; margin-bottom:24px; }
</style>
</head>
<body>
<div class="err-card">
  <div class="err-icon">🚫</div>
  <div class="err-code">403</div>
  <p class="err-msg">No tienes permisos para acceder a esta sección.<br>Contacta al <strong>Coordinador IT</strong> si necesitas acceso.</p>
  <?php
  $rol = $_SESSION['user_rol'] ?? 'desconocido';
  $labels = ['coordinador_it'=>'Coordinador IT','soporte'=>'Soporte','monitoreo'=>'Monitoreo'];
  $label = $labels[$rol] ?? ucfirst($rol);
  ?>
  <div class="err-role">Tu rol actual: <?= htmlspecialchars($label) ?></div>
  <a href="/dashboard.php" class="btn btn-primary" style="display:inline-flex;gap:8px;align-items:center;">
    ← Volver al Dashboard
  </a>
</div>
</body>
</html>
