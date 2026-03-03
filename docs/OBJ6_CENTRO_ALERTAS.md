# Objetivo 6 — Centro de Alertas (Módulo Nuevo)

> Versión: **v3.6.0** | Fecha: 2026-03-02

---

## Resumen

Módulo completamente nuevo que unifica todas las alertas del sistema en un centro de mando centralizado. Incluye escaneo automático de 8 fuentes de datos, priorización, asignación de responsables, historial de cambios y creación manual de alertas.

---

## Migraciones (install.php §3.17)

### Tabla `alertas`
| Campo | Tipo | Descripción |
|---|---|---|
| id | INT PK AI | Identificador |
| tipo | ENUM(10) | vencimiento, mantenimiento, incidente, combustible, recordatorio, componente, licencia, contrato, seguro, inventario |
| prioridad | ENUM(4) | Baja, Normal, Alta, Urgente |
| estado | ENUM(4) | Activa, Atendida, Descartada, Resuelta |
| titulo | VARCHAR(255) | Título corto de la alerta |
| mensaje | TEXT | Detalle extendido |
| entidad | VARCHAR(50) | Tabla fuente (e.g. operadores, vehiculos) |
| entidad_id | INT | ID del registro fuente |
| vehiculo_id | INT FK | Vehículo relacionado (nullable) |
| responsable_id | INT FK | Usuario asignado (nullable) |
| fecha_referencia | DATE | Fecha del evento (vencimiento, límite, etc.) |
| notas | TEXT | Notas internas |
| resuelto_at | DATETIME | Timestamp de resolución |
| resuelto_por | INT | Usuario que resolvió |

**Índices**: tipo, estado, prioridad, vehiculo_id, responsable_id, entidad+entidad_id, fecha_referencia.

### Tabla `alerta_historial`
| Campo | Tipo | Descripción |
|---|---|---|
| id | INT PK AI | — |
| alerta_id | INT FK CASCADE | Referencia a alerta |
| usuario_id | INT FK | Quién realizó la acción |
| accion | VARCHAR(100) | Tipo de acción (Creada, Asignada, Estado cambiado, etc.) |
| comentario | TEXT | Nota o comentario del cambio |

---

## API — modules/api/alertas.php

### Escaneo automático
`GET /api/alertas.php?action=scan`

Ejecuta 8 detectores:

1. **Licencias vencidas**: Operadores con `vigencia_licencia` en próximos 30 días → Prioridad Urgente si ≤7d, Alta si ≤30d
2. **Seguros vencidos**: Vehículos con `vigencia_seguro` en próximos 30 días → Urgente/Alta
3. **Componentes por vencer**: Registros con `fecha_vencimiento` próxima → Alta
4. **Recordatorios pendientes**: Recordatorios Pendientes dentro de 7 días → Normal/Alta
5. **Stock bajo**: Componentes con `stock <= stock_minimo` y `stock_minimo > 0` → Alta
6. **Incidentes sin atender**: Incidentes Abiertos con más de 3 días → Alta
7. **Contratos por vencer**: Contratos Vigentes venciendo en 30 días → Alta
8. **Mantenimientos vencidos**: Preventivos vencidos sin OT → Normal

Respuesta:
```json
{ "ok": true, "created": 5, "sources": { "licencias": 1, "seguros": 0, ... } }
```

### Estadísticas
`GET /api/alertas.php?action=stats`

```json
{
  "activas": 12, "urgentes": 2, "altas": 5, "sin_asignar": 8,
  "by_tipo": { "vencimiento": 3, ... },
  "by_prioridad": { "Urgente": 2, ... },
  "recientes": [{ "fecha": "2026-02-28", "total": 3 }, ...]
}
```

### Historial
`GET /api/alertas.php?action=historial&id={id}`

### CRUD
| Método | Descripción |
|---|---|
| GET | Listado con filtros: q, tipo, prioridad, estado, vehiculo_id, responsable_id. Paginado. |
| POST | Crear alerta manual. Notifica al responsable si se asigna. |
| PUT | Cambiar estado, asignar, agregar notas. Log automático en historial. |
| DELETE | Eliminación (requiere permiso delete). |

---

## Web — modules/web/alertas.php

### KPI Cards
4 indicadores en tiempo real: Activas, Urgentes, Altas, Sin Asignar.

### Barra de herramientas
- Búsqueda
- Filtro por tipo (10 opciones con iconos)
- Filtro por prioridad
- Filtro por estado (default: solo Activas)
- Botón "🔄 Escanear" — ejecuta scan automático
- Botón "+ Nueva Alerta" — formulario manual (requiere permiso create)

### Tabla principal
Columnas: Prioridad (badge), Tipo (badge+icono), Título (+ preview mensaje), Vehículo, Responsable, Fecha ref., Creada, Acciones.
Filas clickeables para abrir detalle.

### Modal de detalle
- Grid con toda la información de la alerta
- Timeline de historial (acción, comentario, usuario, fecha)
- Input para agregar notas/comentarios
- Selector para cambiar estado con botón "Cambiar"

### Modal crear/editar
Formulario con: tipo, prioridad, título, mensaje, vehículo (select dinámico), responsable (select dinámico), fecha referencia, notas.

---

## Navegación

Entrada añadida en sidebar (sección "Gestión"):
```
🚨 Centro de Alertas → /alertas.php
```

---

## Archivos modificados/creados

| Archivo | Acción |
|---|---|
| install.php | §3.17 (2 tablas) |
| modules/api/alertas.php | Nuevo — API completa |
| modules/web/alertas.php | Nuevo — Interfaz web |
| alertas.php | Nuevo — Wrapper |
| includes/layout.php | Sidebar actualizado |
| docs/OBJ6_CENTRO_ALERTAS.md | Este documento |
| docs/CHANGELOG.md | Entrada v3.6.0 |
| README.md | Actualizado a v3.6.0 |
