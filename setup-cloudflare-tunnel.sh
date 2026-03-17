#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
# FlotaControl — Configurador de Cloudflare Tunnel (Post-instalación)
# Uso: bash setup-cloudflare-tunnel.sh
# ═══════════════════════════════════════════════════════════════════

set -euo pipefail

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

banner() {
    echo ""
    echo -e "${CYAN}╔═══════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║${NC}  ${BOLD}Configurador de Cloudflare Tunnel${NC}           ${CYAN}║${NC}"
    echo -e "${CYAN}║${NC}  FlotaControl                                 ${CYAN}║${NC}"
    echo -e "${CYAN}╚═══════════════════════════════════════════════════╝${NC}"
    echo ""
}

info()    { echo -e "${CYAN}[INFO]${NC} $*"; }
success() { echo -e "${GREEN}[OK]${NC}   $*"; }
warn()    { echo -e "${YELLOW}[!]${NC}    $*"; }
error()   { echo -e "${RED}[ERR]${NC}  $*"; }

ask() {
    local prompt="$1" default="${2:-}" var
    if [[ -n "$default" ]]; then
        read -rp "$(echo -e "${BOLD}$prompt${NC} [${default}]: ")" var
        echo "${var:-$default}"
    else
        read -rp "$(echo -e "${BOLD}$prompt${NC}: ")" var
        echo "$var"
    fi
}

confirm() {
    local prompt="$1"
    read -rp "$(echo -e "${YELLOW}$prompt [s/N]:${NC} ")" yn
    [[ "$yn" =~ ^[sS]$ ]]
}

# Verificar si estamos en el directorio correcto
if [[ ! -f "docker-compose.yml" ]]; then
    error "Este script debe ejecutarse desde el directorio de FlotaControl"
    exit 1
fi

banner

echo -e "${BOLD}Configuración de Cloudflare Tunnel${NC}"
echo ""
echo "Este script te ayudará a configurar Cloudflare Tunnel"
echo "para acceder a tu FlotaControl desde internet."
echo ""

# ─ Paso 1: Verificar cloudflared instalado ─
echo -e "${BOLD}Paso 1: Verificando cloudflared...${NC}"
if ! command -v cloudflared &> /dev/null; then
    warn "cloudflared no está instalado"
    if confirm "¿Instalar cloudflared ahora?"; then
        curl -fsSL https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb \
            -o /tmp/cloudflared.deb
        sudo dpkg -i /tmp/cloudflared.deb
        rm -f /tmp/cloudflared.deb
        success "cloudflared instalado"
    else
        error "cloudflared es necesario. Instálalo primero."
        exit 1
    fi
else
    success "cloudflared está instalado: $(cloudflared --version)"
fi

# ─ Paso 2: Obtener datos del tunnel ─
echo ""
echo -e "${BOLD}Paso 2: Datos del Tunnel${NC}"
TUNNEL_NAME=$(ask "Nombre del tunnel" "flota-docker")
TUNNEL_HOSTNAME=$(ask "Hostname público (ej: flota.it-kmmotos.online)" "")

while [[ -z "$TUNNEL_HOSTNAME" ]]; do
    error "El hostname es obligatorio"
    TUNNEL_HOSTNAME=$(ask "Hostname público" "")
done

# ─ Paso 3: Autenticar con Cloudflare ─
echo ""
echo -e "${BOLD}Paso 3: Autenticar con Cloudflare${NC}"
info "Se abrirá tu navegador para autorizar cloudflared"
info "Selecciona el dominio de Cloudflare"
echo ""

if confirm "¿Continuar con autenticación?"; then
    cloudflared tunnel login
    success "Autenticación completada"
else
    warn "Omitido. Deberás ejecutar: cloudflared tunnel login"
fi

# ─ Paso 4: Crear el tunnel ─
echo ""
echo -e "${BOLD}Paso 4: Crear el tunnel${NC}"
info "Creando tunnel: $TUNNEL_NAME"

if cloudflared tunnel create "$TUNNEL_NAME"; then
    success "Tunnel creado: $TUNNEL_NAME"
else
    warn "El tunnel podría ya existir, continuando..."
fi

# ─ Paso 5: Configurar el archivo config.yml ─
echo ""
echo -e "${BOLD}Paso 5: Generar config.yml${NC}"

# Encontrar el archivo de credenciales
CRED_FILE=$(ls ~/.cloudflared/*.json 2>/dev/null | head -1)
if [[ -z "$CRED_FILE" ]]; then
    error "No se encontró archivo de credenciales en ~/.cloudflared/"
    error "Ejecuta primero: cloudflared tunnel login"
    exit 1
fi

CRED_FILENAME=$(basename "$CRED_FILE")

# Crear config.yml
CONFIG_FILE=~/.cloudflared/config.yml
cat > "$CONFIG_FILE" <<EOF
# FlotaControl — Cloudflare Tunnel Config
# Generado: $(date)
tunnel: $TUNNEL_NAME
credentials-file: ~/.cloudflared/$CRED_FILENAME

ingress:
  - hostname: $TUNNEL_HOSTNAME
    service: http://localhost:8080
  - service: http_status:404
EOF

success "Configuración generada: $CONFIG_FILE"
echo -e "${CYAN}Contenido:${NC}"
cat "$CONFIG_FILE"

# ─ Paso 6: Configurar DNS ─
echo ""
echo -e "${BOLD}Paso 6: Configurar DNS${NC}"
info "Ejecutando: cloudflared tunnel route dns $TUNNEL_NAME $TUNNEL_HOSTNAME"

if cloudflared tunnel route dns "$TUNNEL_NAME" "$TUNNEL_HOSTNAME"; then
    success "DNS configurado"
else
    warn "Podría haber un error configurando DNS, pero continuamos..."
fi

# ─ Paso 7: Instalar como servicio systemd ─
echo ""
echo -e "${BOLD}Paso 7: Instalar como servicio systemd${NC}"

if confirm "¿Instalar cloudflared como servicio que arranque automáticamente?"; then
    sudo cloudflared service install
    sudo systemctl start cloudflared
    sudo systemctl enable cloudflared
    success "Servicio instalado y iniciado"

    # Verificar estado
    sleep 2
    if sudo systemctl is-active --quiet cloudflared; then
        success "Servicio activo ✓"
    else
        error "El servicio no está activo. Revisa: sudo journalctl -u cloudflared -n 50"
    fi
else
    warn "No se instaló como servicio"
    echo "Puedes iniciar manualmente con: cloudflared tunnel run $TUNNEL_NAME"
fi

# ─ Verificar Docker está corriendo ─
echo ""
echo -e "${BOLD}Paso 8: Verificar Docker${NC}"
if docker compose ps | grep -q "Up"; then
    success "Docker está corriendo ✓"
else
    warn "Docker podría no estar corriendo"
    if confirm "¿Levantar Docker ahora?"; then
        docker compose up -d
        sleep 3
        success "Docker levantado"
    fi
fi

# ─ Resumen final ─
echo ""
echo -e "${BOLD}${GREEN}╔═══════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║${NC}  ✅ Configuración Completada           ${BOLD}${GREEN}║${NC}"
echo -e "${BOLD}${GREEN}╚═══════════════════════════════════════════╝${NC}"
echo ""
echo -e "Accede a tu aplicación en:"
echo -e "  ${BOLD}https://${TUNNEL_HOSTNAME}${NC}"
echo ""
echo "Comandos útiles:"
echo -e "  Ver status:   ${CYAN}sudo systemctl status cloudflared${NC}"
echo -e "  Ver logs:     ${CYAN}sudo journalctl -u cloudflared -f${NC}"
echo -e "  Listar tunnel: ${CYAN}cloudflared tunnel list${NC}"
echo -e "  Reiniciar:    ${CYAN}sudo systemctl restart cloudflared${NC}"
echo ""
echo "Si algo falla, revisa:"
echo -e "  1. Tunnel status en Cloudflare Dashboard"
echo -e "  2. Docker corriendo: ${CYAN}docker compose ps${NC}"
echo -e "  3. Logs del tunnel: ${CYAN}sudo journalctl -u cloudflared -n 50${NC}"
echo ""
success "¡Listo!"
