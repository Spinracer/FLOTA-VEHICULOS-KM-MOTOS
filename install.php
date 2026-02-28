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
