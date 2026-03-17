# 🚀 Guía de Despliegue — FlotaControl
### Ubuntu Server + Docker + Cloudflare Tunnel
> Guía paso a paso para principiantes

---

## 📋 Antes de empezar

Necesitas tener a la mano:

- ✅ Acceso SSH a tu servidor (ej: `192.168.12.129`)
- ✅ Una contraseña segura que inventarás para la base de datos
- ✅ Tu correo y contraseña para el admin de FlotaControl
- ✅ El nombre de tu Cloudflare Tunnel existente

---

## PASO 1 — Conectarte al servidor

En tu PC abre una terminal y escribe:

```bash
ssh usuario@192.168.12.129
```

> Reemplaza `usuario` con tu usuario real: `root`, `ubuntu`, `admin`, etc.

---

## PASO 2 — Verificar que Docker está instalado

```bash
docker --version
docker compose version
```

Si ves los números de versión, **salta al Paso 3**.

Si dice `command not found`, instálalo con:

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
newgrp docker
```

---

## PASO 3 — Descargar el proyecto

```bash
mkdir -p ~/proyectos
cd ~/proyectos
git clone https://github.com/Spinracer/FLOTA-VEHICULOS-KM-MOTOS.git
cd FLOTA-VEHICULOS-KM-MOTOS
```

Verifica que se descargó bien:

```bash
ls
```

Debes ver archivos como: `docker-compose.yml`, `index.php`, `install.php`, etc.

---

## PASO 4 — Generar una clave secreta

Ejecuta este comando y **copia el resultado** (lo necesitarás en el paso siguiente):

```bash
openssl rand -hex 32
```

Ejemplo de resultado:
```
a3f8c2d1e9b4f07c3e2a1d8b5c6f9e0a2b4d6e8f0a1c3e5f7b9d1e3f5a7c9e1
```

---

## PASO 5 — Configurar el archivo de entorno

Crea tu archivo de configuración:

```bash
cp .env.example .env
nano .env
```

Edita los siguientes valores (**las demás líneas déjalas igual**):

```env
# ─── Base de datos ───
DB_HOST=mysql
DB_PORT=3306
DB_NAME=flotacontrol
DB_USER=flotacontrol
DB_PASS=TuContraseñaBD123          # ← inventa una contraseña segura

# ─── Aplicación ───
APP_ENV=production
APP_URL=https://flota.it-kmmotos.online
APP_DEBUG=false
APP_SECRET=pega_aqui_la_clave_del_paso_4

# ─── Admin inicial (para entrar a la app) ───
ADMIN_EMAIL=tu@email.com
ADMIN_PASSWORD=ClaveAdmin123
ADMIN_NAME=Administrador

# ─── Docker ───
MYSQL_ROOT_PASSWORD=OtraClaveRoot123   # ← diferente a DB_PASS
APP_PORT=127.0.0.1:8080               # ← IMPORTANTE: así exactamente
DB_EXTERNAL_PORT=3307
TZ=America/Tegucigalpa
```

**Para guardar y salir del editor:**
1. Presiona `Ctrl + O`
2. Presiona `Enter`
3. Presiona `Ctrl + X`

---

## PASO 6 — Construir y levantar los contenedores

Construye la imagen (tarda 2-5 minutos la primera vez):

```bash
docker compose build
```

Levanta todo en segundo plano:

```bash
docker compose up -d
```

Verifica que los 3 servicios estén corriendo:

```bash
docker compose ps
```

Debes ver algo así:

```
NAME                   STATUS
flotacontrol-app       running
flotacontrol-nginx     running
flotacontrol-db        running (healthy)
```

---

## PASO 7 — Verificar que la app instaló correctamente

```bash
docker compose logs app
```

Busca estas líneas (pueden tardar 30-60 segundos):

```
[entrypoint] MySQL listo.
[entrypoint] Instalación completada.
```

Prueba rápida desde el servidor:

```bash
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080
```

Debe responder `200` o `302`. Si ves otro número, revisa los logs y comparte el error.

---

## PASO 8 — Agregar FlotaControl a tu Cloudflare Tunnel

### 8.1 — Encuentra el nombre de tu túnel

```bash
cloudflared tunnel list
```

Verás algo así:

```
ID                                   NAME
abc123de-xxxx-xxxx-xxxx-xxxxxxxxxxxx mi-tunnel
```

Anota el valor de la columna **NAME**.

### 8.2 — Encuentra el archivo de configuración del túnel

```bash
# Prueba esta ruta primero:
cat /etc/cloudflared/config.yml

# Si no existe, prueba esta:
cat ~/.cloudflared/config.yml
```

### 8.3 — Edita la configuración del túnel

```bash
# Si el archivo está en /etc/cloudflared/
sudo nano /etc/cloudflared/config.yml

# Si está en ~/.cloudflared/
nano ~/.cloudflared/config.yml
```

Agrega el bloque de FlotaControl **antes** de la línea `service: http_status:404`:

```yaml
ingress:
  - hostname: snipeit.tudominio.com      # el que ya tenías para Snipe-IT
    service: http://localhost:8000

  - hostname: flota.it-kmmotos.online    # ← AGREGA ESTE BLOQUE
    service: http://localhost:8080

  - service: http_status:404             # siempre debe ser la última línea
```

Guarda: `Ctrl + O` → `Enter` → `Ctrl + X`

---

## PASO 9 — Registrar el dominio en el túnel

```bash
cloudflared tunnel route dns NOMBRE_DE_TU_TUNNEL flota.it-kmmotos.online
```

> Reemplaza `NOMBRE_DE_TU_TUNNEL` con el nombre del Paso 8.1

---

## PASO 10 — Reiniciar Cloudflare Tunnel

```bash
sudo systemctl restart cloudflared
```

Verifica que está activo:

```bash
sudo systemctl status cloudflared
```

Debe aparecer `active (running)` en verde.

---

## PASO 11 — Abrir la aplicación ✅

Espera 1-2 minutos y abre en tu navegador:

```
https://flota.it-kmmotos.online
```

Entra con las credenciales que configuraste:

| Campo | Valor |
|-------|-------|
| Email | El que pusiste en `ADMIN_EMAIL` |
| Contraseña | La que pusiste en `ADMIN_PASSWORD` |

---

## 🔧 Comandos útiles — Día a día

```bash
# Ver estado de los contenedores
docker compose ps

# Ver logs en vivo (Ctrl+C para salir)
docker compose logs -f

# Ver logs solo de la app
docker compose logs -f app

# Reiniciar solo la app
docker compose restart app

# Apagar todo (NO borra los datos)
docker compose stop

# Volver a encender
docker compose start
```

---

## 🔄 Actualizar a una nueva versión

```bash
cd ~/proyectos/FLOTA-VEHICULOS-KM-MOTOS
git pull origin main
docker compose build
docker compose up -d
```

---

## 🆘 Solución de problemas

### La app no carga en el navegador
```bash
docker compose logs app --tail=50
docker compose logs nginx --tail=20
```

### MySQL no arranca
```bash
docker compose logs mysql --tail=30
```

### El dominio no resuelve
```bash
sudo systemctl status cloudflared
cloudflared tunnel list
```

### Reiniciar todo desde cero (sin borrar datos)
```bash
docker compose down
docker compose up -d
```

### Ver cuánta memoria y CPU usan los contenedores
```bash
docker stats
```

---

## ⚠️ Cosas importantes

| ✅ Hacer | ❌ Nunca hacer |
|----------|----------------|
| `docker compose down` para apagar | `docker compose down -v` — **borra la base de datos** |
| Hacer backups periódicos de MySQL | Exponer el puerto 3307 en el firewall |
| Mantener `.env` solo en el servidor | Subir `.env` a GitHub |
| `APP_DEBUG=false` en producción | `APP_DEBUG=true` en producción |

---

## 📁 Estructura de datos persistentes

Los datos importantes se guardan en volúmenes Docker y **no se pierden** al reiniciar:

```
Volumen: mysql_data  →  Base de datos completa
Volumen: uploads     →  Imágenes y archivos subidos
```

---

*Guía generada para FlotaControl — KM Motos*
*Stack: PHP 8.3 · MySQL 8.0 · Nginx · Docker · Cloudflare Tunnel*
