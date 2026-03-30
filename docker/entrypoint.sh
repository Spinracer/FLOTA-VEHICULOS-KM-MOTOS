#!/bin/bash
set -e

# ─── Wait for MySQL ───
echo "[entrypoint] Esperando MySQL en ${DB_HOST:-mysql}:${DB_PORT:-3306}..."

MAX_TRIES=30
COUNT=0
until php -r "
    try {
        new PDO('mysql:host=${DB_HOST:-mysql};port=${DB_PORT:-3306}', '${DB_USER}', '${DB_PASS}');
        exit(0);
    } catch (Exception \$e) {
        exit(1);
    }
" 2>/dev/null; do
    COUNT=$((COUNT + 1))
    if [ $COUNT -ge $MAX_TRIES ]; then
        echo "[entrypoint] ERROR: MySQL no disponible tras ${MAX_TRIES} intentos. Saliendo."
        exit 1
    fi
    echo "[entrypoint] MySQL no listo (intento ${COUNT}/${MAX_TRIES})... esperando 2s"
    sleep 2
done

echo "[entrypoint] MySQL listo."

# ─── Run install.php if first run ───
LOCK_FILE="/var/www/html/.installed.lock"

if [ ! -f "$LOCK_FILE" ]; then
    echo "[entrypoint] Primera ejecución. Ejecutando install.php..."

    # Create upload subdirectories
    for subdir in vehiculos incidentes mantenimientos combustible operadores oc_cotizacion oc_factura vehiculo_documentos importaciones; do
        mkdir -p "/var/www/html/uploads/$subdir"
    done
    chown -R www-data:www-data /var/www/html/uploads

    cd /var/www/html
    php install.php 2>&1 | tee /tmp/install_output.log

    if [ -f "$LOCK_FILE" ]; then
        echo "[entrypoint] Instalación completada."
    else
        echo "[entrypoint] ADVERTENCIA: install.php se ejecutó pero .installed.lock no se creó. Revisa errores."
    fi
else
    echo "[entrypoint] Sistema ya instalado (.installed.lock existe). Saltando install.php."
fi

# ─── Run migrations (safe, idempotent) ───
echo "[entrypoint] Ejecutando migraciones seguras..."
cd /var/www/html
for migration in scripts/migrate_*.php; do
    if [ -f "$migration" ]; then
        echo "[entrypoint] Ejecutando $migration..."
        php "$migration" 2>&1 || echo "[entrypoint] ADVERTENCIA: $migration tuvo errores"
    fi
done

# ─── Fix permissions ───
chown -R www-data:www-data /var/www/html/uploads 2>/dev/null || true

# ─── Start PHP-FPM ───
exec "$@"
