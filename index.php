<?php
require_once __DIR__ . '/includes/auth.php';

session_init();
if (is_logged_in()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    if (!$email || !$password) {
        $error = 'Ingresa tu correo y contraseña.';
    } elseif (!login($email, $password)) {
        $error = 'Correo o contraseña incorrectos.';
    } else {
        header('Location: /dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión — FlotaControl</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="login-page">
<div class="login-card">
  <div class="login-logo">FlotaCtrl</div>
  <div class="login-sub">Sistema de Administración de Flotas</div>

  <?php if ($error): ?>
  <div class="login-error show"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="/index.php">
    <div class="form-group" style="margin-bottom:16px">
      <label>Correo electrónico</label>
      <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             placeholder="admin@flotacontrol.local" required autofocus>
    </div>
    <div class="form-group" style="margin-bottom:24px">
      <label>Contraseña</label>
      <input type="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">
      Ingresar al sistema →
    </button>
  </form>
  <div class="login-footer">FlotaControl &bull; Sistema local en red</div>
</div>
</body>
</html>
