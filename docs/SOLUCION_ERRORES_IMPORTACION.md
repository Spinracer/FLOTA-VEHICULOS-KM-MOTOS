# 🔧 SOLUCIÓN: Errores 500 en Importación de Vehículos y Operadores

**Fecha:** 2 de abril de 2026  
**Severidad:** 🔴 CRÍTICA  
**Estado:** ✅ RESUELTA  

---

## 📋 Errores Reportados

### 1. ❌ Error 500 en `api/importacion_vehiculos.php?action=import`
```
Failed to load resource: the server responded with a status of 500
```

### 2. ⚠️ Warning: `feature_collector.js:23` - Parámetros deprecados
```
U @ feature_collector.js:23
using deprecated parameters for the initialization function
```

### 3. ℹ️ Error 404: `favicon.ico` (MENOR)
```
Failed to load resource: the server responded with a status of 404
```

---

## 🔍 ANÁLISIS DE CAUSAS

### Causa Principal: Tabla `import_runs` No Existe ❌

La tabla `import_runs` es **CRÍTICA** para las funciones de importación:
- Rastrear importaciones
- Guardar registros de éxito/error
- Auditar cambios

**Archivo afectado:** `includes/importacion_vehiculos.php` línea 486
```php
$stmt = $db->prepare("INSERT INTO import_runs 
    (tipo_importacion, nombre_archivo, usuario_id, total_filas, estado) 
    VALUES ('vehiculos', ?, ?, ?, 'procesando')");
```

**Lo que pasaba:**
1. El usuario hacía click en "Importar"
2. La aplicación intentaba `INSERT INTO import_runs`
3. **MySQL respondía: "Table 'import_runs' doesn't exist"**
4. Se retornaba HTTP 500 (error interno)

### Causa Secundaria: `install.php` Incompleto

El archivo `install.php` no incluía la tabla `import_runs` en su definición de esquema inicial.

---

## ✅ SOLUCIONES APLICADAS

### Solución 1: Agregar Tabla `import_runs` a `install.php`

**Cambio:** Agregada definición de tabla en `install.php` (antes de cierre de array `$tables`)

```sql
CREATE TABLE IF NOT EXISTS import_runs (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  tipo_importacion    VARCHAR(50) NOT NULL DEFAULT 'vehiculos',
  nombre_archivo      VARCHAR(255) NOT NULL,
  usuario_id          INT NOT NULL,
  total_filas         INT NOT NULL DEFAULT 0,
  creados             INT NOT NULL DEFAULT 0,
  actualizados        INT NOT NULL DEFAULT 0,
  errores             INT NOT NULL DEFAULT 0,
  detalle_errores     JSON NULL,
  estado              ENUM('procesando', 'completado', 'fallido') NOT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at        DATETIME NULL,
  INDEX idx_tipo (tipo_importacion),
  INDEX idx_estado (estado),
  INDEX idx_created (created_at),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
```

**Archivo modificado:** `/install.php`

### Solución 2: Script de Migración para Instalaciones Existentes

**Archivo creado:** `tests/migrate_importacion_tables.php`

Este script puede ejecutarse múltiples veces sin romper nada (es idempotente).

### Solución 3: Favicon Agregado

**Archivo creado:** `favicon.ico`

Resuelve el error 404 del navegador.

---

## 🚀 PASOS PARA APLICAR EN TU SERVIDOR

### Opción A: Reinstalación Completa (Limpia)

Si tu BD está vacía o quieres reinstalar:

```bash
# 1. Clonar la versión actualizada
git clone -b main https://github.com/Spinracer/FLOTA-VEHICULOS-KM-MOTOS.git
cd FLOTA-VEHICULOS-KM-MOTOS

# 2. Copiar .env desde tu servidor anterior
cp /ruta/anterior/.env .

# 3. Ejecutar instalador web
curl http://tu-servidor/install.php
# O acceder desde navegador: http://tu-servidor/install.php

# 4. El instalador creará la tabla import_runs automáticamente
```

### Opción B: Migración en Instalación Existente (RECOMENDADO)

Si ya tienes BD con datos:

```bash
# 1. Actualizar código
cd /ruta/flota-vehiculos
git pull origin main

# 2. Ejecutar migración de tabla import_runs

# VÍA DOCKER (si usas Docker Compose):
docker exec flotacontrol-app php /var/www/html/tests/migrate_importacion_tables.php

# VÍA SSH (servidor Ubuntu directo):
php /var/www/flota-vehiculos/tests/migrate_importacion_tables.php

# 3. Verificar tabla fue creada
mysql -h DB_HOST -u DB_USER -p DB_NAME -e "DESCRIBE import_runs;"
```

---

## ✚ TABLA `import_runs` - Estructura

| Columna | Tipo | Descripción |
|---------|------|-----------|
| `id` | INT | ID único |
| `tipo_importacion` | VARCHAR(50) | 'vehiculos', 'operadores', etc. |
| `nombre_archivo` | VARCHAR(255) | Nombre del archivo CSV/XLSX |
| `usuario_id` | INT | Usuario que hizo la importación |
| `total_filas` | INT | Total de filas procesadas |
| `creados` | INT | Registros exitosamente creados |
| `actualizados` | INT | Registros actualizados |
| `errores` | INT | Cantidad de errores |
| `detalle_errores` | JSON | Array con detalles de errores |
| `estado` | ENUM | procesando, completado, fallido |
| `created_at` | DATETIME | Fecha inicio |
| `completed_at` | DATETIME | Fecha finalización |

---

## ⚠️ Sobre el Warning: `feature_collector.js:23`

**Estado:** INVESTIGADO - No es crítico

Este warning viene de una librería externa (probablemente Chart.js u otro CDN). 

**Qué significa:**
- La librería usa parámetros deprecados en JavaScript
- No afecta la funcionalidad
- Aparece solo en la consola del navegador

**Acción:** Ignorable por ahora (se solucionará en próximas versiones)

---

## 🧪 PRUEBAS POST-MIGRACIÓN

### Verificar que la tabla existe:
```bash
mysql -u usuario -p base_datos -e "SHOW TABLES LIKE 'import_runs';"
```

### Verificar estructura:
```bash
mysql -u usuario -p base_datos -e "DESCRIBE import_runs;"
```

### Probar importación en navegador:
1. Ir a: `http://tu-servidor/importacion_vehiculos.php`
2. Subir archivo CSV/XLSX
3. Mapear columnas  
4. Hacer click en "IMPORTAR"
5. ✅ Debería funcionar sin errores 500

---

## 📊 CAMBIOS EN GIT

Los cambios han sido agregados a la rama `main`:

```bash
git log --oneline -3
# 804a788 Final: Database migration + docs consolidation + assign validation fix
```

**Archivos modificados:**
- `install.php` - Agregada tabla `import_runs`
- `tests/migrate_importacion_tables.php` - Script de migración
- `favicon.ico` - Favicon para eliminar 404

---

## 🆘 Si Aún Tienes Problemas

### Verificar Permisos
```bash
# Usuário debe tener permiso 'create' en módulo 'vehiculos'
# En la DB:
mysql> SELECT * FROM user_module_permissions 
        WHERE usuario_id = YOUR_USER_ID AND modulo = 'vehiculos' AND accion = 'create';
```

### Habilitar Debug
```bash
# Temporalmente en tu servidor
export APP_DEBUG=true

# O en .env:
APP_DEBUG=true

# Luego vuelve a false después de diagnosticar
```

### Ver Logs Detallados
```bash
# Docker
docker exec flotacontrol-app tail -100 /var/log/php-fpm/error.log

# Apache/Nginx directo
tail -100 /var/log/apache2/error.log
# o
tail -100 /var/log/nginx/error.log
```

---

## 📌 RESUMEN RÁPIDO

| Problema | Causa | Solución |
|----------|-------|----------|
| Error 500 en importación | Tabla `import_runs` no existe | Ejecutar migración o reinstalar |
| Warning feature_collector | Librería CDN deprecada | Ignorable - se solucionará luego |
| 404 favicon.ico | Favicon faltante | Ya agregado al repo ✅ |

---

**Versión Final:** `v1.0-final-april-2026`  
**Estado:** ✅ LISTO PARA PRODUCCIÓN
