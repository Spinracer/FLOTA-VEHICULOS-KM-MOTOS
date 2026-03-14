<?php
/**
 * Página externa de firma digital.
 * Acceso: /firma.php?token=XXXX
 * No requiere login — token-based auth.
 */
require_once __DIR__ . '/includes/db.php';

$token = trim($_GET['token'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

if (!$token || strlen($token) < 32) {
    http_response_code(400);
    die('Token inválido o faltante.');
}

$db = getDB();

// POST: guardar firma
if ($method === 'POST') {
    header('Content-Type: application/json');
    $d = json_decode(file_get_contents('php://input'), true);
    $firma = $d['firma_data'] ?? '';
    if (!$firma || !str_starts_with($firma, 'data:image/')) {
        http_response_code(400);
        echo json_encode(['error' => 'Firma inválida.']);
        exit;
    }
    // Try retorno token first, then entrega token
    $stmt = $db->prepare("SELECT id, estado, firma_data, firma_entrega_data FROM asignaciones WHERE firma_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $asig = $stmt->fetch();
    $momento = 'retorno';
    if (!$asig) {
        $stmt = $db->prepare("SELECT id, estado, firma_data, firma_entrega_data FROM asignaciones WHERE firma_entrega_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $asig = $stmt->fetch();
        $momento = 'entrega';
    }
    if (!$asig) {
        http_response_code(404);
        echo json_encode(['error' => 'Asignación no encontrada o token expirado.']);
        exit;
    }
    if ($momento === 'entrega') {
        if ($asig['firma_entrega_data']) {
            http_response_code(409);
            echo json_encode(['error' => 'Esta asignación ya tiene firma de entrega registrada.']);
            exit;
        }
        $db->prepare("UPDATE asignaciones SET firma_entrega_data = ?, firma_entrega_tipo = 'digital', firma_entrega_fecha = NOW(), firma_entrega_ip = ? WHERE firma_entrega_token = ?")
           ->execute([$firma, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $token]);
    } else {
        if ($asig['firma_data']) {
            http_response_code(409);
            echo json_encode(['error' => 'Esta asignación ya tiene una firma registrada.']);
            exit;
        }
        $db->prepare("UPDATE asignaciones SET firma_data = ?, firma_tipo = 'digital', firma_fecha = NOW(), firma_ip = ? WHERE firma_token = ?")
           ->execute([$firma, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $token]);
    }
    echo json_encode(['ok' => true, 'message' => 'Firma guardada exitosamente.']);
    exit;
}

// GET: mostrar formulario de firma
$stmt = $db->prepare("
    SELECT a.*, v.placa, v.marca, v.modelo,
           o.nombre AS operador_nombre
    FROM asignaciones a
    LEFT JOIN vehiculos v ON v.id = a.vehiculo_id
    LEFT JOIN operadores o ON o.id = a.operador_id
    WHERE a.firma_token = ?
    LIMIT 1
");
$stmt->execute([$token]);
$asig = $stmt->fetch();
$momento = 'retorno';
if (!$asig) {
    $stmt = $db->prepare("
        SELECT a.*, v.placa, v.marca, v.modelo,
               o.nombre AS operador_nombre
        FROM asignaciones a
        LEFT JOIN vehiculos v ON v.id = a.vehiculo_id
        LEFT JOIN operadores o ON o.id = a.operador_id
        WHERE a.firma_entrega_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $asig = $stmt->fetch();
    $momento = 'entrega';
}

if (!$asig) {
    http_response_code(404);
    die('Asignación no encontrada o token expirado.');
}

$yaFirmado = $momento === 'entrega' ? !empty($asig['firma_entrega_data']) : !empty($asig['firma_data']);
$folio = 'ASG-' . str_pad($asig['id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Firma Digital — <?= $folio ?></title>
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
        danger: '#ff4757', success: '#2ed573', muted: '#8892a4',
      },
      fontFamily: { heading: ['Syne','sans-serif'], body: ['DM Sans','sans-serif'] }
    }
  }
}
</script>
</head>
<body class="font-body bg-dark text-slate-200 min-h-screen flex flex-col items-center p-5">
<div class="font-heading font-extrabold text-2xl text-accent tracking-tight">FlotaCtrl</div>
<div class="bg-surface border border-border rounded-xl p-6 max-w-lg w-full mt-5">
  <h1 class="text-xl font-bold text-accent mb-1">✍️ Firma Digital</h1>
  <h2 class="text-sm text-muted font-normal mb-4"><?= $folio ?> — <?= $momento === 'entrega' ? 'Entrega' : 'Devolución' ?></h2>
  
  <div class="grid grid-cols-2 gap-2 mb-4 text-[13px]">
    <div class="bg-dark rounded-md px-3 py-2"><label class="text-muted text-[11px] block mb-0.5">Vehículo</label><span class="text-white font-medium"><?= htmlspecialchars($asig['placa'] . ' ' . $asig['marca'] . ' ' . $asig['modelo']) ?></span></div>
    <div class="bg-dark rounded-md px-3 py-2"><label class="text-muted text-[11px] block mb-0.5">Operador</label><span class="text-white font-medium"><?= htmlspecialchars($asig['operador_nombre']) ?></span></div>
    <div class="bg-dark rounded-md px-3 py-2"><label class="text-muted text-[11px] block mb-0.5">Inicio</label><span class="text-white font-medium"><?= $asig['start_at'] ?></span></div>
    <div class="bg-dark rounded-md px-3 py-2"><label class="text-muted text-[11px] block mb-0.5">KM</label><span class="text-white font-medium"><?= number_format((float)($asig['start_km'] ?? 0), 0) ?> km</span></div>
  </div>

  <?php if ($yaFirmado): ?>
    <?php 
      $fData = $momento === 'entrega' ? ($asig['firma_entrega_data'] ?? '') : ($asig['firma_data'] ?? '');
      $fFecha = $momento === 'entrega' ? ($asig['firma_entrega_fecha'] ?? '') : ($asig['firma_fecha'] ?? '');
    ?>
    <div class="bg-success/10 border border-success text-success px-4 py-3.5 rounded-lg text-center text-sm mt-3">✅ Esta asignación ya fue firmada el <?= htmlspecialchars($fFecha) ?>.</div>
    <?php if ($fData): ?>
      <div class="text-center mt-3">
        <img src="<?= htmlspecialchars($fData) ?>" alt="Firma" class="max-w-[300px] mx-auto border border-border rounded-lg bg-white p-2">
      </div>
    <?php endif; ?>
  <?php else: ?>
    <p class="text-[13px] text-muted mb-1">Dibuje su firma en el recuadro:</p>
    <canvas id="sig-canvas" width="460" height="180" class="block w-full border-2 border-dashed border-border rounded-lg bg-white cursor-crosshair touch-none my-3"></canvas>
    <div class="flex gap-3 mt-3">
      <button class="px-5 py-2.5 rounded-lg text-sm font-semibold bg-transparent text-muted border border-border hover:bg-surface2 transition-colors" onclick="clearSig()">🗑 Limpiar</button>
      <button class="px-5 py-2.5 rounded-lg text-sm font-semibold bg-accent text-dark hover:brightness-90 transition-all" onclick="submitSig()" id="btnSubmit">✅ Firmar</button>
    </div>
    <div id="result"></div>
  <?php endif; ?>
</div>

<?php if (!$yaFirmado): ?>
<script>
const canvas = document.getElementById('sig-canvas');
const ctx = canvas.getContext('2d');
let drawing = false;
let hasDrawn = false;

// Scale canvas for retina
const dpr = window.devicePixelRatio || 1;
const rect = canvas.getBoundingClientRect();
canvas.width = rect.width * dpr;
canvas.height = rect.height * dpr;
ctx.scale(dpr, dpr);
canvas.style.width = rect.width + 'px';
canvas.style.height = rect.height + 'px';

function getPos(e) {
  const r = canvas.getBoundingClientRect();
  const t = e.touches ? e.touches[0] : e;
  return { x: t.clientX - r.left, y: t.clientY - r.top };
}

canvas.addEventListener('mousedown', e => { drawing = true; hasDrawn = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
canvas.addEventListener('mousemove', e => { if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.strokeStyle = '#1a1a1a'; ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.stroke(); });
canvas.addEventListener('mouseup', () => drawing = false);
canvas.addEventListener('mouseleave', () => drawing = false);

canvas.addEventListener('touchstart', e => { e.preventDefault(); drawing = true; hasDrawn = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
canvas.addEventListener('touchmove', e => { e.preventDefault(); if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.strokeStyle = '#1a1a1a'; ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.stroke(); });
canvas.addEventListener('touchend', () => drawing = false);

function clearSig() { ctx.clearRect(0, 0, canvas.width / dpr, canvas.height / dpr); hasDrawn = false; }

async function submitSig() {
  if (!hasDrawn) { alert('Dibuje su firma primero.'); return; }
  const btn = document.getElementById('btnSubmit');
  btn.disabled = true;
  btn.textContent = 'Enviando...';
  // Get PNG with white background
  const tmpCanvas = document.createElement('canvas');
  tmpCanvas.width = canvas.width;
  tmpCanvas.height = canvas.height;
  const tmpCtx = tmpCanvas.getContext('2d');
  tmpCtx.fillStyle = '#ffffff';
  tmpCtx.fillRect(0, 0, tmpCanvas.width, tmpCanvas.height);
  tmpCtx.drawImage(canvas, 0, 0);
  const dataUrl = tmpCanvas.toDataURL('image/png');

  try {
    const res = await fetch('/firma.php?token=<?= urlencode($token) ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ firma_data: dataUrl })
    });
    const d = await res.json();
    if (res.ok && d.ok) {
      document.getElementById('result').innerHTML = '<div class="bg-success/10 border border-success text-success px-4 py-3.5 rounded-lg text-center text-sm mt-3">✅ Firma guardada exitosamente. Puede cerrar esta página.</div>';
      btn.style.display = 'none';
    } else {
      document.getElementById('result').innerHTML = `<div class="bg-danger/10 border border-danger text-danger px-4 py-3.5 rounded-lg text-center text-sm mt-3">❌ ${d.error || 'Error al guardar firma'}</div>`;
      btn.disabled = false;
      btn.textContent = '✅ Firmar';
    }
  } catch(e) {
    document.getElementById('result').innerHTML = `<div class="bg-danger/10 border border-danger text-danger px-4 py-3.5 rounded-lg text-center text-sm mt-3">❌ Error de conexión</div>`;
    btn.disabled = false;
    btn.textContent = '✅ Firmar';
  }
}
</script>
<?php endif; ?>
</body>
</html>
