# 🐳 Despliegue en Docker — Servidor Remoto

## 📍 Objetivo

Desplegar **FlotaControl en Docker** en tu servidor remoto de forma **aislada**, sin afectar otros proyectos Docker.

---

## ✅ Requisitos Pre-Despliegue

En tu servidor remoto:

- [ ] **Docker 20.10+** instalado y funcionando
- [ ] **Docker Compose 2.0+** instalado
- [ ] **Puerto disponible** para la app (ej: 8080, 8081, etc.)
- [ ] **Puerto disponible** para MySQL (ej: 3307, 3308, etc.)
- [ ] **Acceso SSH** funcional
- [ ] **Git** instalado
- [ ] **2 GB RAM** mínimo disponible

---

## 🔧 Paso 1: Instalación de Docker y Docker Compose (Si no está instalado)

```bash
# Conectar al servidor
ssh usuario@tu_servidor_ip

# Instalar Docker (si no tiene)
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Instalar Docker Compose (si no tiene)
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Verificar instalación
docker --version
docker compose version
```

---

## 📥 Paso 2: Clonar Repositorio

```bash
# Crear directorio para el proyecto
mkdir -p ~/proyectos
cd ~/proyectos

# Clonar
git clone https://github.com/TuUsuario/FLOTA-VEHICULOS-KM-MOTOS.git
cd FLOTA-VEHICULOS-KM-MOTOS
```

---

## ⚙️ Paso 3: Configurar .env para Servidor

```bash
# Copiar el archivo de ejemplo
cp .env.example .env

# EDITAR .env con credenciales de servidor
nano .env
```

**Contenido del `.env` para SERVIDOR:**

```env
# Base de datos
DB_HOST=mysql
DB_PORT=3306
DB_NAME=flotacontrol
DB_USER=flotacontrol
DB_PASS=TuContraseñaSegura2024@        # ⚠️ CAMBIAR ESTO

# Aplicación
APP_NAME=FlotaControl
APP_ENV=production                      # Cambiar de development a production
APP_URL=http://tu_servidor_ip:8080     # O tu dominio después
APP_DEBUG=false                         # Cambiar a false en producción
APP_SECRET=tuSecretoAleatorio64Chars   # Generar uno nuevo

# Sesión
SESSION_NAME=FLOTACONTROL_PROD
SESSION_LIFETIME=7200

# Admin inicial (crear después en web si lo prefieres)
ADMIN_EMAIL=tu@email.com
ADMIN_PASSWORD=TuPasswordAdmin2024@    # Mínimo 8 caracteres
ADMIN_NAME=Tu Nombre

# MySQL root (importante para inicialización)
MYSQL_ROOT_PASSWORD=RootPass2024@      # Contraseña del root de MySQL

# Puertos (cambiar si entran en conflicto)
APP_PORT=8080                          # Puerto externo de Nginx
DB_EXTERNAL_PORT=3307                  # Puerto externo de MySQL

# Timezone
TZ=America/Tegucigalpa                 # O tu zona horaria
```

---

## 🔐 Generar Valores Seguros

Usa esto para generar valores seguros:

```bash
# Generar APP_SECRET (64 caracteres hexadecimales)
openssl rand -hex 32

# Generar contraseña segura
openssl rand -base64 24

# Ejemplo de salida:
# APP_SECRET=a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2
# DB_PASS=MySecurePass2024@ABC123XYZ789
```

---

## 🐳 Paso 4: Levantar los Contenedores

```bash
# Asegúrate de estar en el directorio del proyecto
cd ~/proyectos/FLOTA-VEHICULOS-KM-MOTOS

# Construir la imagen (primera vez, tarda ~2 min)
docker compose build

# Levantar contenedores
docker compose up -d

# Ver estado
docker compose ps
```

**Resultado esperado:**

```
NAME                 IMAGE                          STATUS
flotacontrol-app     flota-vehiculos-km-motos-app   Up 2 minutes
flotacontrol-db      mysql:8.0                      Up 2 minutes (healthy)
flotacontrol-nginx   nginx:1.25-alpine              Up 2 minutes
```

---

## ✅ Paso 5: Esperar a que BD Esté Lista

```bash
# Ver logs de MySQL
docker compose logs mysql | tail -10

# Esperar hasta ver "ready for connections" (5-10 segundos)
```

---

## 🌐 Paso 6: Abrir en Navegador

Ahora abre en tu navegador:

```
http://tu_servidor_ip:8080
```

**Verás:** Pantalla de login or instalador

---

## 🛠️ Paso 7: Ejecutar Instalador Web (Primera Vez)

Si es la **primera instalación**:

```
http://tu_servidor_ip:8080/install.php
```

1. Haz clic en **"Comenzar Instalación"**
2. Verifica conectividad (debe estar OK)
3. Crea el **usuario administrador**
4. **GUARDA las credenciales**

---

## 🎯 Acceso Final

- **URL:** `http://tu_servidor_ip:8080`
- **Email:** El que creaste en el instalador
- **Contraseña:** La que creaste en el instalador

**Acceso desde otra PC:**
```
http://192.168.1.100:8080  (si tu servidor está en esa IP)
```

---

## 📊 Verificar Estado en Cualquier Momento

```bash
# Ver contenedores corriendo
docker compose ps

# Ver logs de la aplicación
docker compose logs -f app

# Ver logs de Nginx
docker compose logs -f nginx

# Ver logs de MySQL
docker compose logs -f mysql

# Estadísticas de recursos
docker stats
```

---

## 🔌 Puertos y Conexiones

### Dentro de los contenedores (internos):

```
app → puerto 9000 (PHP-FPM)
nginx → puerto 80 (HTTP interno)
mysql → puerto 3306 (interno)
```

### Desde el host (externos):

```
Nginx → puerto 8080 (configurable en .env → APP_PORT)
MySQL → puerto 3307 (configurable en .env → DB_EXTERNAL_PORT)
```

---

## 🔄 Comandos Comunes

```bash
# Ver estado
docker compose ps

# Iniciar contenedores
docker compose up -d

# Detener sin eliminar
docker compose stop

# Reanudar
docker compose start

# Reiniciar TODO
docker compose restart

# Ver logs
docker compose logs -f app

# Entrar a contenedor app
docker exec -it flotacontrol-app bash

# Entrar a contenedor MySQL
docker exec -it flotacontrol-db mysql -u flotacontrol -p

# Eliminar TODO (¡CUIDADO! Borra datos)
docker compose down -v
```

---

## 🐛 Troubleshooting

### ❌ "puerto 8080 ya está en uso"

Cambiar puerto en `.env`:

```env
APP_PORT=8081              # Cambiar a 8081
```

Luego reiniciar:

```bash
docker compose restart
```

Acceso: `http://tu_ip:8081`

---

### ❌ "No puedo conectar a la app"

Ver logs:

```bash
docker compose logs app | tail -50
```

Si ves errores de BD:

```bash
docker compose restart
docker compose logs mysql
```

---

### ❌ "MySQL no inicia"

```bash
# Ver logs
docker compose logs mysql | tail -30

# Si persiste, eliminar y créar de nuevo
docker compose down -v      # ¡BORRA LOS DATOS!
docker compose up -d         # Crear nuevo
```

> ⚠️ **Esto elimina la BD. Usa respaldo si tienes datos importantes**

---

### ❌ "Error: Can't connect to database"

Verificar credenciales en `.env`:

```bash
# Ver el .env
cat .env | grep DB_

# Entrar a MySQL y verificar
docker exec -it flotacontrol-db mysql -u root -pTuRootPassword -e "SELECT user FROM mysql.user;"
```

---

## 💾 Backups en Docker

### Backup de BD

```bash
# Exportar BD a un SQL
docker exec flotacontrol-db mysqldump -u flotacontrol -p'TuPassword' flotacontrol > backup_$(date +%Y%m%d_%H%M).sql

# Comprimir
gzip backup_*.sql
```

### Copiar backup a tu PC

```bash
scp usuario@tu_servidor:/ruta/backup_*.sql.gz ~/backups/
```

### Restaurar BD

```bash
# Descomprimir si está comprimido
gunzip backup_*.sql.gz

# Restaurar
docker exec -i flotacontrol-db mysql -u flotacontrol -p'TuPassword' flotacontrol < backup_*.sql
```

---

## 📂 Estructura Docker en Servidor

```
~/proyectos/FLOTA-VEHICULOS-KM-MOTOS/
├── .env                              ← Configuración (IMPORTANTE)
├── docker-compose.yml                ← Orquestación
├── docker/
│   ├── Dockerfile                    ← Imagen de la app
│   ├── entrypoint.sh                 ← Script de inicio
│   ├── nginx.conf                    ← Config Nginx
│   └── php.ini                       ← Config PHP
├── [código de la aplicación]
└── uploads/                          ← Archivos (volumen persistente)

Docker volumes (datos persistentes):
  flotacontrol_uploads → uploads/
  flotacontrol_mysql_data → BD
```

---

## 🔒 Seguridad Post-Despliegue

### 1. Cambiar Contraseña Admin

En la web:
```
Sistema > Usuarios > [Tu usuario] > Cambiar Contraseña
```

### 2. Cambiar Credenciales de BD

En `.env`:

```env
DB_PASS=NuevaContraseña2024@
MYSQL_ROOT_PASSWORD=NuevaRootPassword2024@
```

Luego reiniciar:

```bash
docker compose down
docker compose up -d
```

### 3. Eliminar install.php

```bash
# Dentro del contenedor o en el host
rm ./install.php
```

### 4. Cambiar APP_DEBUG a false

En `.env`:

```env
APP_DEBUG=false          # De development a production
APP_ENV=production
```

Reiniciar:

```bash
docker compose restart
```

---

## 🌐 Paso 8: Configurar Dominio (Después)

Cuando quieras agregar dominio:

### 1. Apuntar DNS a tu servidor

En tu registrador (Godaddy, Namecheap, etc.):

```
A record
Dominio: flota.miempresa.com
IP: tu_servidor_ip
```

### 2. Crear Reverse Proxy con Nginx (En el host)

Si quieres `https://flota.miempresa.com` sin puerto:

Crear `/etc/nginx/sites-available/flotacontrol-proxy`:

```nginx
server {
    listen 80;
    server_name flota.miempresa.com;

    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Habilitar:

```bash
sudo ln -s /etc/nginx/sites-available/flotacontrol-proxy /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# SSL con Let's Encrypt
sudo certbot --nginx -d flota.miempresa.com
```

Luego en `.env`:

```env
APP_URL=https://flota.miempresa.com
```

---

## 📈 Escalado (Opcional)

Si necesitas más recursos:

```bash
# Aumentar limites de memoria en docker-compose.yml
# O usar Docker Swarm / Kubernetes

# Por ahora, reiniciar es suficiente
docker compose restart
```

---

## ✨ Ventajas de Docker

✅ **Aislado** — No afecta otros proyectos
✅ **Portátil** — Funciona igual en cualquier servidor
✅ **Fácil backup** — Exportar volumes es simple
✅ **Fácil actualización** — Só actualizar código y restart
✅ **Rollback fácil** — Revertir a versión anterior
✅ **Sin conflictos** — Usa puertos propios

---

## 🎯 Resumen de Comandos Principales

| Acción | Comando |
|--------|---------|
| Iniciar | `docker compose up -d` |
| Detener | `docker compose stop` |
| Reiniciar | `docker compose restart` |
| Ver estado | `docker compose ps` |
| Ver logs | `docker compose logs -f app` |
| Actualizar código | `git pull origin main && docker compose restart` |
| Backup BD | `docker exec flotacontrol-db mysqldump -u flotacontrol -p'pass' flotacontrol > backup.sql` |

---

## ⚠️ Datos Persistentes

Los datos están en **volúmenes Docker**:

```bash
# Ver volúmenes
docker volume ls | grep flotacontrol

# Ver dónde están en el host
docker volume inspect flotacontrol_mysql_data
```

Estos datos **NO se pierden** al hacer:
- `docker compose stop`
- `docker compose restart`

Se pierden SOLO con:
- `docker compose down -v`  (¡NO HAGAS ESTO sin backup!)

---

## 📞 Ayuda Rápida

**La app no carga:**
```bash
docker compose logs app | tail -50
```

**MySQL no conecta:**
```bash
docker compose logs mysql | tail -30
```

**Reiniciar todo:**
```bash
docker compose down && docker compose up -d
```

**Ver recursos:**
```bash
docker stats
```

---

**🚀 ¡Tu FlotaControl está aislado y seguro en Docker!**
