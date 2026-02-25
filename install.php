<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Instalador — FlotaControl</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DM Sans', sans-serif; background: #0a0c10; color: #f0f2f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  .card { background: #111318; border: 1px solid #222730; border-radius: 16px; padding: 44px; width: 100%; max-width: 520px; }
  h1 { font-family: 'Syne', sans-serif; font-size: 26px; color: #e8ff47; margin-bottom: 6px; }
  p { font-size: 14px; color: #8892a4; margin-bottom: 28px; }
  .step { margin-bottom: 14px; padding: 14px 18px; border-radius: 8px; font-size: 14px; border-left: 3px solid #222730; background: #181c24; }
  .step.ok  { border-left-color: #2ed573; color: #2ed573; }
  .step.err { border-left-color: #ff4757; color: #ff4757; }
  .step.info{ border-left-color: #e8ff47; color: #e8ff47; }
  .btn { display: inline-block; margin-top: 20px; padding: 11px 24px; background: #e8ff47; color: #0a0c10; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 14px; }
  .creds { background: #181c24; border: 1px solid #222730; border-radius: 10px; padding: 16px 20px; margin-top: 20px; font-size: 13px; }
  .creds strong { color: #e8ff47; }
  pre { font-size: 12px; color: #8892a4; margin-top: 8px; white-space: pre-wrap; }
</style>
</head>
<body>
<div class="card">
  <h1>⚙️ Instalador FlotaControl</h1>
  <p>Este script creará la base de datos y tablas necesarias para el sistema.</p>

<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'flotacontrol');

$log = [];
$ok  = true;

function step(string $msg, bool $success, string $detail = '') {
    global $log, $ok;
    $type = $success ? 'ok' : 'err';
    $icon = $success ? '✅' : '❌';
    echo "<div class='step {$type}'>{$icon} {$msg}" . ($detail ? "<br><small>{$detail}</small>" : '') . "</div>";
    if (!$success) $ok = false;
    flush();
}

// 1. Conexión sin BD
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    step("Conexión al servidor MySQL", true);
} catch (PDOException $e) {
    step("Conexión al servidor MySQL", false, $e->getMessage());
    echo "<pre>Verifica DB_HOST, DB_USER y DB_PASS en este archivo y en includes/db.php</pre>";
    exit;
}

// 2. Crear base de datos
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `".DB_NAME."`");
    step("Base de datos '".DB_NAME."' lista", true);
} catch (PDOException $e) {
    step("Crear base de datos", false, $e->getMessage());
    exit;
}

// 3. Tablas
$tables = [
"usuarios" => "CREATE TABLE IF NOT EXISTS usuarios (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,
  rol           ENUM('admin','operador','lectura') NOT NULL DEFAULT 'operador',
  activo        TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acceso DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"proveedores" => "CREATE TABLE IF NOT EXISTS proveedores (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nombre     VARCHAR(150) NOT NULL,
  tipo       VARCHAR(80)  NOT NULL DEFAULT 'Taller mecánico',
  telefono   VARCHAR(30)  NULL,
  email      VARCHAR(150) NULL,
  direccion  VARCHAR(255) NULL,
  notas      TEXT         NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"operadores" => "CREATE TABLE IF NOT EXISTS operadores (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  nombre         VARCHAR(150) NOT NULL,
  licencia       VARCHAR(50)  NULL,
  categoria_lic  VARCHAR(10)  NULL,
  venc_licencia  DATE         NULL,
  telefono       VARCHAR(30)  NULL,
  email          VARCHAR(150) NULL,
  estado         ENUM('Activo','Inactivo','Suspendido') NOT NULL DEFAULT 'Activo',
  notas          TEXT         NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"vehiculos" => "CREATE TABLE IF NOT EXISTS vehiculos (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  placa          VARCHAR(20)  NOT NULL UNIQUE,
  marca          VARCHAR(80)  NOT NULL,
  modelo         VARCHAR(80)  NOT NULL,
  anio           YEAR         NULL,
  tipo           VARCHAR(50)  NOT NULL DEFAULT 'Automóvil',
  combustible    VARCHAR(30)  NOT NULL DEFAULT 'Gasolina',
  km_actual      DECIMAL(10,1) NOT NULL DEFAULT 0,
  color          VARCHAR(40)  NULL,
  vin            VARCHAR(50)  NULL,
  estado         ENUM('Activo','En mantenimiento','Fuera de servicio') NOT NULL DEFAULT 'Activo',
  operador_id    INT          NULL,
  venc_seguro    DATE         NULL,
  notas          TEXT         NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (operador_id) REFERENCES operadores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"combustible" => "CREATE TABLE IF NOT EXISTS combustible (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  fecha       DATE         NOT NULL,
  vehiculo_id INT          NOT NULL,
  litros      DECIMAL(8,2) NOT NULL,
  costo_litro DECIMAL(8,2) NOT NULL DEFAULT 0,
  total       DECIMAL(10,2) NOT NULL DEFAULT 0,
  km          DECIMAL(10,1) NULL,
  proveedor_id INT         NULL,
  tipo_carga  VARCHAR(20)  NOT NULL DEFAULT 'Lleno',
  notas       TEXT         NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehiculo_id)  REFERENCES vehiculos(id)  ON DELETE CASCADE,
  FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"mantenimientos" => "CREATE TABLE IF NOT EXISTS mantenimientos (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  fecha        DATE         NOT NULL,
  vehiculo_id  INT          NOT NULL,
  tipo         VARCHAR(60)  NOT NULL DEFAULT 'Preventivo',
  descripcion  TEXT         NULL,
  costo        DECIMAL(10,2) NOT NULL DEFAULT 0,
  km           DECIMAL(10,1) NULL,
  proximo_km   DECIMAL(10,1) NULL,
  proveedor_id INT          NULL,
  estado       ENUM('Completado','En proceso','Pendiente') NOT NULL DEFAULT 'Completado',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehiculo_id)  REFERENCES vehiculos(id)  ON DELETE CASCADE,
  FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"incidentes" => "CREATE TABLE IF NOT EXISTS incidentes (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  fecha        DATE         NOT NULL,
  vehiculo_id  INT          NOT NULL,
  tipo         VARCHAR(60)  NOT NULL DEFAULT 'Falla mecánica',
  descripcion  TEXT         NOT NULL,
  severidad    ENUM('Baja','Media','Alta','Crítica') NOT NULL DEFAULT 'Media',
  estado       ENUM('Abierto','En proceso','Cerrado') NOT NULL DEFAULT 'Abierto',
  costo_est    DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"recordatorios" => "CREATE TABLE IF NOT EXISTS recordatorios (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  vehiculo_id  INT          NOT NULL,
  tipo         VARCHAR(80)  NOT NULL,
  descripcion  TEXT         NULL,
  fecha_limite DATE         NOT NULL,
  estado       ENUM('Pendiente','Completado','Cancelado') NOT NULL DEFAULT 'Pendiente',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        step("Tabla '{$name}' creada", true);
    } catch (PDOException $e) {
        step("Tabla '{$name}'", false, $e->getMessage());
    }
}

// 4. Usuario admin por defecto
$adminEmail = 'admin@flotacontrol.local';
$adminPass  = 'Admin1234!';
try {
    $exists = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE email = '{$adminEmail}'")->fetchColumn();
    if (!$exists) {
        $hash = password_hash($adminPass, PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO usuarios (nombre, email, password, rol) VALUES ('Administrador', '{$adminEmail}', '{$hash}', 'admin')");
        step("Usuario administrador creado", true);
    } else {
        step("Usuario administrador ya existe", true, "No se sobreescribió");
    }
} catch (PDOException $e) {
    step("Crear usuario admin", false, $e->getMessage());
}

if ($ok): ?>
<div class="creds">
  <strong>✅ Instalación completada</strong><br><br>
  Accede con estas credenciales iniciales:<br><br>
  📧 <strong>Email:</strong> admin@flotacontrol.local<br>
  🔒 <strong>Contraseña:</strong> Admin1234!<br><br>
  <small style="color:#ff4757">⚠️ Cambia la contraseña al ingresar. Elimina este archivo <code>install.php</code> del servidor.</small>
</div>
<a href="/index.php" class="btn">Ir al sistema →</a>
<?php else: ?>
<div style="margin-top:20px;color:#ff4757;font-size:13px">❌ La instalación no se completó correctamente. Revisa los errores anteriores.</div>
<?php endif; ?>

</div>
</body>
</html>
