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
    $stmt = $db->prepare("SELECT id, estado, firma_data FROM asignaciones WHERE firma_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $asig = $stmt->fetch();
    if (!$asig) {
        http_response_code(404);
        echo json_encode(['error' => 'Asignación no encontrada o token expirado.']);
        exit;
    }
    if ($asig['firma_data']) {
        http_response_code(409);
        echo json_encode(['error' => 'Esta asignación ya tiene una firma registrada.']);
        exit;
    }
    $db->prepare("UPDATE asignaciones SET firma_data = ?, firma_tipo = 'digital', firma_fecha = NOW(), firma_ip = ? WHERE firma_token = ?")
       ->execute([$firma, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $token]);
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

if (!$asig) {
    http_response_code(404);
    die('Asignación no encontrada o token expirado.');
}

$yaFirmado = !empty($asig['firma_data']);
$folio = 'ASG-' . str_pad($asig['id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Firma Digital — <?= $folio ?></title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Segoe UI',Arial,sans-serif; background:#0f1117; color:#e0e0e0; min-height:100vh; display:flex; flex-direction:column; align-items:center; padding:20px; }
  .card { background:#1a1e27; border:1px solid #222730; border-radius:12px; padding:24px; max-width:500px; width:100%; margin-top:20px; }
  h1 { font-size:20px; color:#e8ff47; margin-bottom:4px; }
  h2 { font-size:15px; color:#8892a4; font-weight:400; margin-bottom:16px; }
  .info { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:16px; font-size:13px; }
  .info div { background:#13151b; padding:8px 12px; border-radius:6px; }
  .info label { color:#8892a4; font-size:11px; display:block; margin-bottom:2px; }
  .info span { color:#fff; font-weight:500; }
  canvas { display:block; width:100%; border:2px dashed #333; border-radius:8px; background:#13151b; cursor:crosshair; touch-action:none; margin:12px 0; }
  .actions { display:flex; gap:12px; margin-top:12px; }
  .btn { padding:10px 20px; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; transition:.2s; }
  .btn-primary { background:#e8ff47; color:#000; }
  .btn-primary:hover { background:#d4eb3c; }
  .btn-ghost { background:transparent; color:#8892a4; border:1px solid #333; }
  .btn-ghost:hover { background:#222; }
  .success { background:#1a2e1a; border:1px solid #2ed573; color:#2ed573; padding:16px; border-radius:8px; text-align:center; font-size:14px; margin-top:12px; }
  .error { background:#2e1a1a; border:1px solid #ff4757; color:#ff4757; padding:16px; border-radius:8px; text-align:center; font-size:14px; margin-top:12px; }
  .logo { font-size:24px; font-weight:800; color:#e8ff47; letter-spacing:-0.5px; }
</style>
</head>
<body>
<div class="logo">FlotaCtrl</div>
<div class="card">
  <h1>✍️ Firma Digital</h1>
  <h2><?= $folio ?> — <?= $asig['estado'] === 'Cerrada' ? 'Devolución' : 'Entrega' ?></h2>
  
  <div class="info">
    <div><label>Vehículo</label><span><?= htmlspecialchars($asig['placa'] . ' ' . $asig['marca'] . ' ' . $asig['modelo']) ?></span></div>
    <div><label>Operador</label><span><?= htmlspecialchars($asig['operador_nombre']) ?></span></div>
    <div><label>Inicio</label><span><?= $asig['start_at'] ?></span></div>
    <div><label>KM</label><span><?= number_format((float)($asig['start_km'] ?? 0), 0) ?> km</span></div>
  </div>

  <?php if ($yaFirmado): ?>
    <div class="success">✅ Esta asignación ya fue firmada el <?= $asig['firma_fecha'] ?>.</div>
    <?php if ($asig['firma_data']): ?>
      <div style="text-align:center;margin-top:12px">
        <img src="<?= htmlspecialchars($asig['firma_data']) ?>" alt="Firma" style="max-width:300px;border:1px solid #333;border-radius:8px;background:#fff;padding:8px">
      </div>
    <?php endif; ?>
  <?php else: ?>
    <p style="font-size:13px;color:#8892a4;margin-bottom:4px">Dibuje su firma en el recuadro:</p>
    <canvas id="sig-canvas" width="460" height="180"></canvas>
    <div class="actions">
      <button class="btn btn-ghost" onclick="clearSig()">🗑 Limpiar</button>
      <button class="btn btn-primary" onclick="submitSig()" id="btnSubmit">✅ Firmar</button>
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
      document.getElementById('result').innerHTML = '<div class="success">✅ Firma guardada exitosamente. Puede cerrar esta página.</div>';
      btn.style.display = 'none';
    } else {
      document.getElementById('result').innerHTML = `<div class="error">❌ ${d.error || 'Error al guardar firma'}</div>`;
      btn.disabled = false;
      btn.textContent = '✅ Firmar';
    }
  } catch(e) {
    document.getElementById('result').innerHTML = `<div class="error">❌ Error de conexión</div>`;
    btn.disabled = false;
    btn.textContent = '✅ Firmar';
  }
}
</script>
<?php endif; ?>
</body>
</html>
