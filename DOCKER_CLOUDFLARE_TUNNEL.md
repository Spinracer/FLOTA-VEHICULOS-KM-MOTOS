# 🌐 Despliegue Docker con Cloudflare Tunnel

## 🎯 Objetivo

Desplegar **FlotaControl en Docker** en tu servidor con **Cloudflare Tunnel**, accesible por `https://flota.it-kmmotos.online` **sin abrir puertos en el firewall**.

---

## ✅ Ventajas de Cloudflare Tunnel

- ✅ **Sin puertos abiertos** — No expones tu servidor en internet
- ✅ **HTTPS automático** — Cloudflare maneja SSL
- ✅ **Subdominio** — `flota.it-kmmotos.online`
- ✅ **DDoS protection** — Cloudflare defiende tu servidor
- ✅ **No requiere IP pública fija** — Funciona con IP dinámica
- ✅ **Gratis** — Plan gratuito de Cloudflare incluye tunnels

---

## 📋 Requisitos

- [ ] Dominio: `it-kmmotos.online` (ya tienes en Cloudflare)
- [ ] Acceso a dashboard de Cloudflare
- [ ] Docker + Docker Compose en el servidor
- [ ] Acceso SSH al servidor

---

## 🔧 Paso 1: Crear Tunnel en Cloudflare

1. **Abre Cloudflare dashboard:** https://dash.cloudflare.com
2. Selecciona tu dominio: **it-kmmotos.online**
3. En el menú lateral: **Networks → Tunnels**
4. Haz clic en: **Create a tunnel**

### Crear el tunnel:

**Nombre:** `flota-docker` (o el que prefieras)

Haz clic en **Create tunnel**

---

## 🖥️ Paso 2: Instalar Cloudflare Connector en el Servidor

Cloudflare te dará un comando. En tu servidor:

```bash
ssh usuario@tu_servidor_ip

# Descargar e instalar cloudflared
curl -L --output cloudflared.deb https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
sudo dpkg -i cloudflared.deb

# Autenticar (se abrirá una ventana de autorización)
cloudflared tunnel login
```

Se te pedirá que selecciones el dominio: **it-kmmotos.online**

Verás una URL para autorizar — haz clic en ella en tu navegador.

**Resultado esperado:**
```
Successfully authenticated your machine.
Tunnel credentials have been saved to /root/.cloudflared/TUNNEL_ID.json
```

---

## 📍 Paso 3: Configurar el Tunnel

En tu servidor, crea la configuración del tunnel:

```bash
sudo nano ~/.cloudflared/config.yml
```

Pega esto:

```yaml
tunnel: flota-docker
credentials-file: ~/.cloudflared/TUNNEL_ID.json

ingress:
  - hostname: flota.it-kmmotos.online
    service: http://localhost:8080
  - service: http_status:404
```

> **Nota:** El `TUNNEL_ID` se genera automáticamente, solo copia tal cual.

Guarda: `Ctrl+X`, `Y`, `ENTER`

---

## 🚀 Paso 4: Ejecutar el Tunnel en Background

```bash
# Instalar systemd para que arranque automáticamente
sudo cloudflared service install

# Iniciar el servicio
sudo systemctl start cloudflared
sudo systemctl status cloudflared

# Ver que esté corriendo
cloudflared tunnel list
```

**Debe mostrar:**
```
ID                    NAME            CREATED              STATUS
xxxxx-xxxxx           flota-docker    2026-03-17 15:30     HEALTHY
```

---

## ✅ Paso 5: Verificar en Cloudflare Dashboard

1. Vuelve a Cloudflare → Networks → Tunnels
2. Deberías ver: **flota-docker** con status **HEALTHY**
3. La ruta mostrará: `flota.it-kmmotos.online → http://localhost:8080`

---

## 🌐 Paso 6: Acceder a tu Aplicación

Abre en tu navegador:

```
https://flota.it-kmmotos.online
```

✅ **¡Debería funcionar!**

---

## 🐳 Paso 7: Levantar Docker

Si no lo has hecho aún:

```bash
cd ~/proyectos/FLOTA-VEHICULOS-KM-MOTOS

# Editar .env
nano .env
```

**Cambiar estas líneas:**

```env
APP_URL=https://flota.it-kmmotos.online
APP_ENV=production
APP_DEBUG=false
DB_PASS=TuContraseñaSegura
MYSQL_ROOT_PASSWORD=RootPass
```

Guardar y continuar:

```bash
docker compose build
docker compose up -d
docker compose ps
```

---

## 📊 Verificar Acceso

Ahora accede desde tu navegador:

```
https://flota.it-kmmotos.online
```

Deberías ver:
- ✅ Login de FlotaControl
- ✅ HTTPS (candado verde)
- ✅ Dominio correcto

---

## 🔄 Actualizar Túnel (Cuando Cambies Código)

```bash
cd ~/proyectos/FLOTA-VEHICULOS-KM-MOTOS
git pull origin main
docker compose restart

# El tunnel automáticamente redirige a la nueva versión
```

---

## 🛠️ Comandos del Tunnel

```bash
# Ver status del tunnel
sudo systemctl status cloudflared

# Ver logs del tunnel
sudo journalctl -u cloudflared -f

# Listar tunnels
cloudflared tunnel list

# Reiniciar
sudo systemctl restart cloudflared

# Detener
sudo systemctl stop cloudflared

# Iniciar
sudo systemctl start cloudflared
```

---

## 🔐 Seguridad del Tunnel

### 1. Cloudflare maneja HTTPS
```
Cliente (navegador)
    ↓ HTTPS
Cloudflare (seguro)
    ↓ HTTP
Tu servidor (privado)
```

### 2. Tu servidor NO está expuesto en internet
- IP privada OK
- IP dinámica OK
- Firewall restrictivo OK

### 3. DNS solo apunta a Cloudflare
```
flota.it-kmmotos.online → Cloudflare IP
                          ↓
                    Tunnel → Tu servidor
```

---

## 📈 Monitoreo del Tunnel en Cloudflare

1. **Network → Tunnels**
2. Verás:
   - Status (HEALTHY/UNHEALTHY)
   - Requests/min
   - Última conexión

Si ves **UNHEALTHY**:

```bash
# Check Docker
docker compose ps

# Check tunnel
sudo systemctl status cloudflared

# Restart tunnel
sudo systemctl restart cloudflared
```

---

## 🌍 Agregar Más Subdominios (Opcional)

Si querés hospedar otro proyecto:

Editar `~/.cloudflared/config.yml`:

```yaml
ingress:
  - hostname: flota.it-kmmotos.online
    service: http://localhost:8080
  - hostname: otro.it-kmmotos.online
    service: http://localhost:8081
  - service: http_status:404
```

Reiniciar:
```bash
sudo systemctl restart cloudflared
```

---

## 🆘 Troubleshooting

### ❌ "Tunnel shows UNHEALTHY"

```bash
# Ver logs
sudo journalctl -u cloudflared -n 50

# Si dice "connection refused":
docker compose ps

# Si Docker no está corriendo:
docker compose up -d
docker compose restart
```

---

### ❌ "No puedo acceder a https://flota.it-kmmotos.online"

1. Verifica el tunnel está HEALTHY en Cloudflare
2. Verifica Docker está corriendo: `docker compose ps`
3. Verifica localhost:8080 funciona en el servidor: `curl http://localhost:8080`

Si todo está OK pero aún no funciona:

```bash
# Reiniciar loop: Docker → Tunnel
docker compose restart
sudo systemctl restart cloudflared

# Esperar 30 segundos
sleep 30

# Volver a intentar en navegador
```

---

### ❌ "Error de certificado SSL en el navegador"

Debería ser automático de Cloudflare. Si persiste:

1. Espera 5 minutos (Cloudflare propaga)
2. Limpia cache del navegador: `Ctrl+Shift+Delete`
3. Intenta en incógnito

---

## 📋 Checklist Final con Clouflare Tunnel

- [ ] Dominio en Cloudflare: ✅ it-kmmotos.online
- [ ] Tunnel creado: ✅ flota-docker
- [ ] Credentials descargadas: ✅
- [ ] config.yml configurado: ✅
- [ ] cloudflared instalado: ✅
- [ ] Service activo: ✅ systemctl
- [ ] Docker corriendo: ✅ docker compose
- [ ] flota.it-kmmotos.online accesible: ✅

---

## 📊 Arquitectura Final

```
navegador (tu PC/celular)
    ↓ HTTPS
Cloudflare (seguro)
    ↓ Tunnel encriptado
Tu servidor (privado)
    ↓ HTTP
Docker Container (Nginx)
    ↓
FlotaControl App
```

---

## 🎯 Resumen de Pasos

1. **Cloudflare:** Crear tunnel `flota-docker`
2. **Servidor:** Instalar cloudflared
3. **Servidor:** Autenticar: `cloudflared tunnel login`
4. **Servidor:** Configurar: `~/.cloudflared/config.yml`
5. **Servidor:** Instalar service: `cloudflared service install`
6. **Servidor:** Iniciar: `sudo systemctl start cloudflared`
7. **Docker:** `docker compose up -d`
8. **Navegador:** https://flota.it-kmmotos.online

---

**🚀 ¡Tu FlotaControl está en línea con Cloudflare Tunnel!**

Sin puertos abiertos, sin certificados manuales, sin IP pública fija.
