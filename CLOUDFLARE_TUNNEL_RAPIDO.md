# 🌐 Cloudflare Tunnel — Guía Rápida (Copiar y Pegar)

## 🎯 Objetivo

Conectar tu Docker en el servidor a **Cloudflare Tunnel** para acceder por `https://flota.it-kmmotos.online`

---

## ⚡ OPCIÓN FÁCIL: Script Automatizado (RECOMENDADO)

```bash
cd ~/proyectos/FLOTA-VEHICULOS-KM-MOTOS
bash setup-cloudflare-tunnel.sh
```

El script hace TODO automáticamente:
- ✅ Instala cloudflared (si no está)
- ✅ Autentica con Cloudflare
- ✅ Crea el tunnel
- ✅ Configura archivos
- ✅ Instala como servicio systemd
- ✅ Verifica que Docker está corriendo

**Tiempo: ~5 minutos**

---

## 📊 Después de Ejecutar el Script

Abre en tu navegador:

```
https://flota.it-kmmotos.online
```

✅ **¡Debería funcionar!**

---

## 🔄 Verificar Status

```bash
# Ver status del tunnel
sudo systemctl status cloudflared

# Ver logs
sudo journalctl -u cloudflared -f

# Listar tunnels creados
cloudflared tunnel list

# Ver estadísticas en Cloudflare Dashboard
# Networks → Tunnels → flota-docker
```

---

## 🆘 Troubleshooting

### ❌ "No funciona después de ejecutar el script"

Verificar en orden:

```bash
# 1. ¿Tunnel activo?
sudo systemctl status cloudflared

# 2. ¿Docker corriendo?
docker compose ps

# 3. Ver logs del tunnel
sudo journalctl -u cloudflared -n 50

# Si todo se ve OK, reiniciar
sudo systemctl restart cloudflared
docker compose restart
```

---

## ➡️ OPCIÓN MANUAL: Paso a Paso

Si el script no funciona por algún motivo, ver **DOCKER_CLOUDFLARE_TUNNEL.md**

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
| Detener tunnel | `sudo systemctl stop cloudflared` |
| Iniciar tunnel | `sudo systemctl start cloudflared` |

---

**🚀 ¡Tu FlotaControl está accesible por HTTPS con Cloudflare Tunnel!**

Sin puertos abiertos, sin certificados manuales, sin IP pública fija.

---

## 📖 Guía de Referencia Completa

Para detalles técnicos y aproximación manual paso a paso, ver: **DOCKER_CLOUDFLARE_TUNNEL.md**
