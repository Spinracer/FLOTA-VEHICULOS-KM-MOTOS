# 🚀 INSTRUCCIONES INMEDIATAS - FIX DE ERRORES 500

## El Problema
```
❌ Error 500 en: api/importacion_vehiculos.php?action=import  
⚠️  Warning: feature_collector.js:23
ℹ️  404: favicon.ico
```

## La Causa
La tabla `import_runs` **NO EXISTE** en tu BD. Esta tabla es CRÍTICA para:
- Guardar historial de importaciones
- Rastrear errores
- Auditar cambios

## La Solución (Elige UNA opción)

### ✅ OPCIÓN 1: Vía Docker (MÁS FÁCIL)
```bash
# Si usas Docker Compose:
docker exec flotacontrol-app php /var/www/html/tests/migrate_importacion_tables.php
```

### ✅ OPCIÓN 2: Vía SSH (Servidor Ubuntu)
```bash
# Conectate a tu servidor
ssh usuario@tu-servidor

# Navega a la carpeta del proyecto
cd /path/to/flota-vehiculos

# Ejecuta la migración
php tests/migrate_importacion_tables.php
```

### ✅ OPCIÓN 3: Web Installer (Más seguro si instalas desde 0)
```bash
# 1. Borra el archivo .installed.lock si existe
rm .installed.lock

# 2. Accede en navegador
http://tu-servidor/install.php

# 3. Sigue los pasos del instalador (creará tabla automáticamente)
```

### ✅ OPCIÓN 4: Script Helper (Recomendado)
```bash
# Si tienes acceso SSH:
chmod +x /path/to/apply-import-migration.sh
./apply-import-migration.sh
```

---

## ✨ Después de Aplicar

### 1️⃣ Verificar que funcionó
```bash
# Via consola del servidor:
mysql -u usuario -p nombre_base_datos -e "SELECT * FROM import_runs LIMIT 1;"

# Si devuelve "Empty set" = ✅ Tabla existe!
# Si error "Unknown table" = ❌ Vuelve a intentar
```

### 2️⃣ Probar en navegador
1. Ir a: `http://tu-servidor/importacion_vehiculos.php`
2. Subir un archivo CSV/XLSX
3. Mapear columnas
4. Hacer click en **IMPORTAR**
5. Si funciona = ✅ ¡LISTO!

### 3️⃣ Actualizar el código
```bash
# Asegúrate de tener la versión más reciente
git pull origin main

# Verifica que tienes los nuevos archivos:
ls -la apply-import-migration.sh
ls -la tests/migrate_importacion_tables.php
```

---

## 📊 Qué se crea

La tabla `import_runs` tiene esta estructura:
```sql
CREATE TABLE import_runs (
  id                 INT PRIMARY KEY AUTO_INCREMENT
  tipo_importacion   VARCHAR(50)     -- 'vehiculos', 'operadores'
  nombre_archivo     VARCHAR(255)    -- nombre del CSV/XLSX
  usuario_id         INT             -- quién importó
  total_filas        INT             -- filas procesadas
  creados            INT             -- registros nuevos
  actualizados       INT             -- registros modificados
  errores            INT             -- cantidad de errores
  detalle_errores    JSON            -- detalles de cada error
  estado             ENUM            -- procesando/completado/fallido
  created_at         DATETIME        -- cuándo comenzó
  completed_at       DATETIME        -- cuándo terminó
);
```

---

## 🆘 Si Aún Falla

### Error: "Access denied"
```bash
# Verifica credenciales de BD en .env
cat .env | grep DB_
```

### Error: "Unknown table"
```bash
# Verifica que estés usando la BD correcta
mysql -u usuario -p nombre_base_datos -e "SHOW TABLES;"
# Deberías ver: import_runs, usuarios, vehiculos, etc.
```

### Error: "Syntax error"
```bash
# Habilita debug temporalmente
export APP_DEBUG=true

# Ejecuta migración nuevamente
php tests/migrate_importacion_tables.php

# Revisa errores detallados
# Luego desactiva debug
unset APP_DEBUG
```

---

## 📱 Verificación Rápida POST-FIX

```bash
# 1. Tabla existe
mysql -u usuario -p BD -e "DESCRIBE import_runs;" 

# 2. Permisos OK
mysql -u usuario -p BD -e "SELECT GRANT(usuario);"

# 3. Puedes insertar
mysql -u usuario -p BD -e "INSERT INTO import_runs VALUES (NULL, 'vehiculos', 'test.csv', 1, 0, 0, 0, 0, NULL, 'completado', NOW(), NOW());"

# 4. Limpiar test
mysql -u usuario -p BD -e "DELETE FROM import_runs WHERE estado='completado' AND created_at=NOW();"
```

---

## 📝 Versión Actualizada

✅ Todo está en: `main` branch  
✅ Tag: `v1.0-final-april-2026`  
✅ Cambios: commit `c79f11a`

```bash
git log --oneline -3
# c79f11a Fix: Agregar tabla import_runs y documentación de solución
# 804a788 Final: Database migration + combustible fields + docs consolidation
```

---

**¡LISTO! Con estos pasos tu importación debe funcionar. Reporta si aún hay problemas.**
