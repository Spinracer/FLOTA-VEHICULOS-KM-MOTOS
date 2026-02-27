# FlotaControl — Documentación de API

Base URL: `/api/`

Todos los endpoints requieren autenticación por sesión.  
Las respuestas son JSON. Los errores retornan `{ "error": "mensaje" }`.

---

## Vehículos (`/api/vehiculos.php`)

### GET - Listar
```
GET /api/vehiculos.php?q=busqueda&page=1&per=20
```
Respuesta: `{ "total": 10, "rows": [...] }`

### GET - Perfil 360
```
GET /api/vehiculos.php?action=profile&id=1
```
Respuesta: `{ "vehiculo": {...}, "asignacion_activa": {...}, "mantenimiento_activo": {...}, "ultimo_odometro": {...}, "totales": {...}, "historial_mantenimientos": [...], "historial_combustible": [...] }`

### POST - Crear
```json
{ "placa": "ABC-123", "marca": "Toyota", "modelo": "Hilux", "tipo": "Camioneta", "combustible": "Diésel", "km_actual": 45000, "estado": "Activo" }
```

### PUT - Editar
```json
{ "id": 1, "placa": "ABC-123", ... }
```

### DELETE - Soft-delete
```
DELETE /api/vehiculos.php?id=1
```

---

## Asignaciones (`/api/asignaciones.php`)

### GET - Listar
```
GET /api/asignaciones.php?q=&vehiculo_id=&estado=Activa&page=1&per=25
```

### POST - Crear
```json
{ "vehiculo_id": 1, "operador_id": 2, "start_at": "2026-01-01 08:00:00", "start_km": 45000, "start_notes": "", "override_reason": "" }
```
Reglas: Bloqueo por asignación activa, mantenimiento activo, estado del vehículo.

### PUT - Cerrar
```json
{ "id": 1, "action": "close", "end_at": "2026-01-15 17:00:00", "end_km": 46500, "end_notes": "" }
```

---

## Combustible (`/api/combustible.php`)

### GET - Listar con stats
```
GET /api/combustible.php?q=&vehiculo_id=&from=2026-01-01&to=2026-12-31&page=1&per=25
```
Respuesta: `{ "total": 50, "stats": { "litros": 500, "gasto": 11250 }, "rows": [...] }`

### POST - Crear
```json
{ "fecha": "2026-01-15", "vehiculo_id": 1, "operador_id": 2, "litros": 50, "costo_litro": 22.50, "km": 46000, "tipo_carga": "Lleno", "proveedor_id": 1, "metodo_pago": "Efectivo", "numero_recibo": "REC-001" }
```

---

## Mantenimientos (`/api/mantenimientos.php`)

### GET - Listar
```
GET /api/mantenimientos.php?q=&vehiculo_id=&estado=Pendiente&page=1&per=25
```
Respuesta incluye `items_count` e `items_total` por fila.

### POST - Crear OT
```json
{ "fecha": "2026-01-10", "vehiculo_id": 1, "tipo": "Preventivo", "descripcion": "Cambio de aceite", "costo": 0, "km": 45000, "proximo_km": 50000, "proveedor_id": 1, "estado": "Pendiente" }
```
Estado inicial por defecto: `Pendiente`. Transiciones permitidas por máquina de estados OT.

### PUT - Editar / Cambiar estado
```json
{ "id": 1, "fecha": "2026-01-10", "vehiculo_id": 1, "tipo": "Preventivo", "estado": "En proceso", ... }
```
Transiciones: Pendiente → En proceso | Cancelado, En proceso → Completado | Cancelado (admin).
Al cambiar a "En proceso" el vehículo se marca "En mantenimiento". Al completar/cancelar se restaura "Activo" si no hay otros activos.

### GET - Partidas (items)
```
GET /api/mantenimientos.php?action=items&mantenimiento_id=1
```
Respuesta: `{ "items": [...], "total_items": 1500.00 }`

### POST - Crear partida
```
POST /api/mantenimientos.php?action=items&mantenimiento_id=1
{ "descripcion": "Aceite 5W-30", "cantidad": 4, "unidad": "L", "precio_unitario": 350 }
```
El costo total de la OT se recalcula automáticamente.

### PUT - Editar partida
```
PUT /api/mantenimientos.php?action=items&mantenimiento_id=1
{ "id": 1, "descripcion": "Aceite 5W-30", "cantidad": 5, "unidad": "L", "precio_unitario": 350, "notas": "" }
```

### DELETE - Eliminar partida
```
DELETE /api/mantenimientos.php?action=items&mantenimiento_id=1&item_id=5
```

---

## Incidentes (`/api/incidentes.php`)

### GET - Listar con filtros dedicados
```
GET /api/incidentes.php?q=&vehiculo_id=&estado=Abierto&page=1&per=25
```

---

## Recordatorios (`/api/recordatorios.php`)

### GET - Listar con filtro de estado
```
GET /api/recordatorios.php?q=&estado=Pendiente&page=1&per=25
```

---

## Operadores (`/api/operadores.php`)

### GET - Historial
```
GET /api/operadores.php?action=history&id=1
```
Respuesta: `{ "operador": {...}, "asignaciones": [...], "combustible": [...], "incidentes": [...] }`

---

## Reportes (`/api/reportes.php`)

### GET - Reporte JSON
```
GET /api/reportes.php?report=combustible&from=2026-01-01&to=2026-12-31&vehiculo_id=1
```
Reportes disponibles: `combustible`, `mantenimiento`, `vehiculos`, `top_costosos`, `talleres`

### GET - Exportar CSV
```
GET /api/reportes.php?export=combustible&format=csv&from=2026-01-01&to=2026-12-31
```
Exportaciones disponibles: `combustible`, `mantenimiento`, `asignaciones`, `incidentes`

---

## Catálogos (`/api/catalogos.php`) — Solo admin

### GET - Lista de catálogos
```
GET /api/catalogos.php?type=catalogs
```

### GET - Ítems de catálogo
```
GET /api/catalogos.php?type=items&catalog=tipos_mantenimiento
```

### GET - Configuración global
```
GET /api/catalogos.php?type=settings
```

---

## Usuarios (`/api/usuarios.php`) — Solo admin

CRUD estándar con validaciones de rol y proveedor para tipo taller.

---

## Componentes (`/api/componentes.php`)

### GET - Catálogo maestro
```
GET /api/componentes.php?section=catalog&q=&tipo=tool&page=1&per=25
```
Respuesta: `{ "total": 12, "rows": [...] }`

### POST - Crear en catálogo
```json
{ "nombre": "Botiquín de emergencia", "tipo": "safety", "descripcion": "Kit básico" }
```
Tipos: `tool`, `safety`, `document`, `card`, `accessory`

### GET - Componentes por vehículo
```
GET /api/componentes.php?section=vehicle&vehiculo_id=1&q=&estado=Bueno&page=1&per=50
```
Respuesta: `{ "total": 8, "rows": [...], "resumen": { "Bueno": 5, "Regular": 2, "Faltante": 1 } }`

### POST - Asignar componente a vehículo
```json
{ "vehiculo_id": 1, "component_id": 3, "cantidad": 1, "estado": "Bueno", "numero_serie": "ABC123", "proveedor": "Proveedor X", "fecha_instalacion": "2026-01-01", "fecha_vencimiento": "2027-01-01" }
```
Duplicados se rechazan (409).

---

## Auditoría (`/api/auditoria.php`) — Solo admin

### GET - Consultar bitácora
```
GET /api/auditoria.php?q=&entidad=vehiculos&accion=create&user_id=1&desde=2026-01-01&hasta=2026-12-31&page=1&per=50
```
Respuesta: `{ "total": 100, "rows": [...], "entidades": ["auth","vehiculos",...], "acciones": ["create","update",...] }`

Cada fila incluye `antes`, `despues`, `meta` (JSON decodificado).
