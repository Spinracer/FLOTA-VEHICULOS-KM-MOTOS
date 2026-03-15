# Guía de Despliegue — FlotaControl

## Instalación Rápida (Script Interactivo)

La forma más rápida de desplegar es usar el script interactivo que te pide todos los datos:

```bash
# En el servidor Ubuntu:
sudo bash deploy.sh
```

El script configura automáticamente: dependencias, BD, Nginx, PHP-FPM, SSL, cron de purga y backups. Solo rellena lo que te pida.

> **Nota:** Si tienes discos ya montados con datos, el script **NO** formatea nada. Solo crea los subdirectorios necesarios y un symlink.

---

## Instalación Manual (paso a paso)

Si prefieres configurar todo manualmente, sigue los pasos a continuación.

## Requisitos del Servidor

| Componente | Mínimo | Recomendado |
|---|---|---|
| OS | Ubuntu 22.04 LTS | Ubuntu 24.04 LTS |
| PHP | 8.1 | 8.3 |
| MySQL/MariaDB | 8.0 / 10.6 | 8.4 / 11.x |
| RAM | 2 GB | 4 GB |
| Disco principal | 50 GB | 100 GB |
| Disco datos (uploads) | — | 500 GB |
| Nginx o Apache | Cualquiera | Nginx |

---

## 1. Instalación de Dependencias

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-gd php8.3-zip mariadb-server git certbot python3-certbot-nginx
```

Si PHP 8.3 no está disponible, agregar el PPA:
```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-gd php8.3-zip
```

---

## 2. Configurar Base de Datos

```bash
sudo mysql -u root
```

```sql
CREATE DATABASE flotacontrol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'flotacontrol'@'localhost' IDENTIFIED BY 'TU_PASSWORD_SEGURO';
GRANT ALL PRIVILEGES ON flotacontrol.* TO 'flotacontrol'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 3. Clonar el Proyecto

```bash
sudo mkdir -p /var/www/flotacontrol
cd /var/www/flotacontrol
sudo git clone https://github.com/TU_USUARIO/FLOTA-VEHICULOS-KM-MOTOS.git .
sudo chown -R www-data:www-data /var/www/flotacontrol
```

---

## 4. Configurar Almacenamiento

### Opción A: Disco único (uploads en la misma partición)
```bash
sudo mkdir -p /var/www/flotacontrol/uploads
sudo chown -R www-data:www-data /var/www/flotacontrol/uploads
sudo chmod 755 /var/www/flotacontrol/uploads
```

### Opción B: Disco secundario de 500 GB (recomendado)

1. Identificar el disco:
```bash
lsblk
# Ejemplo: /dev/sdb es el disco de 500GB
```

2. Crear partición y formato:
```bash
sudo fdisk /dev/sdb
# n → p → 1 → Enter → Enter → w
sudo mkfs.ext4 /dev/sdb1
```

3. Montar el disco:
```bash
sudo mkdir -p /mnt/data
sudo mount /dev/sdb1 /mnt/data
```

4. Hacer permanente (agregar a fstab):
```bash
# Obtener UUID
sudo blkid /dev/sdb1
# Agregar al fstab:
echo "UUID=TU_UUID /mnt/data ext4 defaults,noatime 0 2" | sudo tee -a /etc/fstab
```

5. Crear directorio de uploads:
```bash
sudo mkdir -p /mnt/data/flotacontrol/uploads
sudo chown -R www-data:www-data /mnt/data/flotacontrol
sudo chmod 755 /mnt/data/flotacontrol/uploads
```

6. Crear symlink:
```bash
# Eliminar el directorio uploads del proyecto (solo el directorio vacío)
sudo rm -rf /var/www/flotacontrol/uploads
# Crear symlink al disco de datos
sudo ln -s /mnt/data/flotacontrol/uploads /var/www/flotacontrol/uploads
sudo chown -h www-data:www-data /var/www/flotacontrol/uploads
```

---

## 5. Archivo de Configuración (.env)

```bash
sudo mkdir -p /etc/flotacontrol
sudo cp /var/www/flotacontrol/deploy.env.example /var/www/flotacontrol/.env
sudo nano /var/www/flotacontrol/.env
```

Editar con los valores reales:
```
DB_HOST=127.0.0.1
DB_USER=flotacontrol
DB_PASS=TU_PASSWORD_SEGURO
DB_NAME=flotacontrol
APP_DEBUG=false
```

Proteger el archivo:
```bash
sudo chown www-data:www-data /var/www/flotacontrol/.env
sudo chmod 600 /var/www/flotacontrol/.env
```

---

## 6. Configurar PHP-FPM

Editar `/etc/php/8.3/fpm/pool.d/www.conf`:
```ini
[www]
user = www-data
group = www-data
listen = /run/php/php8.3-fpm.sock
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
```

Editar `/etc/php/8.3/fpm/php.ini`:
```ini
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_execution_time = 60
date.timezone = America/Tegucigalpa
```

Reiniciar:
```bash
sudo systemctl restart php8.3-fpm
```

---

## 7. Configurar Nginx

Crear `/etc/nginx/sites-available/flotacontrol`:

```nginx
server {
    listen 80;
    server_name tu-dominio.com;
    root /var/www/flotacontrol;
    index index.php;

    client_max_body_size 25M;

    # Bloquear acceso a archivos sensibles
    location ~ /\.env { deny all; return 404; }
    location ~ /\.git { deny all; return 404; }
    location ^~ /includes/ { deny all; return 404; }
    location ^~ /modules/ { deny all; return 404; }
    location ^~ /tests/ { deny all; return 404; }

    # Archivos estáticos
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # PHP
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 60;
    }

    # Denegar acceso a archivos ocultos
    location ~ /\. { deny all; }
}
```

Habilitar y probar:
```bash
sudo ln -s /etc/nginx/sites-available/flotacontrol /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart nginx
```

---

## 8. SSL con Let's Encrypt

```bash
sudo certbot --nginx -d tu-dominio.com
sudo systemctl enable certbot.timer
```

---

## 9. Ejecutar Instalador

Abrir en el navegador:
```
https://tu-dominio.com/install.php
```

El instalador creará todas las tablas y usuarios iniciales.
**IMPORTANTE:** Anotar las credenciales que se muestran al finalizar.

Después de instalar, el archivo `.installed.lock` se crea automáticamente para prevenir reinstalaciones.

---

## 10. Purga Automática de Órdenes de Compra (6 meses)

Crear cron job:
```bash
sudo crontab -u www-data -e
```

Agregar:
```cron
# Purgar órdenes de compra completadas/canceladas mayores a 6 meses (cada domingo 3AM)
0 3 * * 0 mysql -u flotacontrol -p'TU_PASSWORD' flotacontrol -e "DELETE FROM ordenes_compra WHERE estado IN ('Completada','Cancelada') AND updated_at < DATE_SUB(NOW(), INTERVAL 6 MONTH) AND deleted_at IS NOT NULL;"

# Limpiar adjuntos huérfanos de órdenes purgadas (cada domingo 3:30AM)
0 3 30 * * find /mnt/data/flotacontrol/uploads/oc_cotizacion -type f -mtime +180 -delete 2>/dev/null; find /mnt/data/flotacontrol/uploads/oc_factura -type f -mtime +180 -delete 2>/dev/null
```

---

## 11. Backups

### Backup de base de datos (diario)

```bash
sudo mkdir -p /mnt/data/backups
```

Crear `/etc/cron.daily/flotacontrol-backup`:
```bash
#!/bin/bash
BACKUP_DIR="/mnt/data/backups"
DATE=$(date +%Y%m%d_%H%M)
mysqldump -u flotacontrol -p'TU_PASSWORD' flotacontrol | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"
# Mantener solo últimos 30 backups
ls -t "$BACKUP_DIR"/db_*.sql.gz | tail -n +31 | xargs -r rm
```

```bash
sudo chmod +x /etc/cron.daily/flotacontrol-backup
```

### Backup de uploads
```bash
# Rsync a disco externo o servidor remoto
rsync -avz /mnt/data/flotacontrol/uploads/ /ruta/backup/uploads/
```

---

## 12. Actualizar el Sistema

```bash
cd /var/www/flotacontrol
sudo -u www-data git pull origin main
# Si hay migraciones nuevas, eliminar el lock y ejecutar install:
sudo rm -f .installed.lock
curl -s http://localhost/install.php > /dev/null
# Reiniciar PHP-FPM
sudo systemctl restart php8.3-fpm
```

---

## 13. Monitoreo

### Verificar servicios
```bash
sudo systemctl status nginx php8.3-fpm mariadb
```

### Logs
```bash
# Nginx
tail -f /var/log/nginx/error.log

# PHP-FPM
tail -f /var/log/php8.3-fpm.log

# MySQL
tail -f /var/log/mysql/error.log
```

### Espacio en disco
```bash
df -h /mnt/data
du -sh /mnt/data/flotacontrol/uploads/
```

---

## Estructura de Directorios en Producción

```
/var/www/flotacontrol/          ← Código fuente (disco principal)
├── .env                        ← Configuración (chmod 600)
├── .installed.lock             ← Previene reinstalación
├── uploads → /mnt/data/...    ← Symlink a disco de datos
├── api/
├── assets/
├── includes/
├── modules/
└── ...

/mnt/data/                      ← Disco de 500GB para datos
├── flotacontrol/
│   └── uploads/
│       ├── vehiculos/
│       ├── incidentes/
│       ├── mantenimientos/
│       ├── combustible/
│       ├── operadores/
│       ├── oc_cotizacion/
│       ├── oc_factura/
│       └── vehiculo_documentos/
└── backups/
    └── db_YYYYMMDD_HHMM.sql.gz
```
