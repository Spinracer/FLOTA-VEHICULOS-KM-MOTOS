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

### POST - Crear
```json
{ "fecha": "2026-01-10", "vehiculo_id": 1, "tipo": "Preventivo", "descripcion": "Cambio de aceite", "costo": 1500, "km": 45000, "proximo_km": 50000, "proveedor_id": 1, "estado": "Completado" }
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
