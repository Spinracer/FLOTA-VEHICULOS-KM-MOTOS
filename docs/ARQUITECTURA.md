# FlotaControl — Arquitectura del Sistema

## Stack Tecnológico
- **Backend**: PHP 8.2+ (sin framework, arquitectura modular)
- **Base de datos**: MySQL 8 con InnoDB
- **Frontend**: HTML5 + CSS3 (tema dark) + JavaScript Vanilla
- **Autenticación**: Sesiones PHP con SameSite=Strict

## Estructura del Proyecto

```
├── index.php              # Login
├── install.php            # Instalador de BD y datos semilla
├── dashboard.php          # → modules/web/dashboard.php
├── vehiculos.php          # → modules/web/vehiculos.php
├── asignaciones.php       # → modules/web/asignaciones.php
├── combustible.php        # → modules/web/combustible.php
├── mantenimientos.php     # → modules/web/mantenimientos.php
├── incidentes.php         # → modules/web/incidentes.php
├── recordatorios.php      # → modules/web/recordatorios.php
├── reportes.php           # → modules/web/reportes.php
├── componentes.php        # → modules/web/componentes.php
├── auditoria.php          # → modules/web/auditoria.php (admin)
├── preventivos.php        # → modules/web/preventivos.php
├── operadores.php         # → modules/web/operadores.php
├── proveedores.php        # → modules/web/proveedores.php
├── catalogos.php          # → modules/web/catalogos.php
├── usuarios.php           # → modules/web/usuarios.php
│
├── api/                   # Wrappers API → modules/api/
│   ├── vehiculos.php
│   ├── asignaciones.php
│   ├── combustible.php
│   ├── mantenimientos.php
│   ├── incidentes.php
│   ├── recordatorios.php
│   ├── reportes.php       # Reportes y exportaciones CSV
│   ├── componentes.php    # Catálogo + inventario por vehículo
│   ├── auditoria.php      # Consulta de bitácora (admin)
│   ├── preventivos.php    # Intervalos preventivos y alertas
│   ├── operadores.php
│   ├── proveedores.php
│   ├── catalogos.php
│   └── usuarios.php
│
├── modules/
│   ├── api/               # Lógica de negocio (endpoints JSON)
│   └── web/               # Vistas HTML (consumen la API)
│
├── includes/
│   ├── db.php             # Conexión PDO singleton con .env
│   ├── auth.php           # Roles, permisos, sesiones, login/logout
│   ├── audit.php          # Bitácora de auditoría
│   ├── odometro.php       # Validación y registro de odómetro
│   ├── catalogos.php      # Helper para cargar catálogos dinámicos
│   ├── export.php         # Motor de exportación CSV
│   ├── layout.php         # Layout principal con sidebar
│   └── 403.php            # Página de error 403
│
├── assets/
│   ├── app.js             # Helpers JS: toast, api, modals, pagination, charts
│   └── style.css          # Tema dark con CSS variables
│
└── docs/                  # Documentación del proyecto
    ├── ARQUITECTURA.md    # Este archivo
    ├── CHANGELOG.md       # Historial de cambios
    ├── API.md             # Documentación de endpoints
    └── REGLAS_NEGOCIO.md  # Reglas de negocio documentadas
```

## Patrón Arquitectural

```
[Browser] → [raíz/*.php] → [modules/web/*.php] → render_layout()
                                    ↓ (fetch JS)
           [api/*.php]   → [modules/api/*.php] → JSON response
                                    ↓
                          [includes/*.php] (DB, Auth, Audit, Odómetro)
```

### Capas
1. **Wrappers (raíz)**: Archivos de 1-2 líneas que delegan a módulos
2. **Módulos Web**: Vistas server-side con PHP + JS que consumen la API
3. **Módulos API**: Endpoints JSON con reglas de negocio, validación, auditoría
4. **Includes**: Servicios transversales (DB, Auth, Audit, Odómetro, Catálogos, Export)

## Base de Datos (18 tablas)

| Tabla | Descripción |
|-------|------------|
| `usuarios` | Usuarios del sistema con roles |
| `proveedores` | Proveedores y talleres |
| `operadores` | Conductores/operadores |
| `vehiculos` | Inventario de vehículos |
| `combustible` | Registros de carga de combustible |
| `mantenimientos` | Bitácora de mantenimientos |
| `asignaciones` | Asignaciones vehículo-operador |
| `incidentes` | Registro de incidentes |
| `recordatorios` | Alertas y recordatorios |
| `odometer_logs` | Log de lecturas de odómetro |
| `audit_logs` | Bitácora de auditoría |
| `catalogo_categorias_gasto` | Catálogo: categorías de gasto |
| `catalogo_unidades` | Catálogo: unidades de medida |
| `catalogo_tipos_mantenimiento` | Catálogo: tipos de mantenimiento |
| `catalogo_estados_vehiculo` | Catálogo: estados de vehículo |
| `catalogo_servicios_taller` | Catálogo: servicios de taller |
| `system_settings` | Configuración global del sistema |

## Roles y Permisos

| Rol | Permisos |
|-----|----------|
| `coordinador_it` | view, create, edit, delete, manage_users, manage_permissions |
| `soporte` | view, create, edit |
| `monitoreo` | view |
| `taller` | view, create, edit (restringido a su proveedor) |
