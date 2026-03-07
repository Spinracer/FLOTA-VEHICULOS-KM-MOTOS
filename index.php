<?php
require_once __DIR__ . '/includes/auth.php';

session_init();

// If fully logged in (no pending 2FA), go to dashboard
if (is_logged_in()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
$show2fa = false;

// ── Handle 2FA verification ──
if (($_SESSION['2fa_pending'] ?? false) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totp_code'])) {
    if (!csrf_validate()) {
        $error = 'Token de seguridad expirado. Recarga la página.';
    } else {
        $code = trim($_POST['totp_code'] ?? '');
        $db = getDB();
        $secret = totp_get_secret($db, (int)$_SESSION['user_id']);
        if ($secret && totp_verify($secret, $code)) {
            unset($_SESSION['2fa_pending']);
            csrf_regenerate();
            audit_log('auth', '2fa_verified', (int)$_SESSION['user_id'], [], ['email' => $_SESSION['user_email']]);
            header('Location: /dashboard.php');
            exit;
        } else {
            $error = 'Código 2FA incorrecto. Intenta de nuevo.';
        }
    }
    $show2fa = true;
} elseif (($_SESSION['2fa_pending'] ?? false)) {
    $show2fa = true;
}

// ── Handle login ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$show2fa && !isset($_POST['totp_code'])) {
    // Rate limiting on login
    rate_limit_enforce('login', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    if (!csrf_validate()) {
        $error = 'Token de seguridad expirado. Recarga la página.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');
        if (!$email || !$password) {
            $error = 'Ingresa tu correo y contraseña.';
        } elseif (!login($email, $password)) {
            $error = 'Correo o contraseña incorrectos.';
        } else {
            // Check if 2FA is enabled for this user
            $db = getDB();
            if (totp_is_enabled($db, (int)$_SESSION['user_id'])) {
                $_SESSION['2fa_pending'] = true;
                $show2fa = true;
            } else {
                csrf_regenerate();
                header('Location: /dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión — FlotaControl</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        dark: '#0a0c10', surface: '#111318', surface2: '#181c24',
        border: '#222730', accent: '#e8ff47', accent2: '#47ffe8',
        danger: '#ff4757', muted: '#8892a4',
      },
      fontFamily: { heading: ['Syne','sans-serif'], body: ['DM Sans','sans-serif'] }
    }
  }
}
</script>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="font-body min-h-screen flex items-center justify-center bg-dark">
<div class="bg-surface border border-border rounded-2xl p-10 sm:p-12 w-full max-w-md mx-4 shadow-2xl">
  <div class="font-heading font-extrabold text-3xl text-accent mb-1">FlotaCtrl</div>
  <div class="text-sm text-muted mb-8">Sistema de Administración de Flotas</div>

  <?php if ($error): ?>
  <div class="bg-danger/10 border border-danger text-danger px-4 py-3 rounded-lg text-sm mb-5"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($show2fa): ?>
  <!-- 2FA Verification Form -->
  <form method="POST" action="/index.php" class="space-y-4">
    <?= csrf_field() ?>
    <div class="text-center mb-4">
      <div class="text-4xl mb-2">🔐</div>
      <p class="text-sm text-muted">Ingresa el código de tu aplicación de autenticación</p>
    </div>
    <div class="flex flex-col gap-1.5">
      <label class="text-[11px] text-muted uppercase tracking-widest font-semibold">Código 2FA</label>
      <input type="text" name="totp_code" placeholder="000000" required autofocus
             maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code"
             class="bg-dark border border-border rounded-lg px-3.5 py-2.5 text-slate-100 text-sm text-center text-2xl tracking-[0.5em] font-mono focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none transition-all">
    </div>
    <button type="submit" class="w-full bg-accent text-dark font-semibold py-3 rounded-lg hover:brightness-90 transition-all text-sm mt-2">
      Verificar →
    </button>
    <div class="text-center">
      <a href="/logout.php" class="text-xs text-muted hover:text-accent transition-colors">← Cancelar e ir al login</a>
    </div>
  </form>

  <?php else: ?>
  <!-- Login Form -->
  <form method="POST" action="/index.php" class="space-y-4">
    <?= csrf_field() ?>
    <div class="flex flex-col gap-1.5">
      <label class="text-[11px] text-muted uppercase tracking-widest font-semibold">Correo electrónico</label>
      <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             placeholder="admin@flotacontrol.local" required autofocus
             class="bg-dark border border-border rounded-lg px-3.5 py-2.5 text-slate-100 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none transition-all">
    </div>
    <div class="flex flex-col gap-1.5">
      <label class="text-[11px] text-muted uppercase tracking-widest font-semibold">Contraseña</label>
      <input type="password" name="password" placeholder="••••••••" required
             class="bg-dark border border-border rounded-lg px-3.5 py-2.5 text-slate-100 text-sm focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none transition-all">
    </div>
    <button type="submit" class="w-full bg-accent text-dark font-semibold py-3 rounded-lg hover:brightness-90 transition-all text-sm mt-2">
      Ingresar al sistema →
    </button>
  </form>
  <?php endif; ?>

  <div class="mt-5 text-xs text-muted text-center">FlotaControl &bull; Sistema local en red</div>
</div>
<script>
// Apply saved theme
const t = localStorage.getItem('fc-theme');
if (t === 'light') {
  document.documentElement.classList.remove('dark');
  document.documentElement.classList.add('light');
  document.body.classList.remove('bg-dark');
  document.body.classList.add('bg-gray-50');
}
</script>
</body>
</html>
