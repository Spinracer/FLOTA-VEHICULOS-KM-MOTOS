# FlotaControl — Sistema de Administración de Flotas
## PHP + MySQL/MariaDB | Red Local

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
├── combustible.php        ← Entrada (wrapper) al módulo
├── mantenimientos.php     ← Entrada (wrapper) al módulo
├── incidentes.php         ← Entrada (wrapper) al módulo
├── recordatorios.php      ← Entrada (wrapper) al módulo
├── operadores.php         ← Entrada (wrapper) al módulo
├── proveedores.php        ← Entrada (wrapper) al módulo
├── usuarios.php           ← Entrada (wrapper) al módulo
├── install.php            ← Instalador (eliminar después de usar)
│
├── api/                   ← Wrappers de endpoints JSON
│   ├── vehiculos.php
│   ├── combustible.php
│   ├── mantenimientos.php
│   ├── incidentes.php
│   ├── recordatorios.php
│   ├── operadores.php
│   ├── proveedores.php
│   └── usuarios.php
│
├── modules/
│   ├── web/               ← Implementación real de páginas
│   └── api/               ← Implementación real de endpoints
│
├── includes/
│   ├── db.php             ← Configuración PDO MySQL
│   ├── auth.php           ← Sesiones y control de roles
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
