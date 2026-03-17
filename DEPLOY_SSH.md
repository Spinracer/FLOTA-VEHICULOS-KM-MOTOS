# Despliegue Automatizado por SSH — FlotaControl

## 🚀 Resumen Rápido

Este documento es para desplegar **FlotaControl en tu servidor propio** usando SSH y el script interactivo `deploy.sh`.

**Lo que hace el script automáticamente:**
- ✅ Instala todas las dependencias (PHP 8.3, Nginx, MariaDB, etc.)
- ✅ Clona el repositorio
- ✅ Configura la base de datos
- ✅ Instala y configura Nginx
- ✅ Configura PHP-FPM
- ✅ Crea permisos de seguridad
- ✅ Genera archivo `.env`
- ✅ Compila el instalador
- ✅ Configura SSL (si usas dominio)

---

## 📋 Requisitos Previos

**En tu servidor:**
- [ ] Ubuntu 22.04 LTS o superior
- [ ] Acceso SSH como usuario con permisos `sudo`
- [ ] Conexión a Internet
- [ ] Puertos disponibles: `80`, `443` (si usas dominio)
- [ ] Opcional: Disco secundario para uploads (500 GB recomendado)

**Datos que necesitarás a mano:**
1. **Dominio** (si lo usas): `flota.miempresa.com`
2. **Contraseña de BD**: Password seguro
3. **URL del repositorio**: `https://github.com/TuUsuario/FLOTA-VEHICULOS-KM-MOTOS.git`
4. **Rama**: `main` (por defecto)

---

## 🔧 Paso 1: Conectar por SSH

Desde tu PC local:

```bash
ssh usuario@tu_servidor_ip
# Ejemplo: ssh ubuntu@192.168.1.100
```

O usando el archivo de configuración SSH:

```bash
ssh flota-servidor
```

---

## 📥 Paso 2: Clonar el Repositorio

Una vez dentro del servidor:

```bash
# Navegar a directorio temporal
cd /tmp

# Clonar el repositorio
git clone https://github.com/TuUsuario/FLOTA-VEHICULOS-KM-MOTOS.git
cd FLOTA-VEHICULOS-KM-MOTOS

# Verificar que el script está presente
ls -la deploy.sh
```

---

## ⚙️ Paso 3: Ejecutar el Script Interactivo (OPCIÓN RECOMENDADA)

```bash
sudo bash deploy.sh
```

El script hará preguntas. **A continuación, las respuestas sugeridas:**

### 📍 Paso 1/9: Modo de Despliegue

```
Selecciona modo [1]:
```

**Opciones:**
- **1** = Local sin dominio (acceso por IP: `http://192.168.1.100`)
- **2** = Con dominio propio (ej: `flota.miempresa.com` + SSL automático)
- **3** = Cloudflare Tunnel (acceso público sin abrir puertos)
- **4** = Docker (para dev/testing)

**Recomendación:** Elige **2** si tienes dominio, **1** si solo tienes IP.

---

### 📍 Paso 2/9: Dominio (si elegiste opción 2)

```
Dominio del servidor (ej: flota.miempresa.com): flota.miempresa.com
```

> **Nota:** Debe estar apuntando a tu IP en el DNS antes de ejecutar el script.

---

### 📍 Paso 3/9: Directorio de Instalación

```
Directorio de instalación [/var/www/flotacontrol]:
```

Presiona **Enter** para aceptar el default.

---

### 📍 Paso 4/9: Repositorio Git

```
URL del repositorio Git [https://github.com/Spinracer/FLOTA-VEHICULOS-KM-MOTOS.git]: https://github.com/TuUsuario/FLOTA-VEHICULOS-KM-MOTOS.git
```

Reemplaza con tu fork/repositorio.

---

### 📍 Paso 5/9: Rama

```
Rama a desplegar [main]:
```

Presiona **Enter** para aceptar `main`.

---

### 📍 Paso 6/9: Base de Datos

```
Nombre de la base de datos [flotacontrol]:
Usuario de la base de datos [flotacontrol]:
Contraseña del usuario de BD: ••••••••
```

**Recomendación:**
- Nombre BD: `flotacontrol` (default está bien)
- Usuario: `flotacontrol` (default está bien)
- **Contraseña:** Algo fuerte como `MySecurePass2024@XyZ`

---

### 📍 Paso 7/9: Almacenamiento

```
Opción [1]:
  1) Disco único (uploads dentro de /var/www/flotacontrol)
  2) Disco secundario ya montado (symlink, SIN formatear)
```

**Si tienes disco de 500 GB montado en `/mnt/data`:**
```
Opción [1]: 2
Punto de montaje del disco de datos (ej: /mnt/data) [/mnt/data]:
```

**Si NO tienes disco secundario, presiona Enter para opción 1.**

---

### 📍 Paso 8/9: Configuración de PHP

```
Versión de PHP a usar [8.3]:
upload_max_filesize [20M]:
post_max_size [25M]:
memory_limit [256M]:
Zona horaria [America/Tegucigalpa]:
```

**Presiona Enter** en cada una para aceptar los defaults (están optimizados).

Si necesitas otra zona horaria (ej: `America/Mexico_City`, `Europe/Madrid`), cámbiala.

---

### 📍 Resumen de Configuración

El script mostrará un resumen de lo que va a hacer:

```
── Resumen de configuración ──
  Modo:           Dominio + SSL
  Dominio/Host:   flota.miempresa.com
  Directorio:     /var/www/flotacontrol
  Repositorio:    https://github.com/TuUsuario/FLOTA-VEHICULOS-KM-MOTOS.git
  Rama:           main
  BD:             flotacontrol (user: flotacontrol)
  Uploads:        /mnt/data/flotacontrol/uploads
  PHP:            8.3
  Timezone:       America/Tegucigalpa

¿Proceder con la instalación? [s/N]: s
```

Escribe **`s`** para confirmar.

---

## ⏳ Paso 4: Esperar a que Termine

El script tardará **5-15 minutos** dependiendo de:
- Velocidad de conexión
- Velocidad del servidor
- Si instala certificado SSL

**Lo que verás en pantalla:**
- `[INFO]` = Información
- `[OK]` = Paso completado exitosamente
- `[!]` = Advertencia
- `[ERR]` = Error (si pasa, revisa la sección Troubleshooting)

---

## ✅ Paso 5: Verificar la Instalación

Una vez terminado, el script dirá:

```
[OK]   ✅ ¡Despliegue completado exitosamente!
```

Luego **en tu navegador**, abre:

**Si usaste modo 2 (con dominio):**
```
https://flota.miempresa.com
```

**Si usaste modo 1 (por IP):**
```
http://192.168.1.100
```

---

## 🔐 Paso 6: Completar la Instalación Web

1. Abre la URL anterior
2. Verás la pantalla **"Installer — FlotaControl"**
3. Sigue los pasos del instalador:
   - Verificar conectividad de BD
   - Crear tablas
   - Crear usuario administrador
4. **Guarda las credenciales que te muestre** (email y password)

---

## 🎯 Acceso Final

- **URL:** `https://flota.miempresa.com` (o tu IP si no usas dominio)
- **Email:** El que creaste en el instalador
- **Contraseña:** La que creaste en el instalador

---

## 📊 Post-Despliegue: Verificaciones

### 1. Verificar Servicios

```bash
sudo systemctl status nginx
sudo systemctl status php8.3-fpm
sudo systemctl status mysql
```

Si alguno está rojo, ejecuta:

```bash
sudo systemctl restart nginx php8.3-fpm mysql
```

### 2. Ver Logs si Hay Problemas

```bash
# Nginx
sudo tail -50 /var/log/nginx/error.log

# PHP
sudo tail -50 /var/log/php8.3-fpm.log

# MySQL
sudo tail -50 /var/log/mysql/error.log
```

### 3. Verificar Permisos

```bash
sudo ls -la /var/www/flotacontrol/.env
sudo ls -la /var/www/flotacontrol/.installed.lock
```

Deben ser propiedad de `www-data:www-data`.

---

## 🔄 Opción 2: Despliegue Manual (Sin Script)

Si prefieres hacerlo paso a paso, sigue la **sección "Instalación Manual"** en `DEPLOY_GUIDE.md`. Pero **recomendamos el script** porque automatiza todo.

---

## 🆘 Troubleshooting

### ❌ "ERROR: El script debe ejecutarse como root"

```bash
# Solución: Ejecuta con sudo
sudo bash deploy.sh
```

---

### ❌ "ERROR: La contraseña de BD no puede estar vacía"

```bash
# Solución: Asegúrate de escribir una contraseña segura
# Ejemplo: MyPass2024@XyZ
```

---

### ❌ "ERROR: El punto de montaje /mnt/data no existe"

Significa que tu disco secundario no está montado. Dos opciones:

**Opción A:** Montarlo ahora

```bash
# Identificar el disco
sudo lsblk

# Si es /dev/sdb1, montarlo
sudo mkdir -p /mnt/data
sudo mount /dev/sdb1 /mnt/data

# Ejecutar el script de nuevo
sudo bash deploy.sh
```

**Opción B:** Usar almacenamiento en disco único (opción 1 en el script)

---

### ❌ "ERROR: No se puede conectar a la BD"

Verifica credenciales:

```bash
# Conectar directamente a MySQL
sudo mysql -u root

# Dentro de MySQL, ejecuta:
SHOW DATABASES;
SELECT User FROM mysql.user;

# Si no ves a 'flotacontrol', ejecuta:
CREATE USER 'flotacontrol'@'localhost' IDENTIFIED BY 'TuContraseña';
GRANT ALL PRIVILEGES ON flotacontrol.* TO 'flotacontrol'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

### ❌ "ERROR 403: Acceso denegado" en el navegador

Verifica permisos:

```bash
sudo chown -R www-data:www-data /var/www/flotacontrol
sudo chmod -R 755 /var/www/flotacontrol
sudo chmod 600 /var/www/flotacontrol/.env
```

Reinicia Nginx:

```bash
sudo systemctl restart nginx
```

---

### ❌ "ERROR: Certificado SSL no se instaló"

Verifica que:

1. El dominio está apuntando a tu IP (DNS actualizado)
2. Los puertos 80 y 443 están abiertos
3. El firewall permite el acceso

```bash
# Ver si está escuchando
sudo netstat -tlnp | grep nginx

# Renovar SSL manualmente
sudo certbot renew --force-renewal
```

---

### ❌ "El instalador no aparece en `https://flota.miempresa.com/install.php`"

Verifica:

```bash
# Ver si existe el archivo install.php
ls -la /var/www/flotacontrol/install.php

# Ver permisos
sudo chown www-data:www-data /var/www/flotacontrol/install.php
sudo chmod 644 /var/www/flotacontrol/install.php

# Reiniciar PHP-FPM
sudo systemctl restart php8.3-fpm
```

---

## 🔐 Configuración de Seguridad Post-Despliegue

Después de desplegar, ejecuta estas recomendaciones:

### 1. Cambiar Credenciales de BD

```bash
sudo mysql -u root
ALTER USER 'flotacontrol'@'localhost' IDENTIFIED BY 'NUEVA_CONTRASEÑA_SEGURA';
FLUSH PRIVILEGES;
EXIT;
```

Luego actualiza `.env`:

```bash
sudo nano /var/www/flotacontrol/.env
# Edita: DB_PASS=NUEVA_CONTRASEÑA_SEGURA
```

---

### 2. Deshabilitar install.php

```bash
sudo rm /var/www/flotacontrol/install.php
```

Esto evita que alguien vuelva a instalar la app.

---

### 3. Configurar Firewall

```bash
# Si usas UFW
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
```

---

### 4. Crear Copia de Seguridad

```bash
# Backup de BD
sudo mkdir -p /backups
sudo mysqldump -u flotacontrol -p flotacontrol > /backups/bd_inicial_$(date +%Y%m%d).sql

# Backup de archivo .env (protegido)
sudo cp /var/www/flotacontrol/.env /backups/.env.backup
sudo chmod 600 /backups/.env.backup
```

---

### 5. Configurar Cron de Backups (Automático)

```bash
sudo crontab -u www-data -e
```

Agrega:

```cron
# Backup diario de BD (cada día a las 2 AM)
0 2 * * * mysqldump -u flotacontrol -p'TU_PASSWORD' flotacontrol | gzip > /backups/db_$(date +\%Y\%m\%d_\%H\%M).sql.gz

# Limpiar backups más viejos de 30 días
0 3 * * * find /backups -name "db_*.sql.gz" -mtime +30 -delete
```

---

## 📈 Mantenimiento Rutinario

### Actualizar el Sistema

```bash
cd /var/www/flotacontrol
sudo -u www-data git pull origin main
sudo systemctl restart php8.3-fpm
```

### Monitorear Espacio en Disco

```bash
df -h /var/www/flotacontrol
df -h /mnt/data  # Si tienes disco secundario
```

### Ver Logs en Tiempo Real

```bash
# Nginx
sudo tail -f /var/log/nginx/access.log

# Errores
sudo tail -f /var/log/nginx/error.log
```

---

## 🎓 Notas Importantes

1. **El script es idempotente:** Puedes ejecutarlo varias veces sin problemas
2. **Datos NO se borran:** Si existe BD o directorio, solo se configura
3. **SSL se renueva automáticamente:** Certbot está configurado para renovación automática
4. **Backups:** Configúralos ASAP (ver sección anterior)
5. **SSH Key:** Usa SSH key en lugar de contraseña para mayor seguridad

```bash
# Copiar tu clave SSH
ssh-copy-id -i ~/.ssh/id_rsa.pub usuario@tu_servidor
```

---

## 📞 Soporte

Si encuentras problemas durante el despliegue:

1. Revisa la sección **Troubleshooting** anterior
2. Consulta los **logs** con los comandos indicados
3. Verifica el archivo original `DEPLOY_GUIDE.md` para detalles técnicos

---

**¡Listo! Tu FlotaControl está desplegado. 🚀**
