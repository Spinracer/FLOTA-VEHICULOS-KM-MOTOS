# FlotaControl — Sistema de Administración de Flotas
## PHP + MySQL/MariaDB | Tailwind CSS | Red Local

> **v3.3.0** — Última actualización: 2026-03-02

---

## Características principales

- **Frontend moderno**: Tailwind CSS con tema oscuro/claro intercambiable
- **16 módulos completos**: Vehículos, Asignaciones, Combustible, Mantenimientos, Incidentes, Recordatorios, Reportes, Componentes, Preventivos, Operadores, Proveedores, Sucursales, Catálogos, Usuarios, Auditoría, Permisos
- **Responsive**: Mobile-first hasta pantallas 4K
- **API REST versionada**: `/api/v1/` con documentación Swagger UI
- **Reglas de negocio estrictas**: Bloqueos, odómetro, máquina de estados OT
- **Sistema de notificaciones**: En tiempo real con polling
- **Firma digital**: Canvas con soporte touch + link externo
- **Exportaciones**: CSV, XLSX y PDF
- **Auditoría completa**: Trazabilidad de todas las operaciones
- **Etiquetas de vehículos**: Clasificación libre con badges de colores y filtrado
- **Costo por kilómetro**: Cálculo automático gasto/km en Perfil 360
- **Gráfica de kilometraje**: Historial visual con Chart.js en Perfil 360
- **Telemetría preparada**: Estructura de datos lista para integración GPS/OBD
- **Calendario de asignaciones**: Vista FullCalendar.js con toggle tabla/calendario
- **Checklist dinámico**: Plantillas configurables de checklist con N items
- **Aprobaciones multinivel**: Aprobación automática para OTs de alto costo
- **Componentes en partidas**: Vinculación directa de componentes en OTs

---

## 📋 Requisitos del servidor

| Componente | Versión mínima |
|---|---|
| PHP | 7.4 o superior (recomendado 8.x) |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Servidor web | Apache (mod_rewrite) o Nginx |
| Extensión PHP | `pdo_mysql`, `session` |

---

## 🚀 Instalación paso a paso

### 1. Copiar archivos al servidor
Copia la carpeta `flotacontrol/` a la raíz web de tu servidor:

**XAMPP / WAMPP:**
```
C:\xampp\htdocs\flotacontrol\
```

**Linux (Apache):**
```
/var/www/html/flotacontrol/
```

**Nginx:**
```
/usr/share/nginx/html/flotacontrol/
```

---

### 2. Configurar variables de entorno

1) Copia `.env.example` a `.env`.

2) Edita `.env` con tus datos:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=flotacontrol
DB_USER=root
DB_PASS=
```

`includes/db.php` e `install.php` ya leen estas variables automáticamente.

### Nota de runtime en dev container

En este entorno usa `php8.3` para ejecutar el servidor local (incluye `pdo_mysql`):

```bash
php8.3 -S 0.0.0.0:8000
```

---

### 3. Ejecutar el instalador

Abre en tu navegador:
```
http://[IP-DEL-SERVIDOR]/flotacontrol/install.php
```

El instalador creará:
- La base de datos `flotacontrol`
- Todas las tablas necesarias
- Un usuario administrador inicial

---

### 4. Credenciales iniciales

| Perfil | Email | Contraseña |
|---|---|---|
| Coordinador IT | `coordinador@flotacontrol.local` | `CoordIT2024x` |
| Soporte | `soporte@flotacontrol.local` | `Soporte2024x` |
| Monitoreo | `monitoreo@flotacontrol.local` | `Monitor2024x` |
| Dev Test | `dev@flotacontrol.local` | `DevTest2024x` |

> ⚠️ **Cambia la contraseña** desde `Sistema > Usuarios` al ingresar por primera vez.

---

### 5. Eliminar el instalador (importante)

Después de instalar, **elimina o renombra** el archivo `install.php` para evitar que alguien lo vuelva a ejecutar:
```bash
rm /var/www/html/flotacontrol/install.php
```

---

## 🌐 Acceso desde la red local

Una vez instalado, cualquier equipo en la misma red puede acceder usando:
```
http://[IP-DEL-SERVIDOR]/flotacontrol/
```

Para conocer la IP de tu servidor:
- **Windows:** `ipconfig` en CMD
- **Linux/Mac:** `ip a` o `ifconfig`

---

## 👥 Roles de usuario

| Rol | Permisos |
|---|---|
| **Coordinador IT** | Acceso total + gestión de usuarios y permisos |
| **Soporte** | Ver + Crear + Editar registros |
| **Monitoreo** | Solo visualización |

---

## 📁 Estructura del proyecto

```
flotacontrol/
├── index.php              ← Pantalla de login
├── logout.php
├── dashboard.php          ← Entrada (wrapper) al módulo
├── vehiculos.php          ← Entrada (wrapper) al módulo
├── asignaciones.php       ← Entrada (wrapper) al módulo
├── combustible.php        ← Entrada (wrapper) al módulo
├── mantenimientos.php     ← Entrada (wrapper) al módulo
├── incidentes.php         ← Entrada (wrapper) al módulo
├── recordatorios.php      ← Entrada (wrapper) al módulo
├── operadores.php         ← Entrada (wrapper) al módulo
├── proveedores.php        ← Entrada (wrapper) al módulo
├── usuarios.php           ← Entrada (wrapper) al módulo
├── catalogos.php          ← Entrada (wrapper) al módulo
├── install.php            ← Instalador (eliminar después de usar)
│
├── api/                   ← Wrappers de endpoints JSON
│   ├── vehiculos.php
│   ├── asignaciones.php
│   ├── combustible.php
│   ├── mantenimientos.php
│   ├── incidentes.php
│   ├── recordatorios.php
│   ├── operadores.php
│   ├── proveedores.php
│   ├── usuarios.php
│   └── catalogos.php
│
├── modules/
│   ├── web/               ← Implementación real de páginas
│   └── api/               ← Implementación real de endpoints
│
├── includes/
│   ├── db.php             ← Configuración PDO MySQL
│   ├── auth.php           ← Sesiones y control de roles
│   ├── audit.php          ← Bitácora de cambios críticos
│   ├── odometro.php       ← Reglas y registro de odómetro
│   └── layout.php         ← Sidebar/Header HTML compartido
│
└── assets/
    ├── style.css          ← Estilos del sistema
    └── app.js             ← JavaScript compartido
```

---

## 🔧 Configuración Apache (.htaccess recomendado)

Crea un archivo `.htaccess` en la raíz del proyecto:

```apache
Options -Indexes
RewriteEngine On

# Redirigir raíz al dashboard si hay sesión
DirectoryIndex index.php

# Proteger carpeta includes
<Directory includes>
    Deny from all
</Directory>

# Headers de seguridad
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
```

---

## 🧩 Avance por módulos

- **Módulo 0**: reorganización en `modules/web` y `modules/api` con wrappers compatibles.
- **Módulo 1**: auditoría base (`audit_logs`) y trazabilidad de auth + CRUD.
- **Módulo 2**: odómetro base (`odometer_logs`) con validación de no decremento y override.
- **Módulo 3**: catálogos base y configuración global con UI admin (`catalogos.php`).
- **Módulo 4**: asignaciones con reglas de bloqueo + cierre con control de km y override.
- **Módulo 5**: talleres autorizados + usuario tipo taller con límites en mantenimientos.
- **Módulo 6**: historial consolidado por operador (asignaciones, combustible e incidentes).
- **Módulo 7 (parcial)**: combustible con bloqueo por mantenimiento activo + excepción auditada + conductor/pago/recibo.
- **Módulo 11 (parcial)**: filtros avanzados por rango de fecha en combustible.

---

## 📚 Documentación adicional

- Guía de despliegue: `DEPLOY.md`
- Tecnologías usadas: `TECNOLOGIAS.md`
- Changelog completo: `docs/CHANGELOG.md`
- Arquitectura del sistema: `docs/ARQUITECTURA.md`
- Documentación API: `docs/API.md`
- Reglas de negocio: `docs/REGLAS_NEGOCIO.md`
- Plan de mejoras: `docs/PLAN_MEJORAS.md`
- Migración Tailwind CSS: `docs/OBJ1_TAILWIND_CSS.md`

---

## 🗄️ Respaldo de base de datos

Para hacer un respaldo desde línea de comandos:
```bash
mysqldump -u root -p flotacontrol > respaldo_$(date +%Y%m%d).sql
```

Para restaurar:
```bash
mysql -u root -p flotacontrol < respaldo_20240101.sql
```

---

## 🐛 Solución de problemas comunes

**"Error de conexión a la base de datos"**
→ Verifica `DB_HOST`, `DB_USER` y `DB_PASS` en `.env`

**Página en blanco o error 500**
→ Activa la visualización de errores en PHP o revisa el log de Apache/Nginx

**No carga el CSS/JS**
→ Asegúrate de que el servidor tiene acceso a internet (Google Fonts) o descarga las fuentes localmente

**Sesión se cierra constantemente**
→ Verifica que `session.save_path` esté configurado correctamente en `php.ini`

---

## 📞 Soporte

Sistema diseñado para uso en red local. Para agregar funciones adicionales contacta a tu administrador de sistemas.
# FLOTA-VEHICULOS-KM-MOTOS
