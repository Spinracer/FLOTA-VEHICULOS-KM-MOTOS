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

  <form method="POST" action="/index.php" class="space-y-4">
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
