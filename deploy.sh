#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
# FlotaControl — Script Interactivo de Despliegue
# Compatible con: Ubuntu 22.04 / 24.04 LTS
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

# ── Colores ──
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

banner() {
    echo ""
    echo -e "${CYAN}╔═══════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║${NC}  ${BOLD}FlotaControl — Despliegue Interactivo${NC}            ${CYAN}║${NC}"
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

ask_password() {
    local prompt="$1" var
    read -rsp "$(echo -e "${BOLD}$prompt${NC}: ")" var
    echo ""
    echo "$var"
}

confirm() {
    local prompt="$1"
    read -rp "$(echo -e "${YELLOW}$prompt [s/N]:${NC} ")" yn
    [[ "$yn" =~ ^[sS]$ ]]
}

# ── Verificar root ──
if [[ $EUID -ne 0 ]]; then
    error "Este script debe ejecutarse como root: sudo bash deploy.sh"
    exit 1
fi

banner

# ═══════════════════════════════════════════════════════════════════
# PASO 1: Recopilar configuración
# ═══════════════════════════════════════════════════════════════════
echo -e "${BOLD}── Paso 1/8: Configuración General ──${NC}"
echo ""

DOMAIN=$(ask "Dominio del servidor (ej: flota.miempresa.com)" "")
while [[ -z "$DOMAIN" ]]; do
    error "El dominio es obligatorio"
    DOMAIN=$(ask "Dominio del servidor" "")
done

INSTALL_DIR=$(ask "Directorio de instalación" "/var/www/flotacontrol")
GIT_REPO=$(ask "URL del repositorio Git" "https://github.com/Spinracer/FLOTA-VEHICULOS-KM-MOTOS.git")
GIT_BRANCH=$(ask "Rama a desplegar" "main")

echo ""
echo -e "${BOLD}── Base de datos ──${NC}"
DB_NAME=$(ask "Nombre de la base de datos" "flotacontrol")
DB_USER=$(ask "Usuario de la base de datos" "flotacontrol")
DB_PASS=$(ask_password "Contraseña del usuario de BD (se creará)")
while [[ -z "$DB_PASS" ]]; do
    error "La contraseña no puede estar vacía"
    DB_PASS=$(ask_password "Contraseña del usuario de BD")
done

echo ""
echo -e "${BOLD}── Almacenamiento de Uploads ──${NC}"
echo -e "  ${CYAN}1)${NC} Disco único (uploads dentro de ${INSTALL_DIR})"
echo -e "  ${CYAN}2)${NC} Disco secundario montado (symlink)"

STORAGE_OPT=$(ask "Opción" "1")
UPLOAD_PATH="${INSTALL_DIR}/uploads"
MOUNT_POINT=""

if [[ "$STORAGE_OPT" == "2" ]]; then
    MOUNT_POINT=$(ask "Punto de montaje del disco de datos (ej: /mnt/data)" "/mnt/data")
    if [[ ! -d "$MOUNT_POINT" ]]; then
        error "El punto de montaje $MOUNT_POINT no existe. Asegúrate de que el disco esté montado."
        if confirm "¿Continuar de todas formas?"; then
            warn "Se creará el directorio cuando sea necesario"
        else
            exit 1
        fi
    fi
    UPLOAD_PATH="${MOUNT_POINT}/flotacontrol/uploads"
fi

echo ""
echo -e "${BOLD}── PHP ──${NC}"
PHP_VER=$(ask "Versión de PHP a usar" "8.3")
PHP_UPLOAD_MAX=$(ask "upload_max_filesize" "20M")
PHP_POST_MAX=$(ask "post_max_size" "25M")
PHP_MEMORY=$(ask "memory_limit" "256M")
TIMEZONE=$(ask "Zona horaria" "America/Tegucigalpa")

echo ""
echo -e "${BOLD}── Resumen de configuración ──${NC}"
echo -e "  Dominio:        ${GREEN}${DOMAIN}${NC}"
echo -e "  Directorio:     ${GREEN}${INSTALL_DIR}${NC}"
echo -e "  Repositorio:    ${GREEN}${GIT_REPO}${NC}"
echo -e "  Rama:           ${GREEN}${GIT_BRANCH}${NC}"
echo -e "  BD:             ${GREEN}${DB_NAME}${NC} (user: ${DB_USER})"
echo -e "  Uploads:        ${GREEN}${UPLOAD_PATH}${NC}"
echo -e "  PHP:            ${GREEN}${PHP_VER}${NC}"
echo -e "  Timezone:       ${GREEN}${TIMEZONE}${NC}"
echo ""

if ! confirm "¿Proceder con la instalación?"; then
    info "Cancelado."
    exit 0
fi

# ═══════════════════════════════════════════════════════════════════
# PASO 2: Instalar dependencias
# ═══════════════════════════════════════════════════════════════════
echo ""
echo -e "${BOLD}── Paso 2/8: Instalando dependencias ──${NC}"

apt-get update -qq
apt-get install -y -qq nginx git curl certbot python3-certbot-nginx mariadb-server > /dev/null 2>&1
success "nginx, git, mariadb-server, certbot instalados"

# PHP — intentar instalar directamente, si falla agregar PPA
if ! apt-get install -y -qq "php${PHP_VER}-fpm" "php${PHP_VER}-mysql" "php${PHP_VER}-mbstring" "php${PHP_VER}-xml" "php${PHP_VER}-curl" "php${PHP_VER}-gd" "php${PHP_VER}-zip" > /dev/null 2>&1; then
    warn "PHP ${PHP_VER} no disponible, agregando PPA ondrej/php..."
    add-apt-repository -y ppa:ondrej/php > /dev/null 2>&1
    apt-get update -qq
    apt-get install -y -qq "php${PHP_VER}-fpm" "php${PHP_VER}-mysql" "php${PHP_VER}-mbstring" "php${PHP_VER}-xml" "php${PHP_VER}-curl" "php${PHP_VER}-gd" "php${PHP_VER}-zip" > /dev/null 2>&1
fi
success "PHP ${PHP_VER} + extensiones instalados"

# ═══════════════════════════════════════════════════════════════════
# PASO 3: Base de datos
# ═══════════════════════════════════════════════════════════════════
echo ""
echo -e "${BOLD}── Paso 3/8: Configurando base de datos ──${NC}"

# Usar heredoc con password escaping seguro
mysql -u root <<EOSQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOSQL
success "Base de datos '${DB_NAME}' y usuario '${DB_USER}' creados"

# ═══════════════════════════════════════════════════════════════════
# PASO 4: Clonar proyecto
# ═══════════════════════════════════════════════════════════════════
echo ""
echo -e "${BOLD}── Paso 4/8: Clonando proyecto ──${NC}"

if [[ -d "${INSTALL_DIR}/.git" ]]; then
    warn "El directorio ya contiene un repositorio Git. Actualizando..."
    cd "${INSTALL_DIR}"
    git fetch origin
    git reset --hard "origin/${GIT_BRANCH}"
else
    mkdir -p "${INSTALL_DIR}"
    git clone --branch "${GIT_BRANCH}" "${GIT_REPO}" "${INSTALL_DIR}"
fi
chown -R www-data:www-data "${INSTALL_DIR}"
success "Código clonado en ${INSTALL_DIR}"

# ═══════════════════════════════════════════════════════════════════
# PASO 5: Configurar almacenamiento
# ═══════════════════════════════════════════════════════════════════
echo ""
echo -e "${BOLD}── Paso 5/8: Configurando almacenamiento ──${NC}"

if [[ "$STORAGE_OPT" == "2" ]]; then
    # Disco secundario — NO formatear, solo crear directorios
    mkdir -p "${UPLOAD_PATH}"
    chown -R www-data:www-data "${MOUNT_POINT}/flotacontrol"
    chmod 755 "${UPLOAD_PATH}"
    # Crear symlink (si no existe ya)
    if [[ -L "${INSTALL_DIR}/uploads" ]]; then
        info "Symlink ya existe, se mantiene"
    elif [[ -d "${INSTALL_DIR}/uploads" ]]; then
        # Mover contenido existente al disco de datos
        if [[ -n "$(ls -A "${INSTALL_DIR}/uploads" 2>/dev/null)" ]]; then
            info "Moviendo archivos existentes a disco de datos..."
            cp -a "${INSTALL_DIR}/uploads/"* "${UPLOAD_PATH}/" 2>/dev/null || true
        fi
        rm -rf "${INSTALL_DIR}/uploads"
        ln -s "${UPLOAD_PATH}" "${INSTALL_DIR}/uploads"
        chown -h www-data:www-data "${INSTALL_DIR}/uploads"
    else
        ln -s "${UPLOAD_PATH}" "${INSTALL_DIR}/uploads"
        chown -h www-data:www-data "${INSTALL_DIR}/uploads"
    fi
    success "Symlink: ${INSTALL_DIR}/uploads → ${UPLOAD_PATH}"
else
    mkdir -p "${INSTALL_DIR}/uploads"
    chown -R www-data:www-data "${INSTALL_DIR}/uploads"
    chmod 755 "${INSTALL_DIR}/uploads"
    success "Directorio de uploads creado en ${INSTALL_DIR}/uploads"
fi

# Crear subdirectorios de entidades
for subdir in vehiculos incidentes mantenimientos combustible operadores oc_cotizacion oc_factura vehiculo_documentos; do
    mkdir -p "${UPLOAD_PATH}/${subdir}"
done
chown -R www-data:www-data "${UPLOAD_PATH}"
success "Subdirectorios de uploads creados"

# ═══════════════════════════════════════════════════════════════════
# PASO 6: Archivo .env
# ═══════════════════════════════════════════════════════════════════
echo ""
echo -e "${BOLD}── Paso 6/8: Generando .env ──${NC}"

ENV_FILE="${INSTALL_DIR}/.env"
cat > "${ENV_FILE}" <<ENVEOF
# FlotaControl — Generado por deploy.sh el $(date '+%Y-%m-%d %H:%M')
DB_HOST=127.0.0.1
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
DB_NAME=${DB_NAME}
APP_DEBUG=false
APP_NAME=FlotaControl
APP_URL=https://${DOMAIN}
UPLOAD_PATH=${UPLOAD_PATH}
OC_PURGE_DAYS=180
PHP_MAX_UPLOAD=${PHP_UPLOAD_MAX}
PHP_POST_MAX=${PHP_POST_MAX}
PHP_MEMORY_LIMIT=${PHP_MEMORY}
ENVEOF
chown www-data:www-data "${ENV_FILE}"
chmod 600 "${ENV_FILE}"
success ".env creado y protegido (chmod 600)"

# ═══════════════════════════════════════════════════════════════════
# PASO 7: Configurar PHP-FPM
# ═══════════════════════════════════════════════════════════════════
echo ""
echo -e "${BOLD}── Paso 7/8: Configurando PHP-FPM ──${NC}"

PHP_INI="/etc/php/${PHP_VER}/fpm/php.ini"
if [[ -f "$PHP_INI" ]]; then
    sed -i "s/^upload_max_filesize = .*/upload_max_filesize = ${PHP_UPLOAD_MAX}/" "$PHP_INI"
    sed -i "s/^post_max_size = .*/post_max_size = ${PHP_POST_MAX}/" "$PHP_INI"
    sed -i "s/^memory_limit = .*/memory_limit = ${PHP_MEMORY}/" "$PHP_INI"
    sed -i "s|^;*date.timezone = .*|date.timezone = ${TIMEZONE}|" "$PHP_INI"
    success "php.ini actualizado"
fi

systemctl restart "php${PHP_VER}-fpm"
success "PHP-FPM reiniciado"

# ═══════════════════════════════════════════════════════════════════
# PASO 8: Configurar Nginx
# ═══════════════════════════════════════════════════════════════════
echo ""
echo -e "${BOLD}── Paso 8/8: Configurando Nginx ──${NC}"

NGINX_CONF="/etc/nginx/sites-available/flotacontrol"
cat > "${NGINX_CONF}" <<NGINXEOF
server {
    listen 80;
    server_name ${DOMAIN};
    root ${INSTALL_DIR};
    index index.php;

    client_max_body_size ${PHP_POST_MAX};

    # Bloquear acceso a archivos sensibles
    location ~ /\.env { deny all; return 404; }
    location ~ /\.git { deny all; return 404; }
    location ^~ /includes/ { deny all; return 404; }
    location ^~ /modules/ { deny all; return 404; }
    location ^~ /tests/ { deny all; return 404; }
    location ~ /deploy\.sh { deny all; return 404; }

    # Archivos estáticos
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # PHP
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_read_timeout 60;
    }

    # Denegar acceso a archivos ocultos
    location ~ /\. { deny all; }
}
NGINXEOF

ln -sf "${NGINX_CONF}" /etc/nginx/sites-enabled/flotacontrol
rm -f /etc/nginx/sites-enabled/default

nginx -t 2>&1 && success "Configuración de Nginx válida" || { error "Nginx config test falló"; exit 1; }
systemctl restart nginx
success "Nginx reiniciado"

# ═══════════════════════════════════════════════════════════════════
# POST-INSTALACIÓN
# ═══════════════════════════════════════════════════════════════════
echo ""
echo -e "${BOLD}── Post-instalación ──${NC}"

# Configurar cron de purga
CRON_LINE="0 3 * * 0 mysql -u ${DB_USER} -p'${DB_PASS}' ${DB_NAME} -e \"DELETE FROM ordenes_compra WHERE estado IN ('Completada','Cancelada') AND updated_at < DATE_SUB(NOW(), INTERVAL 180 DAY) AND deleted_at IS NOT NULL;\" 2>/dev/null"
(crontab -u www-data -l 2>/dev/null | grep -v 'ordenes_compra'; echo "$CRON_LINE") | crontab -u www-data -
success "Cron de purga configurado (domingos 3AM)"

# Configurar backup diario
BACKUP_DIR="${MOUNT_POINT:-/var}/backups/flotacontrol"
mkdir -p "$BACKUP_DIR"
cat > /etc/cron.daily/flotacontrol-backup <<BAKEOF
#!/bin/bash
BACKUP_DIR="${BACKUP_DIR}"
DATE=\$(date +%Y%m%d_%H%M)
mysqldump -u ${DB_USER} -p'${DB_PASS}' ${DB_NAME} | gzip > "\$BACKUP_DIR/db_\$DATE.sql.gz"
ls -t "\$BACKUP_DIR"/db_*.sql.gz | tail -n +31 | xargs -r rm
BAKEOF
chmod +x /etc/cron.daily/flotacontrol-backup
success "Backup diario configurado en ${BACKUP_DIR}"

# SSL
echo ""
if confirm "¿Configurar SSL con Let's Encrypt ahora?"; then
    certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos --redirect -m "admin@${DOMAIN}" || warn "Certbot falló. Puedes ejecutarlo manualmente: certbot --nginx -d ${DOMAIN}"
    systemctl enable certbot.timer 2>/dev/null || true
    success "SSL configurado"
else
    warn "Puedes configurar SSL después: sudo certbot --nginx -d ${DOMAIN}"
fi

# Ejecutar instalador de FlotaControl
echo ""
info "Ejecutando instalador de FlotaControl..."
rm -f "${INSTALL_DIR}/.installed.lock"
cd "${INSTALL_DIR}"
sudo -u www-data php${PHP_VER} install.php 2>&1 | tail -5
success "Instalador ejecutado"

# ═══════════════════════════════════════════════════════════════════
# RESUMEN FINAL
# ═══════════════════════════════════════════════════════════════════
echo ""
echo -e "${CYAN}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║${NC}  ${GREEN}${BOLD}✅ FlotaControl instalado correctamente${NC}               ${CYAN}║${NC}"
echo -e "${CYAN}╠═══════════════════════════════════════════════════════════╣${NC}"
echo -e "${CYAN}║${NC}  URL:        ${BOLD}https://${DOMAIN}${NC}"
echo -e "${CYAN}║${NC}  Directorio: ${INSTALL_DIR}"
echo -e "${CYAN}║${NC}  Base datos: ${DB_NAME}"
echo -e "${CYAN}║${NC}  Uploads:    ${UPLOAD_PATH}"
echo -e "${CYAN}║${NC}  Backups:    ${BACKUP_DIR}"
echo -e "${CYAN}║${NC}"
echo -e "${CYAN}║${NC}  ${YELLOW}Anota las credenciales mostradas arriba por${NC}"
echo -e "${CYAN}║${NC}  ${YELLOW}el instalador (usuario y contraseña inicial).${NC}"
echo -e "${CYAN}║${NC}"
echo -e "${CYAN}║${NC}  Para actualizar:  cd ${INSTALL_DIR} && git pull"
echo -e "${CYAN}║${NC}  Logs Nginx:       tail -f /var/log/nginx/error.log"
echo -e "${CYAN}║${NC}  Logs PHP:         tail -f /var/log/php${PHP_VER}-fpm.log"
echo -e "${CYAN}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""
