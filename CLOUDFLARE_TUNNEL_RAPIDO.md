# 🌐 Cloudflare Tunnel — Guía Rápida (Copiar y Pegar)

## 🎯 Objetivo

Conectar tu Docker en el servidor a **Cloudflare Tunnel** para acceder por `https://flota.it-kmmotos.online`

---

## 🚀 Paso 1: Crear Tunnel en Cloudflare Dashboard

1. Abre: https://dash.cloudflare.com
2. Selecciona dominio: **it-kmmotos.online**
3. Menú: **Networks → Tunnels**
4. Click: **Create a tunnel**

**Nombre del tunnel:** `flota-docker`

Click: **Create tunnel**

Verás un comando similar a este (Cloudflare te lo proporciona)

---

## 🖥️ Paso 2: Instalar Cloudflared en el Servidor

```bash
ssh usuario@tu_servidor_ip

# Descargar
curl -L --output cloudflared.deb https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb

# Instalar
sudo dpkg -i cloudflared.deb

# Autenticar
cloudflared tunnel login
```

Se abrirá en tu navegador. Autoriza y selecciona el dominio: **it-kmmotos.online**

---

## ⚙️ Paso 3: Configurar el Tunnel

```bash
sudo nano ~/.cloudflared/config.yml
```

Pega exactamente esto:

```yaml
tunnel: flota-docker
credentials-file: ~/.cloudflared/TUNNEL_ID.json

ingress:
  - hostname: flota.it-kmmotos.online
    service: http://localhost:8080
  - service: http_status:404
```

**Guardar:** `Ctrl+X` → `Y` → `ENTER`

---

## 🚀 Paso 4: Instalar y Arrancar el Servicio

```bash
# Instalar como servicio systemd
sudo cloudflared service install

# Iniciar
sudo systemctl start cloudflared

# Verificar que está corriendo
sudo systemctl status cloudflared
```

**Debe mostrar:** `● cloudflared.service - Loaded Loaded`

---

## ✅ Paso 5: Verificar en Cloudflare

1. Abre: https://dash.cloudflare.com
2. Networks → Tunnels
3. Busca **flota-docker**
4. Debe mostrar: **Status: HEALTHY** ✅

Si ves **UNHEALTHY**, ver troubleshooting abajo.

---

## 🐳 Paso 6: Asegurar que Docker Está Levantado

```bash
cd ~/proyectos/FLOTA-VEHICULOS-KM-MOTOS

# Ver estado
docker compose ps

# Si no está corriendo, levantar
docker compose up -d
```

---

## 🌐 Paso 7: Acceder a tu Aplicación

Abre en tu navegador:

```
https://flota.it-kmmotos.online
```

✅ **¡Debería funcionar!**

---

## 📊 Ver Status del Tunnel

```bash
# Ver status
sudo systemctl status cloudflared

# Ver logs
sudo journalctl -u cloudflared -f

# Listar tunnels
cloudflared tunnel list
```

---

## 🆘 Troubleshooting Rápido

### ❌ "UNHEALTHY" en Cloudflare

```bash
# Verificar Docker
docker compose ps

# Si está parado, levantar
docker compose up -d

# Reiniciar tunnel
sudo systemctl restart cloudflared

# Esperar 10 segundos y verificar
sudo systemctl status cloudflared
```

---

### ❌ "Connection refused"

```bash
# Ver logs detallados
sudo journalctl -u cloudflared -n 50

# Si dice "connection refused", Docker no está escuchando
docker compose ps

# Reiniciar Docker
docker compose restart

# Esperar 10 segundos
sleep 10

# Reiniciar tunnel
sudo systemctl restart cloudflared
```

---

### ❌ "Error en navegador al acceder"

1. Verifica que tunnel dice **HEALTHY** en Cloudflare
2. Espera 30 segundos
3. Limpia cache: `Ctrl+Shift+Delete`
4. Intenta en incógnito

---

## 🔄 Actualizar Código

Cuando hagas cambios en el código:

```bash
cd ~/proyectos/FLOTA-VEHICULOS-KM-MOTOS
git pull origin main
docker compose restart

# El tunnel automáticamente redirige a la versión nueva
```

---

## 🎯 Comandos Principales

| Acción | Comando |
|--------|---------|
| Ver status tunnel | `sudo systemctl status cloudflared` |
| Ver logs | `sudo journalctl -u cloudflared -f` |
| Reiniciar tunnel | `sudo systemctl restart cloudflared` |
| Ver estado Docker | `docker compose ps` |
| Reiniciar Docker | `docker compose restart` |
| Listar tunnels | `cloudflared tunnel list` |

---

## 🚀 Resumen Rápido

```
1. ssh usuario@tu_ip
2. Instalar cloudflared (3 comandos arriba)
3. cloudflared tunnel login
4. Configurar config.yml
5. sudo cloudflared service install
6. sudo systemctl start cloudflared
7. docker compose up -d
8. Abrir: https://flota.it-kmmotos.online
9. ¡LISTO!
```

---

**🎉 Tu FlotaControl está accesible por HTTPS con Cloudflare Tunnel**

Sin puertos abiertos, sin certificados manuales, sin IP pública fija.
