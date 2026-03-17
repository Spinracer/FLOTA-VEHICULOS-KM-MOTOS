# 🐳 Despliegue Docker — Guía Rápida (Copiar y Pegar)

## 🎯 Objetivo

Desplegar **FlotaControl en Docker** en tu servidor en **~10 minutos**. Sin instalar nada nativo, todo aislado.

---

## 📋 Lo que Necesitas

- [ ] IP del servidor o nombre de host
- [ ] Acceso SSH funcional
- [ ] Docker instalado (o lo instalaré por ti)
- [ ] ~2GB RAM disponible
- [ ] Puerto disponible (ej: 8080 para la app)

---

## 🚀 Paso 1: Conectar al Servidor

```bash
ssh usuario@tu_servidor_ip
# Ejemplo: ssh ubuntu@192.168.1.100
```

---

## ✅ Paso 2: Verificar Docker

```bash
docker --version && docker compose version
```

**Si ves versiones:** ¡Perfecto, salta al Paso 4!

**Si hay error:** Ejecuta Paso 3 (instalar Docker)

---

## 🔧 Paso 3: Instalar Docker (SOLO si no está instalado)

```bash
curl -fsSL https://get.docker.com -o get-docker.sh && sudo sh get-docker.sh
```

```bash
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose && sudo chmod +x /usr/local/bin/docker-compose
```

```bash
# Verificar instalación
docker compose version
```

---

## 📥 Paso 4: Clonar el Repositorio

```bash
mkdir -p ~/proyectos && cd ~/proyectos
git clone https://github.com/TuUsuario/FLOTA-VEHICULOS-KM-MOTOS.git
cd FLOTA-VEHICULOS-KM-MOTOS
```

---

## ⚙️ Paso 5: Configurar el .env

```bash
cp .env.example .env
nano .env
```

**Editar ESTAS líneas:**

```
Línea 6:  DB_PASS=TuContraseñaSegura2024@          (cambiar)
Línea 11: APP_ENV=production                        (o development si es DEV)
Línea 12: APP_URL=http://tu_ip:8080                (cambiar IP)
Línea 14: APP_SECRET=???                           (generar abajo)
Línea 18: ADMIN_EMAIL=tu@email.com                 (cambiar)
Línea 19: ADMIN_PASSWORD=PasswordAdmin2024@        (cambiar)
Línea 20: ADMIN_NAME=Tu Nombre                     (cambiar)
Línea 23: MYSQL_ROOT_PASSWORD=RootPass2024@        (cambiar)
```

**Para generar APP_SECRET:**

En otra terminal del servidor (o en tu PC):

```bash
openssl rand -hex 32
# Copia la salida y pégalo en APP_SECRET
```

**Para generar contraseñas seguras:**

```bash
openssl rand -base64 24
# Ejemplo de salida: MySecurePass2024@ABC123XYZ789
```

**Guardar el archivo:** Presiona `Ctrl+X`, luego `Y`, luego `ENTER`

---

## 🐳 Paso 6: Construir Imagen Docker

```bash
docker compose build
```

**Que verás:**

```
Building app
...
✅ Successfully built ...
```

Tarda ~1-2 minutos (solo la primera vez).

---

## 🚀 Paso 7: Levantar Contenedores

```bash
docker compose up -d
```

**Que verás:**

```
✅ Container flotacontrol-app is running
✅ Container flotacontrol-db is running
✅ Container flotacontrol-nginx is running
```

---

## ⏳ Paso 8: Esperar a que MySQL Esté Listo

```bash
docker compose logs mysql | tail -5
```

Espera hasta ver algo como:

```
... ready for connections
```

Si aún dice "setting up database", espera 5 segundos más y vuelve a ejecutar.

---

## 🌐 Paso 9: Acceder en Navegador

Abre en tu navegador:

```
http://tu_ip_servidor:8080
```

**Ejemplo:** `http://192.168.1.100:8080`

---

## 🛠️ Paso 10: Completar Instalador Web

Si es la primera vez:

1. Verás **"Installer — FlotaControl"** o pantalla de login
2. Si ves instalador:
   - Haz clic en **"Comenzar Instalación"**
   - Verifica conectividad (debe estar OK)
   - Crea admin
   - **GUARDA credenciales**

Si ves login directamente, usa las credenciales del `.env`:

- **Email:** `coordinadorityseguridadkmmotos@gmail.com` (default)
- **Password:** `coordinadorityseguridadkmmotos@gmail.com` (default)

---

## ✅ Paso 11: LISTO

Ahora tienes FlotaControl corriendo en Docker.

**¡Ve a la web y empieza a usar!**

---

## 📊 Verificar que Todo Está Corriendo

```bash
docker compose ps
```

**Resultado esperado:**

```
NAME                   STATUS
flotacontrol-app       Up 2 minutes
flotacontrol-db        Up 2 minutes (healthy)
flotacontrol-nginx     Up 2 minutes
```

Si ves `Exit` o `Exited`, hay error. Ver logs:

```bash
docker compose logs -f app
```

---

## 🔄 Comandos Útiles

| Acción | Comando |
|--------|---------|
| Ver estado | `docker compose ps` |
| Reiniciar todo | `docker compose restart` |
| Ver logs | `docker compose logs -f app` |
| Detener (sin borrar datos) | `docker compose stop` |
| Reanudar | `docker compose start` |
| Actualizar código | `git pull && docker compose restart` |

---

## 🆘 Troubleshooting Rápido

### ❌ "Puerto 8080 ya está en uso"

Cambiar en `.env`:

```env
APP_PORT=8081
```

Reiniciar:

```bash
docker compose restart
```

Acceder: `http://tu_ip:8081`

---

### ❌ "No puedo acceder a http://tu_ip:8080"

Ver logs:

```bash
docker compose logs app | tail -20
```

Si ves error, reiniciar:

```bash
docker compose restart
```

Esperar 10 segundos y volver a intentar.

---

### ❌ "La BD no conecta"

Ver logs de MySQL:

```bash
docker compose logs mysql | tail -30
```

Si sigue fallando:

```bash
docker compose down
docker compose up -d
```

**⚠️ Esto NO borra los datos, los datos están en volúmenes Docker.**

---

### ❌ "¿Cómo accedo desde otra PC?"

Si estás en la misma red:

```
http://192.168.1.100:8080
```

(Reemplaza la IP por la de tu servidor)

---

## 🌐 Agregar Dominio Después

Cuando quieras usar dominio (`https://flota.miempresa.com`):

1. Editar `.env`:

```env
APP_URL=https://flota.miempresa.com
```

2. Ver guía **DOCKER_DEPLOY.md → "Paso 8: Configurar Dominio"**

---

## 💾 Backup de Datos

```bash
# Backup de BD (en el servidor)
docker exec flotacontrol-db mysqldump -u flotacontrol -p'TuPassword' flotacontrol > backup_$(date +%Y%m%d).sql

# Copiar a tu PC
scp usuario@tu_ip:backup_*.sql ~/backups/
```

---

## 🎯 Resumen

```
1. ssh usuario@tu_ip
2. mkdir -p ~/proyectos && cd ~/proyectos
3. git clone [...] && cd FLOTA-VEHICULOS-KM-MOTOS
4. cp .env.example .env && nano .env   (editar)
5. docker compose build
6. docker compose up -d
7. Abrir en navegador: http://tu_ip:8080
8. ¡Listo!
```

---

**Si necesitas más detalles, lee: DOCKER_DEPLOY.md**

**🚀 ¡Tu FlotaControl está en Docker!**
