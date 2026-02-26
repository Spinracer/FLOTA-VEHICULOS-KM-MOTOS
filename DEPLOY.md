# Guía de despliegue

## 1) Requisitos

- PHP 8.3 con extensiones: `pdo_mysql`, `session`, `mbstring`, `json`
- MySQL 8 o MariaDB compatible
- Servidor web Apache/Nginx (o servidor embebido PHP para pruebas)

## 2) Variables de entorno

1. Copia `.env.example` a `.env`
2. Ajusta credenciales:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=flotacontrol
DB_USER=root
DB_PASS=tu_password
```

## 3) Inicialización de base de datos

Ejecuta una vez:

```bash
php8.3 install.php
```

Este paso crea tablas base, catálogos semilla, auditoría y odómetro.

## 4) Levantar en local (desarrollo)

### Opción A: MySQL en Docker + PHP embebido

```bash
docker run -d --name flotacontrol-db \
  -e MYSQL_ROOT_PASSWORD=FlotaCtrl2024x \
  -e MYSQL_DATABASE=flotacontrol \
  -p 3306:3306 mysql:8

php8.3 -S 0.0.0.0:8000
```

App disponible en: `http://127.0.0.1:8000`

### Opción B: Servicios locales instalados

- Asegura MySQL escuchando en `DB_HOST:DB_PORT`
- Ejecuta `php8.3 -S 0.0.0.0:8000`

## 5) Producción (recomendaciones)

- No exponer `install.php` tras instalación (eliminar o bloquear)
- Mantener `.env` fuera de Git
- Forzar HTTPS
- Configurar backup periódico de DB
- Monitorear logs de aplicación y web server

## 6) Verificación rápida

- `GET /` debe responder 200
- Login debe redirigir a dashboard
- Menú de Sistema debe mostrar Catálogos para admin
- Menú principal debe mostrar Asignaciones
- En Proveedores debe existir campo de taller autorizado
- Usuarios admin permite crear rol Taller con proveedor asignado
- Operadores debe permitir abrir historial consolidado (📚)
- Operaciones CRUD deben registrar actividad en `audit_logs`
