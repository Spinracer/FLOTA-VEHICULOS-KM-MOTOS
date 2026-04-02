# Actualización Final — Abril 2026

## Resumen de cambios

Esta actualización consolida todas las mejoras para la rama `main` de producción, incluyendo:

### 🚗 Módulo de Vehículos
- ✅ Filtro por tipo de vehículo (Automóvil, Camioneta, Camión, Motocicleta, SUV, Furgoneta, Maquinaria, Otro)
- ✅ Acceso directo a importación desde la barra de herramientas
- ✅ Eliminación del módulo independiente `importacion_vehiculos.php` del menú lateral

### 👤 Módulo de Operadores
- ✅ Nuevo botón de importación masiva en el toolbar
- ✅ Módulo de importación integrado en `operadores.php`
- ✅ Soporte para estados: Activo, Inactivo, Suspendido
- ✅ Template de importación con ejemplo: `assets/plantilla_importacion_operadores.csv`

### 🚨 Sistema de Alertas
- ✅ Nueva alerta tipo `operador_inactivo`: se dispara cuando un vehículo tiene asignación activa y el operador está inactivo o suspendido
- ✅ Colores: badge-purple para operador inactivo
- ✅ Verificación automática en dashboard al actualizar

### 📋 Asignaciones
- ✅ **CRÍTICO**: Migración de base de datos aplicada
  - Se agregaron columnas: `start_combustible`, `end_combustible`
  - Migración ejecutada automáticamente en `tests/migrate_asignaciones_combustible.php`
- ✅ Integración del pase de salida en asignaciones
- ✅ Firma de operario (digital/física) en cierre de asignación

### 🔧 Correcciones
- ✅ Validación mejorada de estado de vehículos (case-insensitive + trim)
- ✅ Permisos granulares para operaciones de override
- ✅ Mensaje de error más específico si el vehículo no está "Activo"

---

## Migración de Base de Datos

### Tabla: `asignaciones`

Se agregaron dos columnas para registrar el nivel de combustible en entrega y retorno:

```sql
ALTER TABLE asignaciones ADD COLUMN start_combustible VARCHAR(40) NULL;
ALTER TABLE asignaciones ADD COLUMN end_combustible VARCHAR(40) NULL;
```

**Los valores válidos son:**
- `tanque_lleno`
- `tres_cuartos`
- `medio_tanque`
- `un_cuarto`
- `tanque_vacio`

### Ejecución en Ubuntu Server

```bash
# Conexión SSH al servidor
ssh usuario@ip_ubuntu_server

# Dentro del servidor, accede a la carpeta del proyecto
cd /path/to/flota-vehiculos

# Ejecuta la migración
php tests/migrate_asignaciones_combustible.php
# Salida esperada:
# ✅ Added: start_combustible
# ✅ Added: end_combustible
# Done.
```

---

## Instalación y Despliegue en Ubuntu Server

### Opción 1: Docker Compose (Recomendado)

```bash
# Clonar repositorio
git clone https://github.com/Spinracer/FLOTA-VEHICULOS-KM-MOTOS.git
cd FLOTA-VEHICULOS-KM-MOTOS

# Crear .env (copiar de .env.example si está disponible)
cp deploy.env.example .env

# Editar variables de entorno (especialmente DB_HOST, DB_USER, DB_PASS, ADMIN_EMAIL, ADMIN_PASSWORD)
nano .env

# Levantar contenedores
docker-compose up -d

# Ejecutar instalación y migraciones
docker exec flotacontrol-app php /var/www/html/install.php
docker exec flotacontrol-app php /var/www/html/tests/migrate_asignaciones_combustible.php

# Acceder a la aplicación
# http://localhost:8080
# Login: admin@flotacontrol.local / 123 (o tus credenciales configuradas)
```

### Opción 2: Instalación Manual en Ubuntu

```bash
# Actualizar y instalar dependencias
sudo apt update
sudo apt install -y nginx php-fpm php-mysql php-gd php-json php-mbstring mysql-server

# Clonar repositorio
cd /var/www/html
sudo git clone https://github.com/Spinracer/FLOTA-VEHICULOS-KM-MOTOS.git flota

# Configurar permisos
sudo chown -R www-data:www-data /var/www/html/flota
sudo chmod -R 755 /var/www/html/flota

# Crear .env
sudo cp /var/www/html/flota/deploy.env.example /var/www/html/flota/.env
sudo nano /var/www/html/flota/.env

# Crear base de datos MySQL
sudo mysql -u root -p <<EOF
CREATE DATABASE IF NOT EXISTS flotacontrol;
CREATE USER 'flotacontrol'@'localhost' IDENTIFIED BY 'flotapass';
GRANT ALL PRIVILEGES ON flotacontrol.* TO 'flotacontrol'@'localhost';
FLUSH PRIVILEGES;
EOF

# Ejecutar instalación
cd /var/www/html/flota
php install.php

# Ejecutar migración de asignaciones
php tests/migrate_asignaciones_combustible.php

# Configurar Nginx (ver DEPLOY_GUIDE.md)
sudo nano /etc/nginx/sites-available/flota

# Reiniciar servicios
sudo systemctl restart nginx php-fpm mysql
```

---

## Verificación Post-Despliegue

```bash
# 1. Verificar que la base de datos tiene las nuevas columnas
mysql -u flotacontrol -p flotacontrol -e "DESCRIBE asignaciones;" | grep combustible

# 2. Verificar que la migración se ejecutó
docker logs flotacontrol-app | grep -i combustible

# 3. Probar API de asignaciones (con token CSRF)
curl -X POST http://localhost:8080/api/asignaciones.php \
  -H 'Content-Type: application/json' \
  -H 'X-CSRF-Token: TOKEN_AQUI' \
  -d '{
    "vehiculo_id": 1,
    "operador_id": 1,
    "start_at": "2026-04-02 10:00:00",
    "start_combustible": "tanque_lleno"
  }'

# Respuesta esperada: {"ok":true,"id":XX}

# 4. Verificar alertas de operador inactivo
curl http://localhost:8080/api/alertas.php -b cookies.txt
```

---

## Cambios en Estructura de Carpetas

- ✅ Todos los archivos .md consolidados en `/docs/`
- ✅ Templates de importación en `/assets/`
- ✅ Migraciones en `/tests/`
- ✅ APIs endpoint: `/api/asignaciones.php`, `/api/importacion_operadores.php`
- ✅ UI: `/modules/web/asignaciones.php`, `/modules/web/operadores.php`

---

## Próximas Mejoras (Futuro)

- [ ] Export de asignaciones a PDF
- [ ] Dashboard de combustible por flota
- [ ] Sincronización de GPS en tiempo real
- [ ] Reportes de rentabilidad por operador
- [ ] Integración con WhatsApp para notificaciones

---

## Soporte y Troubleshooting

### Error: "Column 'start_combustible' not found"
```bash
# Ejecutar migración
docker exec flotacontrol-app php /var/www/html/tests/migrate_asignaciones_combustible.php
```

### Error: "Error interno del servidor" en asignaciones
- Verificar que la base de datos está sincronizada
- Ver logs de PHP: `docker logs -f flotacontrol-app`

### Las alertas no se disparan
- Verificar que los operadores tienen estado `Inactivo` o `Suspendido`
- Refrescar dashboard para forzar recalc: `POST /api/dashboard.php`

---

**Versión:** 1.0 (Abril 2026)  
**Rama:** main  
**Última actualización:** 2026-04-02
