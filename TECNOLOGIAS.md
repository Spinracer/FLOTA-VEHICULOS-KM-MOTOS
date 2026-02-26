# Tecnologías usadas

## Backend

- PHP (runtime objetivo: 8.3 en este entorno)
- Arquitectura modular en PHP tradicional (`modules/web`, `modules/api`)
- PDO para acceso a datos

## Base de datos

- MySQL 8
- Tablas funcionales por módulo (vehículos, combustible, mantenimientos, etc.)
- Auditoría: `audit_logs`
- Odómetro: `odometer_logs`
- Catálogos: tablas `catalogo_*` y `system_settings`

## Frontend

- HTML + CSS + JavaScript vanilla
- `assets/style.css`
- `assets/app.js`
- Renderizado server-side + consumo de API interna JSON

## Seguridad y control

- Sesiones PHP (`includes/auth.php`)
- Roles y permisos base: `coordinador_it`, `soporte`, `monitoreo`
- Control de acceso por rutas y acciones

## Despliegue/Infra

- Entorno local soportado con:
  - Docker (MySQL)
  - Servidor embebido de PHP (`php8.3 -S`)
- Compatible con Apache/Nginx en servidor tradicional

## Estado de evolución

- Módulo 0: Reorganización estructural y entorno
- Módulo 1: Auditoría base y trazabilidad
- Módulo 2: Control de odómetro
- Módulo 3: Catálogos base y configuración global
- Módulo 4: Asignaciones con reglas de bloqueo
- Módulo 5: Talleres autorizados y portal de taller
- Módulo 6: Historial operativo por conductor
- Módulo 7 (parcial): Bloqueo de combustible por mantenimiento activo + override auditado
