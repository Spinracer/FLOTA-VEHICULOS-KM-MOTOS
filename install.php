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
// Cargar .env si existe
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (!$line || str_starts_with($line,'#') || !str_contains($line,'=')) continue;
        [$k,$v] = explode('=', $line, 2);
        putenv(trim($k).'='.trim(trim($v),'"\''));
    }
}
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'flotacontrol');
define('DB_SOCKET', getenv('DB_SOCKET') ?: '');

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
    $installDsn = DB_SOCKET
        ? "mysql:unix_socket=" . DB_SOCKET . ";charset=utf8mb4"
        : "mysql:host=" . DB_HOST . ";charset=utf8mb4";
    $pdo = new PDO($installDsn, DB_USER, DB_PASS, [
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
  rol           ENUM('coordinador_it','soporte','monitoreo','taller','admin','operador','lectura') NOT NULL DEFAULT 'monitoreo',
  proveedor_id  INT NULL,
  activo        TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acceso DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_usuarios_proveedor (proveedor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"proveedores" => "CREATE TABLE IF NOT EXISTS proveedores (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nombre     VARCHAR(150) NOT NULL,
  tipo       VARCHAR(80)  NOT NULL DEFAULT 'Taller mecánico',
  es_taller_autorizado TINYINT(1) NOT NULL DEFAULT 0,
  telefono   VARCHAR(30)  NULL,
  email      VARCHAR(150) NULL,
  direccion  VARCHAR(255) NULL,
  notas      TEXT         NULL,
  INDEX idx_prov_taller_autorizado (es_taller_autorizado),
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
  operador_id INT          NULL,
  litros      DECIMAL(8,2) NOT NULL,
  costo_litro DECIMAL(8,2) NOT NULL DEFAULT 0,
  total       DECIMAL(10,2) NOT NULL DEFAULT 0,
  km          DECIMAL(10,1) NULL,
  proveedor_id INT         NULL,
  metodo_pago VARCHAR(30)  NOT NULL DEFAULT 'Efectivo',
  numero_recibo VARCHAR(80) NULL,
  tipo_carga  VARCHAR(20)  NOT NULL DEFAULT 'Lleno',
  notas       TEXT         NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_combustible_operador (operador_id),
  FOREIGN KEY (vehiculo_id)  REFERENCES vehiculos(id)  ON DELETE CASCADE,
  FOREIGN KEY (operador_id) REFERENCES operadores(id) ON DELETE SET NULL,
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
  exit_km      DECIMAL(10,1) NULL,
  proximo_km   DECIMAL(10,1) NULL,
  proveedor_id INT          NULL,
  estado       ENUM('Completado','En proceso','Pendiente','Cancelado') NOT NULL DEFAULT 'Pendiente',
  resumen      TEXT         NULL,
  completed_at DATETIME     NULL,
  completed_by INT          NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehiculo_id)  REFERENCES vehiculos(id)  ON DELETE CASCADE,
  FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"asignaciones" => "CREATE TABLE IF NOT EXISTS asignaciones (
  id              BIGINT AUTO_INCREMENT PRIMARY KEY,
  vehiculo_id      INT NOT NULL,
  operador_id      INT NOT NULL,
  start_at         DATETIME NOT NULL,
  start_km         DECIMAL(10,1) NULL,
  start_notes      TEXT NULL,
  end_at           DATETIME NULL,
  end_km           DECIMAL(10,1) NULL,
  end_notes        TEXT NULL,
  estado           ENUM('Activa','Cerrada') NOT NULL DEFAULT 'Activa',
  override_reason  TEXT NULL,
  created_by       INT NULL,
  closed_by        INT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_asignaciones_vehiculo_estado (vehiculo_id, estado),
  INDEX idx_asignaciones_operador_estado (operador_id, estado),
  CONSTRAINT fk_asig_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE,
  CONSTRAINT fk_asig_operador FOREIGN KEY (operador_id) REFERENCES operadores(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"incidentes" => "CREATE TABLE IF NOT EXISTS incidentes (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  fecha             DATE         NOT NULL,
  vehiculo_id       INT          NOT NULL,
  tipo              VARCHAR(60)  NOT NULL DEFAULT 'Falla mecánica',
  descripcion       TEXT         NOT NULL,
  severidad         ENUM('Baja','Media','Alta','Crítica') NOT NULL DEFAULT 'Media',
  estado            ENUM('Abierto','En proceso','Cerrado') NOT NULL DEFAULT 'Abierto',
  costo_est         DECIMAL(10,2) NOT NULL DEFAULT 0,
  aseguradora       VARCHAR(150) NULL,
  poliza_numero     VARCHAR(80)  NULL,
  tiene_reclamo     TINYINT(1)   NOT NULL DEFAULT 0,
  estado_reclamo    ENUM('N/A','En proceso','Aprobado','Rechazado','Pagado') NOT NULL DEFAULT 'N/A',
  monto_reclamo     DECIMAL(10,2) NOT NULL DEFAULT 0,
  fecha_reclamo     DATE         NULL,
  referencia_reclamo VARCHAR(100) NULL,
  notas_seguro      TEXT         NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
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

"odometer_logs" => "CREATE TABLE IF NOT EXISTS odometer_logs (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id  INT NOT NULL,
  reading_km  DECIMAL(10,1) NOT NULL,
  source      VARCHAR(30) NOT NULL,
  recorded_at DATETIME NOT NULL,
  user_id     INT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_odo_vehicle_date (vehicle_id, recorded_at),
  CONSTRAINT fk_odometer_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehiculos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"catalogo_categorias_gasto" => "CREATE TABLE IF NOT EXISTS catalogo_categorias_gasto (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(120) NOT NULL UNIQUE,
  descripcion VARCHAR(255) NULL,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"catalogo_unidades" => "CREATE TABLE IF NOT EXISTS catalogo_unidades (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  clave       VARCHAR(20) NULL,
  nombre      VARCHAR(120) NOT NULL UNIQUE,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"catalogo_tipos_mantenimiento" => "CREATE TABLE IF NOT EXISTS catalogo_tipos_mantenimiento (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(120) NOT NULL UNIQUE,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"catalogo_estados_vehiculo" => "CREATE TABLE IF NOT EXISTS catalogo_estados_vehiculo (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(120) NOT NULL UNIQUE,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"catalogo_servicios_taller" => "CREATE TABLE IF NOT EXISTS catalogo_servicios_taller (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(120) NOT NULL UNIQUE,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"system_settings" => "CREATE TABLE IF NOT EXISTS system_settings (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  key_name    VARCHAR(160) NOT NULL UNIQUE,
  value_text  VARCHAR(255) NULL,
  value_num   DECIMAL(14,4) NULL,
  description VARCHAR(255) NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"audit_logs" => "CREATE TABLE IF NOT EXISTS audit_logs (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NULL,
  user_email   VARCHAR(150) NULL,
  user_rol     VARCHAR(50) NULL,
  entidad      VARCHAR(80) NOT NULL,
  entidad_id   BIGINT NULL,
  accion       VARCHAR(40) NOT NULL,
  antes_json   JSON NULL,
  despues_json JSON NULL,
  meta_json    JSON NULL,
  ip           VARCHAR(45) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_entidad_fecha (entidad, created_at),
  INDEX idx_audit_user_fecha (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"components" => "CREATE TABLE IF NOT EXISTS components (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(150) NOT NULL,
  tipo         ENUM('tool','safety','document','card','accessory') NOT NULL DEFAULT 'tool',
  descripcion  TEXT NULL,
  activo       TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_components_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"vehicle_components" => "CREATE TABLE IF NOT EXISTS vehicle_components (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  vehiculo_id    INT NOT NULL,
  component_id   INT NOT NULL,
  cantidad       INT NOT NULL DEFAULT 1,
  estado         ENUM('Bueno','Regular','Malo','Faltante') NOT NULL DEFAULT 'Bueno',
  numero_serie   VARCHAR(100) NULL,
  proveedor      VARCHAR(150) NULL,
  fecha_instalacion DATE NULL,
  fecha_vencimiento DATE NULL,
  notas          TEXT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_vc_vehiculo (vehiculo_id),
  INDEX idx_vc_component (component_id),
  INDEX idx_vc_estado (estado),
  CONSTRAINT fk_vc_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE,
  CONSTRAINT fk_vc_component FOREIGN KEY (component_id) REFERENCES components(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"mantenimiento_items" => "CREATE TABLE IF NOT EXISTS mantenimiento_items (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  mantenimiento_id INT NOT NULL,
  descripcion      VARCHAR(255) NOT NULL,
  cantidad         DECIMAL(10,2) NOT NULL DEFAULT 1,
  unidad           VARCHAR(20) NOT NULL DEFAULT 'PZA',
  precio_unitario  DECIMAL(10,2) NOT NULL DEFAULT 0,
  subtotal         DECIMAL(12,2) GENERATED ALWAYS AS (cantidad * precio_unitario) STORED,
  notas            TEXT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_mi_mantenimiento (mantenimiento_id),
  CONSTRAINT fk_mi_mantenimiento FOREIGN KEY (mantenimiento_id) REFERENCES mantenimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"assignment_component_snapshots" => "CREATE TABLE IF NOT EXISTS assignment_component_snapshots (
  id              BIGINT AUTO_INCREMENT PRIMARY KEY,
  asignacion_id   BIGINT NOT NULL,
  vehiculo_id     INT NOT NULL,
  momento         ENUM('entrega','retorno') NOT NULL,
  component_id    INT NOT NULL,
  componente_nombre VARCHAR(150) NOT NULL,
  componente_tipo VARCHAR(30) NOT NULL,
  estado          ENUM('Bueno','Regular','Malo','Faltante') NOT NULL,
  cantidad        INT NOT NULL DEFAULT 1,
  numero_serie    VARCHAR(100) NULL,
  observaciones   TEXT NULL,
  created_by      INT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_acs_asignacion (asignacion_id),
  INDEX idx_acs_vehiculo_momento (vehiculo_id, momento),
  CONSTRAINT fk_acs_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones(id) ON DELETE CASCADE,
  CONSTRAINT fk_acs_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE,
  CONSTRAINT fk_acs_component FOREIGN KEY (component_id) REFERENCES components(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"role_module_permissions" => "CREATE TABLE IF NOT EXISTS role_module_permissions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  rol         VARCHAR(30) NOT NULL,
  modulo      VARCHAR(60) NOT NULL,
  permiso     VARCHAR(30) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rmp (rol, modulo, permiso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"attachments" => "CREATE TABLE IF NOT EXISTS attachments (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  entidad         VARCHAR(60) NOT NULL,
  entidad_id      INT NOT NULL,
  filename        VARCHAR(255) NOT NULL,
  original_name   VARCHAR(255) NOT NULL,
  mime_type       VARCHAR(100) NOT NULL,
  size_bytes      INT NOT NULL DEFAULT 0,
  uploaded_by     INT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at      DATETIME NULL,
  INDEX idx_att_entidad (entidad, entidad_id),
  INDEX idx_att_user (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"preventive_intervals" => "CREATE TABLE IF NOT EXISTS preventive_intervals (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  vehiculo_id     INT NOT NULL,
  tipo            VARCHAR(60) NOT NULL,
  cada_km         DECIMAL(10,1) NULL COMMENT 'Cada cuántos km',
  cada_dias       INT NULL COMMENT 'Cada cuántos días',
  ultimo_km       DECIMAL(10,1) NULL COMMENT 'KM del último servicio',
  ultima_fecha    DATE NULL COMMENT 'Fecha del último servicio',
  proveedor_id    INT NULL,
  activo          TINYINT(1) NOT NULL DEFAULT 1,
  notas           TEXT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pi_vehiculo (vehiculo_id),
  INDEX idx_pi_activo (activo),
  CONSTRAINT fk_pi_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($tables as $name => $sql) {
  try {
    $pdo->exec($sql);
    step("Tabla '{$name}' creada", true);
  } catch (Throwable $e) {
    step("Tabla '{$name}'", false, $e->getMessage());
  }
}

// 3.1 Ajustes de compatibilidad para instalaciones existentes
$dbNameEsc = str_replace("'", "''", DB_NAME);
$existsColumn = function (string $table, string $column) use ($pdo, $dbNameEsc): bool {
  $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='{$dbNameEsc}' AND TABLE_NAME='".str_replace("'","''",$table)."' AND COLUMN_NAME='".str_replace("'","''",$column)."'");
  return (int)$stmt->fetchColumn() > 0;
};
$existsIndex = function (string $table, string $index) use ($pdo, $dbNameEsc): bool {
  $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='{$dbNameEsc}' AND TABLE_NAME='".str_replace("'","''",$table)."' AND INDEX_NAME='".str_replace("'","''",$index)."'");
  return (int)$stmt->fetchColumn() > 0;
};
$existsFk = function (string $table, string $constraint) use ($pdo, $dbNameEsc): bool {
  $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA='{$dbNameEsc}' AND TABLE_NAME='".str_replace("'","''",$table)."' AND CONSTRAINT_NAME='".str_replace("'","''",$constraint)."' AND CONSTRAINT_TYPE='FOREIGN KEY'");
  return (int)$stmt->fetchColumn() > 0;
};

try {
  if (!$existsColumn('proveedores', 'es_taller_autorizado')) {
    $pdo->exec("ALTER TABLE proveedores ADD COLUMN es_taller_autorizado TINYINT(1) NOT NULL DEFAULT 0");
    step('Compat: proveedores.es_taller_autorizado', true);
  } else {
    step('Compat: proveedores.es_taller_autorizado', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Compat: proveedores.es_taller_autorizado', false, $e->getMessage());
}

try {
  if (!$existsIndex('proveedores', 'idx_prov_taller_autorizado')) {
    $pdo->exec("ALTER TABLE proveedores ADD INDEX idx_prov_taller_autorizado (es_taller_autorizado)");
    step('Compat: índice proveedores autorizados', true);
  } else {
    step('Compat: índice proveedores autorizados', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Compat: índice proveedores autorizados', false, $e->getMessage());
}

try {
  $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('coordinador_it','soporte','monitoreo','taller','admin','operador','lectura') NOT NULL DEFAULT 'monitoreo'");
  step('Compat: rol taller en usuarios', true);
} catch (Throwable $e) {
  step('Compat: rol taller en usuarios', false, $e->getMessage());
}

try {
  if (!$existsColumn('usuarios', 'proveedor_id')) {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN proveedor_id INT NULL");
    step('Compat: usuarios.proveedor_id', true);
  } else {
    step('Compat: usuarios.proveedor_id', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Compat: usuarios.proveedor_id', false, $e->getMessage());
}

try {
  if (!$existsIndex('usuarios', 'idx_usuarios_proveedor')) {
    $pdo->exec("ALTER TABLE usuarios ADD INDEX idx_usuarios_proveedor (proveedor_id)");
    step('Compat: índice usuarios.proveedor_id', true);
  } else {
    step('Compat: índice usuarios.proveedor_id', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Compat: índice usuarios.proveedor_id', false, $e->getMessage());
}

try {
  if (!$existsFk('usuarios', 'fk_usuarios_proveedor')) {
    $pdo->exec("ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL");
    step('Compat: FK usuarios->proveedores', true);
  } else {
    step('Compat: FK usuarios->proveedores', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Compat: FK usuarios->proveedores', true, 'Omitido: ' . htmlspecialchars($e->getMessage()));
}

try {
  if (!$existsColumn('combustible', 'operador_id')) {
    $pdo->exec("ALTER TABLE combustible ADD COLUMN operador_id INT NULL AFTER vehiculo_id");
    step('Compat: combustible.operador_id', true);
  } else {
    step('Compat: combustible.operador_id', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Compat: combustible.operador_id', false, $e->getMessage());
}

try {
  if (!$existsColumn('combustible', 'metodo_pago')) {
    $pdo->exec("ALTER TABLE combustible ADD COLUMN metodo_pago VARCHAR(30) NOT NULL DEFAULT 'Efectivo' AFTER proveedor_id");
    step('Compat: combustible.metodo_pago', true);
  } else {
    step('Compat: combustible.metodo_pago', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Compat: combustible.metodo_pago', false, $e->getMessage());
}

try {
  if (!$existsColumn('combustible', 'numero_recibo')) {
    $pdo->exec("ALTER TABLE combustible ADD COLUMN numero_recibo VARCHAR(80) NULL AFTER metodo_pago");
    step('Compat: combustible.numero_recibo', true);
  } else {
    step('Compat: combustible.numero_recibo', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Compat: combustible.numero_recibo', false, $e->getMessage());
}

try {
  if (!$existsIndex('combustible', 'idx_combustible_operador')) {
    $pdo->exec("ALTER TABLE combustible ADD INDEX idx_combustible_operador (operador_id)");
    step('Compat: índice combustible.operador_id', true);
  } else {
    step('Compat: índice combustible.operador_id', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Compat: índice combustible.operador_id', false, $e->getMessage());
}

try {
  if (!$existsFk('combustible', 'fk_combustible_operador')) {
    $pdo->exec("ALTER TABLE combustible ADD CONSTRAINT fk_combustible_operador FOREIGN KEY (operador_id) REFERENCES operadores(id) ON DELETE SET NULL");
    step('Compat: FK combustible->operadores', true);
  } else {
    step('Compat: FK combustible->operadores', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Compat: FK combustible->operadores', true, 'Omitido: ' . htmlspecialchars($e->getMessage()));
}

// 3.1 Datos semilla de catálogos base
$seedCatalogs = [
  ["INSERT IGNORE INTO catalogo_categorias_gasto (nombre,descripcion) VALUES ('Repuestos','Partes y refacciones'), ('Lubricantes','Aceites y fluidos'), ('Mano de obra','Servicios técnicos'), ('Llantas','Neumáticos y reparación')", 'Semilla categorías de gasto'],
  ["INSERT IGNORE INTO catalogo_unidades (clave,nombre) VALUES ('L','Litros'), ('GAL','Galones'), ('PZA','Pieza'), ('SERV','Servicio')", 'Semilla unidades'],
  ["INSERT IGNORE INTO catalogo_tipos_mantenimiento (nombre) VALUES ('Preventivo'), ('Correctivo'), ('Inspección'), ('Emergencia'), ('Aceite y Filtros'), ('Frenos'), ('Llantas'), ('Batería'), ('Revisión general')", 'Semilla tipos de mantenimiento'],
  ["INSERT IGNORE INTO catalogo_estados_vehiculo (nombre) VALUES ('Activo'), ('En mantenimiento'), ('Fuera de servicio')", 'Semilla estados de vehículo'],
  ["INSERT IGNORE INTO catalogo_servicios_taller (nombre) VALUES ('Mecánica general'), ('Electricidad automotriz'), ('Llantería'), ('Alineación y balanceo')", 'Semilla servicios de taller'],
  ["INSERT IGNORE INTO system_settings (key_name,value_num,description) VALUES ('fuel.anomaly_threshold',15,'Porcentaje mínimo bajo promedio para marcar anomalía')", 'Semilla configuración global'],
  ["INSERT IGNORE INTO system_settings (key_name,value_num,description) VALUES ('fuel.max_litros_evento',200,'Máximo de litros permitidos por carga (0=sin límite)')", 'Semilla max litros'],
  ["INSERT IGNORE INTO system_settings (key_name,value_num,description) VALUES ('maintenance.umbral_aprobacion',5000,'Costo de OT que requiere aprobación especial (0=sin umbral)')", 'Semilla umbral aprobación'],
  ["INSERT IGNORE INTO system_settings (key_name,value_num,description) VALUES ('maintenance.umbral_adjuntos',3000,'Costo de OT sobre el cual se requieren adjuntos para completar (0=sin umbral)')", 'Semilla umbral adjuntos OT'],
  ["INSERT IGNORE INTO components (nombre,tipo,descripcion) VALUES
    ('Gato hidráulico','tool','Gato para cambio de llanta'),
    ('Llave de ruedas','tool','Cruz para tuercas de rueda'),
    ('Triángulo de seguridad','safety','Triángulo reflectivo de emergencia'),
    ('Chaleco reflectivo','safety','Chaleco de alta visibilidad'),
    ('Extintor','safety','Extintor ABC 1kg mínimo'),
    ('Botiquín primeros auxilios','safety','Kit básico de primeros auxilios'),
    ('Cable de arranque','tool','Cables pasa-corriente'),
    ('Tarjeta de circulación','card','Tarjeta de circulación vehicular'),
    ('Póliza de seguro','document','Póliza de seguro vigente'),
    ('Verificación vehicular','document','Constancia de verificación'),
    ('Llanta de refacción','accessory','Llanta de repuesto'),
    ('Herramienta básica','tool','Juego de desarmadores y llaves')", 'Semilla componentes base'],
];

foreach ($seedCatalogs as [$sql, $label]) {
  try {
    $pdo->exec($sql);
    step($label, true);
  } catch (Throwable $e) {
    step($label, false, $e->getMessage());
  }
}

// 3.2 Soft-delete: añadir columna deleted_at a tablas principales
$softDeleteTables = ['vehiculos', 'operadores', 'proveedores', 'mantenimientos', 'combustible', 'incidentes', 'recordatorios'];
foreach ($softDeleteTables as $tbl) {
  try {
    if (!$existsColumn($tbl, 'deleted_at')) {
      $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
      step("Soft-delete: {$tbl}.deleted_at", true);
    } else {
      step("Soft-delete: {$tbl}.deleted_at", true, 'Ya existe');
    }
  } catch (Throwable $e) {
    step("Soft-delete: {$tbl}.deleted_at", false, $e->getMessage());
  }
}

// 3.25 Agregar estado 'Cancelado' a mantenimientos
try {
  $pdo->exec("ALTER TABLE mantenimientos MODIFY COLUMN estado ENUM('Completado','En proceso','Pendiente','Cancelado') NOT NULL DEFAULT 'Pendiente'");
  step("Compat: mantenimientos.estado con Cancelado", true);
} catch (Throwable $e) {
  step("Compat: mantenimientos.estado con Cancelado", true, 'Ya actualizado o error: ' . $e->getMessage());
}

// 3.26 Columnas de cierre OT: exit_km, resumen, completed_at, completed_by
$mantNewCols = [
  ['exit_km', "DECIMAL(10,1) NULL AFTER km"],
  ['resumen', "TEXT NULL AFTER estado"],
  ['completed_at', "DATETIME NULL AFTER resumen"],
  ['completed_by', "INT NULL AFTER completed_at"],
];
foreach ($mantNewCols as [$col, $def]) {
  try {
    if (!$existsColumn('mantenimientos', $col)) {
      $pdo->exec("ALTER TABLE mantenimientos ADD COLUMN {$col} {$def}");
      step("Compat: mantenimientos.{$col}", true);
    } else {
      step("Compat: mantenimientos.{$col}", true, 'Ya existe');
    }
  } catch (Throwable $e) {
    step("Compat: mantenimientos.{$col}", false, $e->getMessage());
  }
}

// 3.27 Seed: matriz de permisos granular por módulo
$modules = ['vehiculos','asignaciones','mantenimientos','combustible','incidentes','recordatorios','operadores','proveedores','componentes','preventivos','reportes','catalogos','usuarios','auditoria'];
$permsByRole = [
  'coordinador_it' => ['view','create','edit','delete'],
  'admin'          => ['view','create','edit','delete'],
  'soporte'        => ['view','create','edit'],
  'taller'         => ['view','create','edit'],
  'monitoreo'      => ['view'],
  'operador'       => ['view','create','edit'],
  'lectura'        => ['view'],
];
// Restricciones extras para taller (solo mant/proveedores)
$tallerModules = ['mantenimientos','proveedores','componentes','preventivos'];
try {
  $checkRmp = $pdo->query("SELECT COUNT(*) FROM role_module_permissions");
  $existingRmp = (int)$checkRmp->fetchColumn();
  if ($existingRmp === 0) {
    $insRmp = $pdo->prepare("INSERT IGNORE INTO role_module_permissions (rol,modulo,permiso) VALUES (?,?,?)");
    foreach ($permsByRole as $rol => $perms) {
      foreach ($modules as $mod) {
        $effectivePerms = $perms;
        // Taller solo puede editar ciertos módulos
        if ($rol === 'taller' && !in_array($mod, $tallerModules)) {
          $effectivePerms = ['view'];
        }
        // Monitoreo/lectura no accede a usuarios/auditoria
        if (in_array($rol, ['monitoreo','lectura']) && in_array($mod, ['usuarios','catalogos'])) {
          continue;
        }
        foreach ($effectivePerms as $perm) {
          $insRmp->execute([$rol, $mod, $perm]);
        }
      }
    }
    step('Semilla: permisos granulares por módulo', true, count($modules).' módulos × '.count($permsByRole).' roles');
  } else {
    step('Semilla: permisos granulares', true, 'Ya existen '.$existingRmp.' registros');
  }
} catch (Throwable $e) {
  step('Semilla: permisos granulares', false, $e->getMessage());
}

// 3.3 Índices compuestos para rendimiento
$compositeIndexes = [
  ['combustible', 'idx_combustible_fecha', '(fecha)'],
  ['combustible', 'idx_combustible_vehiculo_km', '(vehiculo_id, km)'],
  ['mantenimientos', 'idx_mantenimientos_fecha', '(fecha)'],
  ['mantenimientos', 'idx_mantenimientos_vehiculo_estado', '(vehiculo_id, estado)'],
  ['incidentes', 'idx_incidentes_fecha', '(fecha)'],
  ['incidentes', 'idx_incidentes_vehiculo_estado', '(vehiculo_id, estado)'],
  ['recordatorios', 'idx_recordatorios_fecha_estado', '(fecha_limite, estado)'],
  ['asignaciones', 'idx_asignaciones_created_at', '(created_at)'],
];
foreach ($compositeIndexes as [$tbl, $idx, $cols]) {
  try {
    if (!$existsIndex($tbl, $idx)) {
      $pdo->exec("ALTER TABLE `{$tbl}` ADD INDEX {$idx} {$cols}");
      step("Índice: {$tbl}.{$idx}", true);
    } else {
      step("Índice: {$tbl}.{$idx}", true, 'Ya existe');
    }
  } catch (Throwable $e) {
    step("Índice: {$tbl}.{$idx}", false, $e->getMessage());
  }
}

// 3.4 Módulo 14: Incidentes avanzados con seguros (migración)
$incSeguroCols = [
  ['aseguradora', "VARCHAR(150) NULL AFTER costo_est"],
  ['poliza_numero', "VARCHAR(80) NULL AFTER aseguradora"],
  ['tiene_reclamo', "TINYINT(1) NOT NULL DEFAULT 0 AFTER poliza_numero"],
  ['estado_reclamo', "ENUM('N/A','En proceso','Aprobado','Rechazado','Pagado') NOT NULL DEFAULT 'N/A' AFTER tiene_reclamo"],
  ['monto_reclamo', "DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER estado_reclamo"],
  ['fecha_reclamo', "DATE NULL AFTER monto_reclamo"],
  ['referencia_reclamo', "VARCHAR(100) NULL AFTER fecha_reclamo"],
  ['notas_seguro', "TEXT NULL AFTER referencia_reclamo"],
];
foreach ($incSeguroCols as [$col, $def]) {
  try {
    if (!$existsColumn('incidentes', $col)) {
      $pdo->exec("ALTER TABLE incidentes ADD COLUMN {$col} {$def}");
      step("Incidentes seguros: {$col}", true);
    } else {
      step("Incidentes seguros: {$col}", true, 'Ya existe');
    }
  } catch (Throwable $e) {
    step("Incidentes seguros: {$col}", false, $e->getMessage());
  }
}

// 3.5 Módulo 14: Tabla sucursales + columna sucursal_id
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS sucursales (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(150) NOT NULL,
    direccion  VARCHAR(255) NULL,
    ciudad     VARCHAR(100) NULL,
    telefono   VARCHAR(30)  NULL,
    responsable VARCHAR(150) NULL,
    activo     TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: sucursales', true);
} catch (Throwable $e) {
  step('Tabla: sucursales', false, $e->getMessage());
}

$sucursalTables = ['vehiculos', 'operadores', 'usuarios'];
foreach ($sucursalTables as $tbl) {
  try {
    if (!$existsColumn($tbl, 'sucursal_id')) {
      $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN sucursal_id INT NULL");
      step("Multi-sucursal: {$tbl}.sucursal_id", true);
    } else {
      step("Multi-sucursal: {$tbl}.sucursal_id", true, 'Ya existe');
    }
  } catch (Throwable $e) {
    step("Multi-sucursal: {$tbl}.sucursal_id", false, $e->getMessage());
  }
}

// Seed sucursal por defecto
try {
  $chk = $pdo->query("SELECT COUNT(*) FROM sucursales")->fetchColumn();
  if ((int)$chk === 0) {
    $pdo->exec("INSERT INTO sucursales (nombre, ciudad) VALUES ('Matriz','Ciudad Principal')");
    step('Semilla: sucursal Matriz', true);
  } else {
    step('Semilla: sucursal Matriz', true, 'Ya existen sucursales');
  }
} catch (Throwable $e) {
  step('Semilla: sucursal Matriz', false, $e->getMessage());
}

// 3.6 Módulo 14: Tabla notificaciones
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS notificaciones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT          NULL,
    tipo        VARCHAR(60)  NOT NULL DEFAULT 'info',
    titulo      VARCHAR(200) NOT NULL,
    mensaje     TEXT         NOT NULL,
    entidad     VARCHAR(60)  NULL,
    entidad_id  INT          NULL,
    leida       TINYINT(1)   NOT NULL DEFAULT 0,
    enviada_email TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_usuario (usuario_id, leida),
    INDEX idx_notif_created (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: notificaciones', true);
} catch (Throwable $e) {
  step('Tabla: notificaciones', false, $e->getMessage());
}

// 3.7 Checklist de vehículo
$checklistCols = [
  'tiene_gata'          => "TINYINT(1) NOT NULL DEFAULT 0",
  'tiene_herramientas'  => "TINYINT(1) NOT NULL DEFAULT 0",
  'tiene_llanta_repuesto' => "TINYINT(1) NOT NULL DEFAULT 0",
  'tiene_bac_flota'     => "TINYINT(1) NOT NULL DEFAULT 0",
  'revision_ok'         => "TINYINT(1) NOT NULL DEFAULT 0",
  'detalles_checklist'  => "TEXT NULL",
];
foreach ($checklistCols as $col => $def) {
  try {
    if (!$existsColumn('vehiculos', $col)) {
      $pdo->exec("ALTER TABLE vehiculos ADD COLUMN {$col} {$def}");
      step("Checklist vehículo: {$col}", true);
    } else {
      step("Checklist vehículo: {$col}", true, 'Ya existe');
    }
  } catch (Throwable $e) {
    step("Checklist vehículo: {$col}", false, $e->getMessage());
  }
}

// 3.8 Checklist y firmas en asignaciones
$asgExtraCols = [
  'checklist_gata'         => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_herramientas' => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_llanta'       => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_bac'          => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_revision'     => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_luces'        => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_liquidos'     => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_motor'        => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_parabrisas'   => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_documentacion'=> "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_frenos'       => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_espejos'      => "TINYINT(1) NOT NULL DEFAULT 0",
  'checklist_detalles'     => "TEXT NULL",
  'end_checklist_gata'         => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_herramientas' => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_llanta'       => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_bac'          => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_revision'     => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_luces'        => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_liquidos'     => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_motor'        => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_parabrisas'   => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_documentacion'=> "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_frenos'       => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_espejos'      => "TINYINT(1) NOT NULL DEFAULT 0",
  'end_checklist_detalles'     => "TEXT NULL",
  'firma_tipo'            => "ENUM('digital','fisica','ninguna') NOT NULL DEFAULT 'ninguna'",
  'firma_data'            => "LONGTEXT NULL",
  'firma_token'           => "VARCHAR(128) NULL",
  'firma_fecha'           => "DATETIME NULL",
  'firma_ip'              => "VARCHAR(45) NULL",
];
foreach ($asgExtraCols as $col => $def) {
  try {
    if (!$existsColumn('asignaciones', $col)) {
      $pdo->exec("ALTER TABLE asignaciones ADD COLUMN {$col} {$def}");
      step("Asignación extra: {$col}", true);
    } else {
      step("Asignación extra: {$col}", true, 'Ya existe');
    }
  } catch (Throwable $e) {
    step("Asignación extra: {$col}", false, $e->getMessage());
  }
}

// 3.9 Etiquetas de vehículos
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS vehiculo_etiquetas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    vehiculo_id INT NOT NULL,
    etiqueta    VARCHAR(60) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_veh_etiq (vehiculo_id, etiqueta),
    INDEX idx_etiqueta (etiqueta),
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: vehiculo_etiquetas', true);
} catch (Throwable $e) {
  step('Tabla: vehiculo_etiquetas', false, $e->getMessage());
}

// 3.10 Base para telemetría futura
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS telemetria_logs (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    vehiculo_id INT NOT NULL,
    tipo        VARCHAR(50) NOT NULL COMMENT 'gps, velocidad, rpm, temperatura, combustible_nivel, etc.',
    valor       VARCHAR(255) NOT NULL,
    unidad      VARCHAR(20) NULL,
    latitud     DECIMAL(10,7) NULL,
    longitud    DECIMAL(10,7) NULL,
    fuente      VARCHAR(50) NOT NULL DEFAULT 'manual' COMMENT 'manual, obd2, gps_tracker, api_externa',
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_telem_veh (vehiculo_id, tipo),
    INDEX idx_telem_fecha (recorded_at),
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: telemetria_logs (base telemetría)', true);
} catch (Throwable $e) {
  step('Tabla: telemetria_logs', false, $e->getMessage());
}

// 3.11 Columna aseguradora/poliza en vehiculos (para costo/km)
$vehExtraCols = [
  'costo_adquisicion' => "DECIMAL(12,2) NULL COMMENT 'Precio de compra'",
  'aseguradora'       => "VARCHAR(120) NULL",
  'poliza_numero'     => "VARCHAR(80) NULL",
];
foreach ($vehExtraCols as $col => $def) {
  try {
    if (!$existsColumn('vehiculos', $col)) {
      $pdo->exec("ALTER TABLE vehiculos ADD COLUMN {$col} {$def}");
      step("Vehículo extra: {$col}", true);
    } else {
      step("Vehículo extra: {$col}", true, 'Ya existe');
    }
  } catch (Throwable $e) {
    step("Vehículo extra: {$col}", false, $e->getMessage());
  }
}

// ═══════════════════════════════════════════════════
// 3.12 Plantillas de checklist dinámicas (Objetivo 3)
// ═══════════════════════════════════════════════════
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS checklist_plantillas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(120) NOT NULL,
    tipo        ENUM('entrega','retorno','ambos') NOT NULL DEFAULT 'ambos',
    activo      TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_plantilla_nombre (nombre)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: checklist_plantillas', true);
} catch (Throwable $e) {
  step('Tabla: checklist_plantillas', false, $e->getMessage());
}

try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS checklist_plantilla_items (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    plantilla_id  INT NOT NULL,
    label         VARCHAR(120) NOT NULL,
    orden         INT NOT NULL DEFAULT 0,
    requerido     TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_plantilla_items (plantilla_id, orden),
    FOREIGN KEY (plantilla_id) REFERENCES checklist_plantillas(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: checklist_plantilla_items', true);
} catch (Throwable $e) {
  step('Tabla: checklist_plantilla_items', false, $e->getMessage());
}

try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS asignacion_checklist_respuestas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    asignacion_id   BIGINT NOT NULL,
    item_label      VARCHAR(120) NOT NULL,
    momento         ENUM('entrega','retorno') NOT NULL,
    checked         TINYINT(1) NOT NULL DEFAULT 0,
    observacion     TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_acr_asig (asignacion_id, momento),
    FOREIGN KEY (asignacion_id) REFERENCES asignaciones(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: asignacion_checklist_respuestas', true);
} catch (Throwable $e) {
  step('Tabla: asignacion_checklist_respuestas', false, $e->getMessage());
}

// Insertar plantilla por defecto si la tabla está vacía
try {
  $cnt = (int)$pdo->query("SELECT COUNT(*) FROM checklist_plantillas")->fetchColumn();
  if ($cnt === 0) {
    $pdo->exec("INSERT INTO checklist_plantillas (nombre, tipo) VALUES ('Estándar Flota', 'ambos')");
    $defPlantilla = (int)$pdo->lastInsertId();
    $defItems = ['Gata','Herramientas','Llanta de repuesto','BAC Flota','Revisión general OK','Luces funcionando','Frenos OK','Documentos vigentes'];
    $orden = 0;
    foreach ($defItems as $label) {
      $pdo->prepare("INSERT INTO checklist_plantilla_items (plantilla_id, label, orden) VALUES (?,?,?)")
          ->execute([$defPlantilla, $label, $orden++]);
    }
    step('Plantilla checklist por defecto insertada', true, count($defItems).' items');
  } else {
    step('Plantilla checklist por defecto', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Plantilla checklist por defecto', false, $e->getMessage());
}

// ═══════════════════════════════════════════════════
// 3.13 Sistema de aprobación multinivel para OTs (Objetivo 3)
// ═══════════════════════════════════════════════════
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS mantenimiento_aprobaciones (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    mantenimiento_id  INT NOT NULL,
    nivel             INT NOT NULL DEFAULT 1 COMMENT '1=soporte, 2=coordinador',
    aprobador_id      INT NULL,
    estado            ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
    comentario        TEXT NULL,
    fecha             DATETIME NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_aprobaciones_mant (mantenimiento_id, nivel),
    FOREIGN KEY (mantenimiento_id) REFERENCES mantenimientos(id) ON DELETE CASCADE,
    FOREIGN KEY (aprobador_id) REFERENCES usuarios(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: mantenimiento_aprobaciones', true);
} catch (Throwable $e) {
  step('Tabla: mantenimiento_aprobaciones', false, $e->getMessage());
}

// Columna requiere_aprobacion en mantenimientos
try {
  if (!$existsColumn('mantenimientos', 'requiere_aprobacion')) {
    $pdo->exec("ALTER TABLE mantenimientos ADD COLUMN requiere_aprobacion TINYINT(1) NOT NULL DEFAULT 0");
    step('Mant extra: requiere_aprobacion', true);
  } else {
    step('Mant extra: requiere_aprobacion', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Mant extra: requiere_aprobacion', false, $e->getMessage());
}

try {
  if (!$existsColumn('mantenimientos', 'aprobacion_estado')) {
    $pdo->exec("ALTER TABLE mantenimientos ADD COLUMN aprobacion_estado ENUM('no_requerida','pendiente','aprobada','rechazada') NOT NULL DEFAULT 'no_requerida'");
    step('Mant extra: aprobacion_estado', true);
  } else {
    step('Mant extra: aprobacion_estado', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Mant extra: aprobacion_estado', false, $e->getMessage());
}

// Settings para umbrales de aprobación
try {
  $stCheck = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE key_name=?");
  $stCheck->execute(['maintenance.umbral_aprobacion_n1']);
  if ((int)$stCheck->fetchColumn() === 0) {
    $pdo->prepare("INSERT INTO system_settings (key_name, value_num, description) VALUES (?,?,?)")
        ->execute(['maintenance.umbral_aprobacion_n1', 5000, 'Costo mínimo para requerir aprobación nivel 1 (soporte)']);
    step('Setting: umbral_aprobacion_n1', true, '$5,000');
  } else {
    step('Setting: umbral_aprobacion_n1', true, 'Ya existe');
  }
  $stCheck->execute(['maintenance.umbral_aprobacion_n2']);
  if ((int)$stCheck->fetchColumn() === 0) {
    $pdo->prepare("INSERT INTO system_settings (key_name, value_num, description) VALUES (?,?,?)")
        ->execute(['maintenance.umbral_aprobacion_n2', 15000, 'Costo mínimo para requerir aprobación nivel 2 (coordinador)']);
    step('Setting: umbral_aprobacion_n2', true, '$15,000');
  } else {
    step('Setting: umbral_aprobacion_n2', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Settings aprobación', false, $e->getMessage());
}

// ═══════════════════════════════════════════════════
// 3.14 Control de repuestos en partidas OT (Objetivo 3)
// ═══════════════════════════════════════════════════
try {
  if (!$existsColumn('mantenimiento_items', 'component_id')) {
    $pdo->exec("ALTER TABLE mantenimiento_items ADD COLUMN component_id INT NULL COMMENT 'Referencia a componente del inventario'");
    step('Items extra: component_id', true);
  } else {
    step('Items extra: component_id', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Items extra: component_id', false, $e->getMessage());
}

// Columna plantilla_id en asignaciones
try {
  if (!$existsColumn('asignaciones', 'plantilla_id')) {
    $pdo->exec("ALTER TABLE asignaciones ADD COLUMN plantilla_id INT NULL COMMENT 'Plantilla de checklist usada'");
    step('Asignación extra: plantilla_id', true);
  } else {
    step('Asignación extra: plantilla_id', true, 'Ya existe');
  }
} catch (Throwable $e) {
  step('Asignación extra: plantilla_id', false, $e->getMessage());
}

// 3.15 Seguimiento de incidentes (Objetivo 4)
// ═══════════════════════════════════════════════════
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS incidente_seguimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incidente_id INT NOT NULL,
    usuario_id INT NULL,
    accion VARCHAR(60) NOT NULL COMMENT 'estado_change, nota, adjunto',
    estado_anterior VARCHAR(30) NULL,
    estado_nuevo VARCHAR(30) NULL,
    comentario TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incidente_id) REFERENCES incidentes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: incidente_seguimientos', true);
} catch (Throwable $e) {
  step('Tabla: incidente_seguimientos', false, $e->getMessage());
}

try {
  if (!$existsColumn('incidentes', 'resolved_at')) {
    $pdo->exec("ALTER TABLE incidentes ADD COLUMN resolved_at DATETIME NULL COMMENT 'Fecha de resolución'");
    step('Incidentes extra: resolved_at', true);
  } else { step('Incidentes extra: resolved_at', true, 'Ya existe'); }
} catch (Throwable $e) { step('Incidentes extra: resolved_at', false, $e->getMessage()); }

try {
  if (!$existsColumn('incidentes', 'resolved_by')) {
    $pdo->exec("ALTER TABLE incidentes ADD COLUMN resolved_by INT NULL COMMENT 'Usuario que cerró'");
    step('Incidentes extra: resolved_by', true);
  } else { step('Incidentes extra: resolved_by', true, 'Ya existe'); }
} catch (Throwable $e) { step('Incidentes extra: resolved_by', false, $e->getMessage()); }

try {
  if (!$existsColumn('incidentes', 'prioridad')) {
    $pdo->exec("ALTER TABLE incidentes ADD COLUMN prioridad ENUM('Baja','Normal','Alta','Urgente') NOT NULL DEFAULT 'Normal' COMMENT 'Prioridad de atención'");
    step('Incidentes extra: prioridad', true);
  } else { step('Incidentes extra: prioridad', true, 'Ya existe'); }
} catch (Throwable $e) { step('Incidentes extra: prioridad', false, $e->getMessage()); }

// 3.16 Objetivo 5 — Operadores + Componentes + Proveedores + Sucursales
// ═══════════════════════════════════════════════════════════════════════

// -- Capacitaciones de operadores
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS operador_capacitaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operador_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    tipo ENUM('Interna','Externa','Online') NOT NULL DEFAULT 'Interna',
    horas DECIMAL(6,1) NOT NULL DEFAULT 0,
    fecha DATE NOT NULL,
    certificado_url VARCHAR(500) NULL,
    vencimiento DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_opcap_operador (operador_id),
    INDEX idx_opcap_fecha (fecha),
    FOREIGN KEY (operador_id) REFERENCES operadores(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: operador_capacitaciones', true);
} catch (Throwable $e) { step('Tabla: operador_capacitaciones', false, $e->getMessage()); }

// -- Infracciones de operadores
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS operador_infracciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operador_id INT NOT NULL,
    fecha DATE NOT NULL,
    tipo ENUM('Multa','Accidente','Violación','Otro') NOT NULL DEFAULT 'Multa',
    descripcion TEXT NULL,
    monto DECIMAL(12,2) NOT NULL DEFAULT 0,
    estado ENUM('Pendiente','Pagada','Contestada') NOT NULL DEFAULT 'Pendiente',
    referencia VARCHAR(100) NULL COMMENT 'Folio o número de boleta',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_opinf_operador (operador_id),
    INDEX idx_opinf_fecha (fecha),
    FOREIGN KEY (operador_id) REFERENCES operadores(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: operador_infracciones', true);
} catch (Throwable $e) { step('Tabla: operador_infracciones', false, $e->getMessage()); }

// -- Movimientos de componentes / inventario
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS componente_movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    component_id INT NOT NULL,
    vehiculo_id INT NULL COMMENT 'Vehículo involucrado',
    tipo ENUM('Entrada','Salida','Transferencia','Ajuste') NOT NULL DEFAULT 'Entrada',
    cantidad INT NOT NULL DEFAULT 1,
    referencia VARCHAR(150) NULL COMMENT 'OT, factura, etc.',
    notas TEXT NULL,
    usuario_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cmov_component (component_id),
    INDEX idx_cmov_vehiculo (vehiculo_id),
    INDEX idx_cmov_fecha (created_at),
    FOREIGN KEY (component_id) REFERENCES components(id) ON DELETE CASCADE,
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: componente_movimientos', true);
} catch (Throwable $e) { step('Tabla: componente_movimientos', false, $e->getMessage()); }

// -- Evaluaciones de proveedores
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS proveedor_evaluaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT NOT NULL,
    periodo VARCHAR(20) NOT NULL COMMENT 'Ej: 2026-Q1',
    calidad TINYINT NOT NULL DEFAULT 3 COMMENT '1-5',
    puntualidad TINYINT NOT NULL DEFAULT 3,
    precio TINYINT NOT NULL DEFAULT 3,
    servicio TINYINT NOT NULL DEFAULT 3,
    promedio DECIMAL(3,2) GENERATED ALWAYS AS ((calidad+puntualidad+precio+servicio)/4) STORED,
    comentario TEXT NULL,
    usuario_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_peval_proveedor (proveedor_id),
    INDEX idx_peval_periodo (periodo),
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: proveedor_evaluaciones', true);
} catch (Throwable $e) { step('Tabla: proveedor_evaluaciones', false, $e->getMessage()); }

// -- Contratos de proveedores
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS proveedor_contratos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    numero_contrato VARCHAR(80) NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL,
    monto DECIMAL(14,2) NOT NULL DEFAULT 0,
    tipo ENUM('Servicio','Suministro','Mantenimiento','Otro') NOT NULL DEFAULT 'Servicio',
    estado ENUM('Vigente','Vencido','Cancelado') NOT NULL DEFAULT 'Vigente',
    documento_url VARCHAR(500) NULL,
    notas TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pcon_proveedor (proveedor_id),
    INDEX idx_pcon_estado (estado),
    INDEX idx_pcon_fin (fecha_fin),
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: proveedor_contratos', true);
} catch (Throwable $e) { step('Tabla: proveedor_contratos', false, $e->getMessage()); }

// -- Columna stock en catálogo de componentes (para inventario con movimientos)
try {
  if (!$existsColumn('components', 'stock')) {
    $pdo->exec("ALTER TABLE components ADD COLUMN stock INT NOT NULL DEFAULT 0 COMMENT 'Stock consolidado'");
    step('Components: stock', true);
  } else { step('Components: stock', true, 'Ya existe'); }
} catch (Throwable $e) { step('Components: stock', false, $e->getMessage()); }

// -- Columna stock_minimo en catálogo para alertas
try {
  if (!$existsColumn('components', 'stock_minimo')) {
    $pdo->exec("ALTER TABLE components ADD COLUMN stock_minimo INT NOT NULL DEFAULT 0 COMMENT 'Stock mínimo para alerta'");
    step('Components: stock_minimo', true);
  } else { step('Components: stock_minimo', true, 'Ya existe'); }
} catch (Throwable $e) { step('Components: stock_minimo', false, $e->getMessage()); }

// 3.17 Centro de Alertas Unificado (Objetivo 6)
// ═══════════════════════════════════════════════════
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('vencimiento','mantenimiento','incidente','combustible','recordatorio','componente','licencia','contrato','seguro','inventario') NOT NULL,
    prioridad ENUM('Baja','Normal','Alta','Urgente') NOT NULL DEFAULT 'Normal',
    titulo VARCHAR(250) NOT NULL,
    mensaje TEXT NULL,
    estado ENUM('Activa','Atendida','Descartada','Resuelta') NOT NULL DEFAULT 'Activa',
    entidad VARCHAR(60) NULL COMMENT 'Tabla fuente: vehiculos, operadores, etc.',
    entidad_id INT NULL COMMENT 'ID del registro fuente',
    vehiculo_id INT NULL,
    responsable_id INT NULL COMMENT 'Usuario asignado',
    fecha_referencia DATE NULL COMMENT 'Fecha de vencimiento, límite, etc.',
    resuelto_at DATETIME NULL,
    resuelto_por INT NULL,
    notas TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_alerta_tipo (tipo),
    INDEX idx_alerta_estado (estado),
    INDEX idx_alerta_prioridad (prioridad),
    INDEX idx_alerta_vehiculo (vehiculo_id),
    INDEX idx_alerta_responsable (responsable_id),
    INDEX idx_alerta_fecha_ref (fecha_referencia),
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE SET NULL,
    FOREIGN KEY (responsable_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (resuelto_por) REFERENCES usuarios(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: alertas', true);
} catch (Throwable $e) { step('Tabla: alertas', false, $e->getMessage()); }

// -- Historial de alertas (acciones)
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS alerta_historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alerta_id INT NOT NULL,
    usuario_id INT NULL,
    accion VARCHAR(60) NOT NULL COMMENT 'creada, asignada, atendida, descartada, resuelta, nota',
    comentario TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ahist_alerta (alerta_id),
    FOREIGN KEY (alerta_id) REFERENCES alertas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: alerta_historial', true);
} catch (Throwable $e) { step('Tabla: alerta_historial', false, $e->getMessage()); }

// ─────────────────────────────────────────────────────────
// 3.18 Seguridad Avanzada (Objetivo 8): rate_limits + 2FA
// ─────────────────────────────────────────────────────────
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
    rate_key VARCHAR(200) NOT NULL PRIMARY KEY,
    hits INT UNSIGNED NOT NULL DEFAULT 1,
    window_start DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rl_window (window_start)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  step('Tabla: rate_limits', true);
} catch (Throwable $e) { step('Tabla: rate_limits', false, $e->getMessage()); }

// -- 2FA columns on usuarios
try {
  $cols = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'totp_secret'")->fetch();
  if (!$cols) {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN totp_secret VARCHAR(32) NULL DEFAULT NULL");
    step('Columna: usuarios.totp_secret', true);
  } else { step('Columna: usuarios.totp_secret', true, 'Ya existe'); }
} catch (Throwable $e) { step('Columna: usuarios.totp_secret', false, $e->getMessage()); }

try {
  $cols = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'totp_enabled'")->fetch();
  if (!$cols) {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0");
    step('Columna: usuarios.totp_enabled', true);
  } else { step('Columna: usuarios.totp_enabled', true, 'Ya existe'); }
} catch (Throwable $e) { step('Columna: usuarios.totp_enabled', false, $e->getMessage()); }

// ─────────────────────────────────────────────────────────
// 3.19 Rendimiento (Objetivo 9): Índices de optimización
// ─────────────────────────────────────────────────────────
$perf_indexes = [
    // Dashboard: combustible por vehículo+fecha (KPIs, charts mensuales)
    ['combustible', 'idx_comb_vehiculo_fecha', '(vehiculo_id, fecha)'],
    // Dashboard: mantenimiento por proveedor (reporte talleres)
    ['mantenimientos', 'idx_mant_proveedor', '(proveedor_id)'],
    // Alertas scan: búsqueda rápida de duplicados
    ['alertas', 'idx_alerta_entidad_compuesta', '(tipo, entidad, entidad_id, estado)'],
    // Asignaciones: JOIN temporal eficiencia operadores
    ['asignaciones', 'idx_asig_vehiculo_fechas', '(vehiculo_id, start_at, end_at)'],
    // Vehículos: filtro deleted_at + sucursal (dashboard, listados)
    ['vehiculos', 'idx_veh_deleted_sucursal', '(deleted_at, sucursal_id)'],
    // Vehículos: estado para filtros frecuentes
    ['vehiculos', 'idx_veh_estado', '(estado)'],
    // Combustible: km por vehículo para eficiencia
    ['combustible', 'idx_comb_vehiculo_km_rec', '(vehiculo_id, km)'],
];
foreach ($perf_indexes as [$tbl, $idx, $cols]) {
    try {
        $exists = $pdo->query("SHOW INDEX FROM `{$tbl}` WHERE Key_name = '{$idx}'")->fetch();
        if (!$exists) {
            $pdo->exec("ALTER TABLE `{$tbl}` ADD INDEX {$idx} {$cols}");
            step("Índice: {$tbl}.{$idx}", true);
        } else {
            step("Índice: {$tbl}.{$idx}", true, 'Ya existe');
        }
    } catch (Throwable $e) { step("Índice: {$tbl}.{$idx}", false, $e->getMessage()); }
}

// 4. Usuarios iniciales del sistema
$usuarios_iniciales = [
    [
        'nombre' => 'Coordinador IT',
        'email'  => 'coordinador@flotacontrol.local',
        'pass'   => 'CoordIT2024x',
        'rol'    => 'coordinador_it',
    ],
    [
        'nombre' => 'Soporte Sistema',
        'email'  => 'soporte@flotacontrol.local',
        'pass'   => 'Soporte2024x',
        'rol'    => 'soporte',
    ],
    [
        'nombre' => 'Monitor Flota',
        'email'  => 'monitoreo@flotacontrol.local',
        'pass'   => 'Monitor2024x',
        'rol'    => 'monitoreo',
    ],
    [
        'nombre' => 'Dev Test',
        'email'  => 'dev@flotacontrol.local',
        'pass'   => 'DevTest2024x',
        'rol'    => 'coordinador_it',
    ],
];
foreach ($usuarios_iniciales as $u) {
    try {
        $exists = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
        $exists->execute([$u['email']]);
        if (!$exists->fetchColumn()) {
            $hash = password_hash($u['pass'], PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO usuarios (nombre,email,password,rol) VALUES (?,?,?,?)")
                ->execute([$u['nombre'], $u['email'], $hash, $u['rol']]);
            step("Usuario '{$u['nombre']}' ({$u['rol']}) creado", true);
        } else {
            step("Usuario '{$u['email']}' ya existe", true, "No se sobreescribió");
        }
    } catch (PDOException $e) {
        step("Crear usuario '{$u['nombre']}'", false, $e->getMessage());
    }
}

if ($ok): ?>
<div class="creds">
  <strong>✅ Instalación completada</strong><br><br>
  <strong style="color:var(--accent,#e8ff47)">Usuarios creados:</strong><br><br>
  🔑 <strong>Coordinador IT</strong> (admin total)<br>
  &nbsp;&nbsp;&nbsp;📧 coordinador@flotacontrol.local &nbsp;|&nbsp; 🔒 CoordIT2024x<br><br>
  🛠️ <strong>Soporte</strong> (crear/editar)<br>
  &nbsp;&nbsp;&nbsp;📧 soporte@flotacontrol.local &nbsp;&nbsp;&nbsp;&nbsp;|&nbsp; 🔒 Soporte2024x<br><br>
  👁️ <strong>Monitoreo</strong> (solo lectura)<br>
  &nbsp;&nbsp;&nbsp;📧 monitoreo@flotacontrol.local &nbsp;|&nbsp; 🔒 Monitor2024x<br><br>
  🧪 <strong>Dev Test</strong> (coordinador_it)<br>
  &nbsp;&nbsp;&nbsp;📧 dev@flotacontrol.local &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;|&nbsp; 🔒 DevTest2024x<br><br>
  <small style="color:#ff4757">⚠️ Cambia las contraseñas al ingresar. Elimina <code>install.php</code> del servidor.</small>
</div>
<a href="/index.php" class="btn">Ir al sistema →</a>
<?php else: ?>
<div style="margin-top:20px;color:#ff4757;font-size:13px">❌ La instalación no se completó correctamente. Revisa los errores anteriores.</div>
<?php endif; ?>

</div>
</body>
</html>
