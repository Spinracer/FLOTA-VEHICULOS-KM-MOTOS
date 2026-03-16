# FlotaControl — Instrucciones Docker

Guia completa para levantar FlotaControl con Docker en un servidor Ubuntu local.

---

## Requisitos previos

| Software | Version minima | Verificar |
|----------|---------------|-----------|
| Docker Engine | 24.0+ | `docker --version` |
| Docker Compose | v2.20+ | `docker compose version` |
| Git | 2.x | `git --version` |

### Instalar Docker en Ubuntu

```bash
# Instalar Docker
curl -fsSL https://get.docker.com | sudo sh

# Agregar tu usuario al grupo docker (evita usar sudo)
sudo usermod -aG docker $USER

# Cerrar sesion y volver a entrar para que aplique
exit
# (volver a conectar por SSH o abrir nueva terminal)

# Verificar
docker --version
docker compose version
```

---

## Inicio rapido (5 pasos)

### 1. Clonar el repositorio

```bash
git clone https://github.com/Spinracer/FLOTA-VEHICULOS-KM-MOTOS.git
cd FLOTA-VEHICULOS-KM-MOTOS
```

### 2. Crear archivo .env

```bash
cp .env.example .env
nano .env
```

Completa TODOS estos campos (son obligatorios):

```env
# Base de datos
DB_USER=flotacontrol
DB_PASS=UnPasswordSeguro123
DB_NAME=flotacontrol
MYSQL_ROOT_PASSWORD=OtroPasswordSeguro456

# Primer usuario admin (solo primera instalacion)
ADMIN_EMAIL=tu@email.com
ADMIN_PASSWORD=tu_password_minimo_8_chars
ADMIN_NAME=Tu Nombre

# Seguridad (genera uno aleatorio con: openssl rand -hex 32)
APP_SECRET=pega_aqui_el_string_aleatorio

# Opcional
APP_PORT=8080
TZ=America/Tegucigalpa
```

Generar APP_SECRET aleatorio:
```bash
openssl rand -hex 32
```

### 3. Levantar los contenedores

```bash
docker compose up -d
```

La primera vez:
1. Descarga imagenes de Docker Hub (~500MB total)
2. Compila la imagen PHP con extensiones (~2-3 min)
3. Inicia MySQL y espera a que este listo
4. Ejecuta install.php (crea tablas, catalogos, usuario admin)
5. Inicia PHP-FPM y Nginx

### 4. Verificar que todo esta corriendo

```bash
docker compose ps
```

Debes ver 3 contenedores con status "Up":

```
NAME                  STATUS
flotacontrol-app      Up
flotacontrol-nginx    Up
flotacontrol-db       Up (healthy)
```

### 5. Acceder al sistema

Abre en tu navegador: `http://IP-DEL-SERVIDOR:8080`

Inicia sesion con el email y password que configuraste en `ADMIN_EMAIL` / `ADMIN_PASSWORD`.

---

## Deploy con Script Interactivo (alternativa)

Si prefieres que el script te pregunte todo interactivamente:

```bash
sudo bash deploy.sh
# Selecciona opcion 4) Docker
```

El script genera el `.env` automaticamente con las respuestas que le des.

---

## Integracion con Cloudflare Tunnel

Para exponer FlotaControl a internet sin abrir puertos:

### 1. Instalar cloudflared (en el host, NO en Docker)

```bash
curl -fsSL https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb \
    -o /tmp/cloudflared.deb
sudo dpkg -i /tmp/cloudflared.deb
```

### 2. Autenticar con Cloudflare

```bash
cloudflared tunnel login
```

Esto abre un enlace en el navegador para autorizar tu cuenta.

### 3. Crear el tunnel

```bash
cloudflared tunnel create flotacontrol
cloudflared tunnel route dns flotacontrol flota.tudominio.com
```

### 4. Configurar /etc/cloudflared/config.yml

```yaml
tunnel: flotacontrol
credentials-file: /home/tu-usuario/.cloudflared/<TUNNEL_ID>.json

ingress:
  - hostname: flota.tudominio.com
    service: http://localhost:8080
  - service: http_status:404
```

Nota: el puerto `8080` debe coincidir con `APP_PORT` en tu `.env`.

### 5. Iniciar como servicio

```bash
sudo cloudflared service install
sudo systemctl start cloudflared
sudo systemctl enable cloudflared
```

### 6. Actualizar APP_URL en .env

```bash
# Editar .env
APP_URL=https://flota.tudominio.com
# Reiniciar app para que tome el cambio
docker compose restart app
```

Ahora FlotaControl es accesible desde `https://flota.tudominio.com` y los links de firma digital
usaran ese dominio automaticamente.

---

## Errores comunes y soluciones

### "FATAL: DB_USER no esta configurado"

**Causa:** Las variables `DB_USER` o `DB_PASS` estan vacias en `.env`.

```bash
# Verificar que .env existe y tiene valores
cat .env | grep DB_

# Deben aparecer:
# DB_USER=flotacontrol
# DB_PASS=tu_password

# Reiniciar despues de corregir
docker compose restart app
```

### MySQL connection refused / "MySQL not reachable after 30 attempts"

**Causa:** MySQL tarda mas de 60 segundos en iniciar (primera vez, hardware lento).

```bash
# Ver logs de MySQL
docker compose logs mysql

# Si dice "ready for connections", reiniciar app
docker compose restart app

# Si MySQL fallo, verificar password root
docker compose down
# Corregir MYSQL_ROOT_PASSWORD en .env
docker compose up -d
```

### Permission denied en uploads

**Causa:** El volumen de uploads no tiene permisos correctos.

```bash
docker compose exec app chown -R www-data:www-data /var/www/html/uploads
docker compose exec app chmod -R 755 /var/www/html/uploads
```

### install.php no se ejecuto (no hay tablas)

**Causa:** El archivo `.installed.lock` ya existe de una ejecucion anterior.

```bash
# Verificar
docker compose exec app ls -la /var/www/html/.installed.lock

# Para forzar reinstalacion:
docker compose exec app rm /var/www/html/.installed.lock
docker compose restart app

# Ver el log de instalacion
docker compose logs app | grep entrypoint
```

### Error 502 Bad Gateway

**Causa:** PHP-FPM no esta corriendo o se reinicio.

```bash
# Verificar estado
docker compose ps

# Si app no esta Up:
docker compose restart app

# Ver logs de error
docker compose logs app
docker compose logs nginx
```

### "Table 'flotacontrol.usuarios' doesn't exist"

**Causa:** La base de datos existe pero las tablas no se crearon.

```bash
# Forzar reinstalacion
docker compose exec app rm -f /var/www/html/.installed.lock
docker compose restart app
docker compose logs -f app
```

### Contenedor se reinicia en loop

**Causa:** Error fatal en la aplicacion o base de datos no accesible.

```bash
# Ver logs completos
docker compose logs --tail=50 app

# Errores comunes:
# - .env mal formateado (espacios, caracteres especiales sin escapar)
# - MYSQL_ROOT_PASSWORD diferente de la que uso MySQL al crearse
```

### Cambiar MYSQL_ROOT_PASSWORD despues del primer inicio

**NO se puede** cambiar el password de root editando `.env` si MySQL ya inicio
antes, porque los datos persisten en el volumen. Opciones:

```bash
# Opcion 1: Cambiar password dentro del contenedor
docker compose exec mysql mysql -u root -p'password_viejo' -e \
    "ALTER USER 'root'@'%' IDENTIFIED BY 'password_nuevo';"

# Opcion 2: Borrar todo y empezar de cero (PIERDE DATOS)
docker compose down -v
# Editar .env con nuevo password
docker compose up -d
```

---

## Backup y restauracion

### Backup de base de datos

```bash
# Backup manual
docker compose exec mysql mysqldump -u root -p"$(grep MYSQL_ROOT_PASSWORD .env | cut -d= -f2)" \
    flotacontrol > backup_$(date +%Y%m%d).sql

# Backup automatico (agregar a crontab del host)
# crontab -e
0 3 * * * cd /ruta/al/proyecto && docker compose exec -T mysql mysqldump -u root -p"TUPASSWORD" flotacontrol | gzip > /ruta/backups/db_$(date +\%Y\%m\%d).sql.gz
```

### Backup de uploads

```bash
# Copiar volumen de uploads al host
docker compose cp flotacontrol-app:/var/www/html/uploads ./backup_uploads_$(date +%Y%m%d)
```

### Restaurar base de datos

```bash
docker compose exec -T mysql mysql -u root -p"TUPASSWORD" flotacontrol < backup_20240101.sql
```

### Restaurar uploads

```bash
docker compose cp ./backup_uploads/. flotacontrol-app:/var/www/html/uploads/
docker compose exec app chown -R www-data:www-data /var/www/html/uploads
```

---

## Actualizar la aplicacion

```bash
cd /ruta/al/proyecto

# 1. Hacer backup primero
docker compose exec -T mysql mysqldump -u root -p"TUPASSWORD" flotacontrol > backup_pre_update.sql

# 2. Bajar contenedores
docker compose down

# 3. Actualizar codigo
git pull origin main

# 4. Reconstruir imagen PHP (si cambio Dockerfile o php.ini)
docker compose build --no-cache app

# 5. Levantar de nuevo
docker compose up -d

# 6. Si hay migraciones de BD, forzar reinstalacion
docker compose exec app rm -f /var/www/html/.installed.lock
docker compose restart app
```

---

## Comandos utiles

```bash
# ─── Estado ───
docker compose ps                    # Estado de contenedores
docker compose top                   # Procesos dentro de cada contenedor

# ─── Logs ───
docker compose logs -f               # Todos los logs en tiempo real
docker compose logs -f app           # Solo logs de PHP
docker compose logs -f nginx         # Solo logs de Nginx
docker compose logs -f mysql         # Solo logs de MySQL
docker compose logs --tail=100 app   # Ultimas 100 lineas de PHP

# ─── Shell ───
docker compose exec app bash         # Shell dentro del contenedor PHP
docker compose exec mysql mysql -u root -p  # Consola MySQL

# ─── Reiniciar ───
docker compose restart               # Reiniciar todos
docker compose restart app           # Solo PHP-FPM
docker compose restart nginx         # Solo Nginx

# ─── Parar / Iniciar ───
docker compose stop                  # Parar sin eliminar
docker compose start                 # Iniciar contenedores parados
docker compose down                  # Parar y eliminar contenedores (datos persisten)
docker compose down -v               # Parar, eliminar contenedores Y volumenes (BORRA TODO)

# ─── Disco ───
docker system df                     # Uso de disco de Docker
docker volume ls                     # Listar volumenes
```

---

## Estructura de contenedores

```
                    Cloudflare Tunnel (opcional)
                           |
                    puerto 8080
                           |
                    ┌──────┴──────┐
                    │    nginx    │  :80 dentro del contenedor
                    │  (estaticos │  Sirve CSS, JS, imagenes
                    │   + proxy)  │  Bloquea archivos sensibles
                    └──────┬──────┘
                           │
                    fastcgi :9000
                           │
                    ┌──────┴──────┐
                    │  app (PHP)  │  PHP 8.3-FPM
                    │  FlotaCtrl  │  Ejecuta todo el codigo PHP
                    │             │  Volumen: uploads/
                    └──────┬──────┘
                           │
                      TCP :3306
                           │
                    ┌──────┴──────┐
                    │   mysql     │  MySQL 8.0
                    │  (datos)    │  Volumen: mysql_data
                    └─────────────┘
```

---

## Notas para produccion

- **Siempre** genera un `APP_SECRET` aleatorio unico por servidor
- **Nunca** dejes `APP_DEBUG=true` en produccion
- Configura backups automaticos de BD y uploads
- Usa Cloudflare Tunnel para HTTPS sin certificados manuales
- Monitorea espacio en disco: `docker system df`
- Si el servidor se reinicia, Docker inicia los contenedores automaticamente (`restart: unless-stopped`)
