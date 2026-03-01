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

### GET - Exportar (CSV / XLSX / PDF)
```
GET /api/reportes.php?export=combustible&format=csv&from=2026-01-01&to=2026-12-31
GET /api/reportes.php?export=mantenimiento&format=xlsx&vehiculo_id=1
GET /api/reportes.php?export=incidentes&format=pdf
```
Exportaciones disponibles: `combustible`, `mantenimiento`, `asignaciones`, `incidentes`
Formatos soportados: `csv` (descarga directa), `xlsx` (Excel), `pdf` (vista imprimible en nueva pestaña)

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

---

## Asignaciones — Snapshots (`/api/asignaciones.php`)

### GET - Consultar snapshots
```
GET /api/asignaciones.php?action=snapshots&asignacion_id=1&momento=entrega
```
Respuesta: `{ "snapshots": [...] }`

### POST - Crear snapshot de retorno manual
```json
{ "items": [{ "component_id": 3, "estado": "Malo", "observaciones": "Pantalla rota" }] }
```
URL: `POST /api/asignaciones.php?action=snapshots&asignacion_id=1`

---

## Combustible — Anomalías (`/api/combustible.php`)

### GET - Detectar anomalías
```
GET /api/combustible.php?action=anomalias&vehiculo_id=0&limit=100
```
Respuesta: `{ "alertas": [{ "registro_id": 5, "fecha": "2026-02-20", "placa": "ABC-123", "rendimiento": 4.5, "alertas": [{ "tipo": "rendimiento_bajo", "msg": "...", "severidad": "media" }] }], "promedios": { "1": 8.5 }, "threshold": 15 }`

Tipos de alerta: `rendimiento_bajo`, `carga_cercana`, `odometro_retroceso`, `salto_odometro`.

---

## Mantenimiento Preventivo (`/api/preventivos.php`)

### GET - Listar intervalos
```
GET /api/preventivos.php?action=intervals&vehiculo_id=0
```
Respuesta: `{ "rows": [...] }`

### POST - Crear intervalo
```json
{ "vehiculo_id": 1, "tipo": "Aceite y Filtros", "cada_km": 5000, "cada_dias": 90, "ultimo_km": 45000, "ultima_fecha": "2026-01-15", "proveedor_id": 2 }
```

### GET - Verificar vencimientos
```
GET /api/preventivos.php?action=check&vehiculo_id=0
```
Respuesta: `{ "alertas": [{ "interval_id": 1, "placa": "ABC-123", "tipo": "Aceite", "estado": "vencido", "km_restante": -200, "dias_restante": -5 }], "total_intervals": 10 }`

### POST - Crear OT desde alerta
```json
{ "interval_id": 1 }
```
URL: `POST /api/preventivos.php?action=create_ot`
Respuesta: `{ "ok": true, "ot_id": 42 }`

### Reglas de cierre OT (PUT mantenimientos)
Al cambiar estado a `Completado`:
- `exit_km` obligatorio (debe ser ≥ km de entrada)
- `resumen` obligatorio (texto del trabajo realizado)
- Se registra odómetro automáticamente con `exit_km`
- Se actualiza `completed_at` y `completed_by`

---

## Permisos Granulares (`/api/permisos.php`)

Solo accesible por administradores (coordinador_it/admin).

### GET - Obtener matriz
```
GET /api/permisos.php
```
Respuesta: `{ "matrix": { "soporte": { "vehiculos": ["view","create","edit"], ... }}, "modulos": [...], "permisos": [...], "roles": [...] }`

### PUT - Actualizar permisos de un rol/módulo
```json
{ "rol": "soporte", "modulo": "vehiculos", "permisos": ["view", "create", "edit"] }
```
Respuesta: `{ "ok": true, "rol": "soporte", "modulo": "vehiculos", "permisos": ["view","create","edit"] }`

---

## Adjuntos (`/api/attachments.php`)

### GET - Listar adjuntos
```
GET /api/attachments.php?entidad=vehiculos&entidad_id=1
```
Respuesta: `{ "attachments": [{ "id": 1, "filename": "...", "original_name": "foto.jpg", "mime_type": "image/jpeg", "size_bytes": 123456, ... }] }`

### GET - Descargar archivo
```
GET /api/attachments.php?action=download&id=1
```
Respuesta: Archivo binario con content-type y content-disposition.

### POST - Subir archivo(s)
Enviar como `multipart/form-data`:
- `entidad` (string): vehiculos, mantenimientos, combustible, etc.
- `entidad_id` (int): ID del registro
- `archivo` (file): Archivo(s) a subir (máx 10MB, tipos: jpg/png/gif/webp/pdf/doc/docx/xls/xlsx)

Respuesta: `{ "ok": true, "uploaded": [{ "id": 1, "filename": "...", "original_name": "...", ... }] }`

### DELETE - Eliminar adjunto
```
DELETE /api/attachments.php?id=1
```
Respuesta: `{ "ok": true, "deleted": 1 }`

---

## Reportes — Nuevos tipos

### GET - Reporte Overrides
```
GET /api/reportes.php?report=overrides&from=2024-01-01&to=2024-12-31
```
Respuesta: `{ "total": 5, "rows": [...], "por_usuario": {...}, "por_entidad": {...} }`

### GET - Perfil Operador 360
```
GET /api/reportes.php?report=operador_360&operador_id=1
```
Respuesta: `{ "operador": {...}, "totales": {...}, "asignaciones": [...], "combustible": [...], "incidentes": [...] }`

### Agrupaciones avanzadas (combustible y mantenimiento)
Parámetros adicionales:
- `group_by`: vehiculo, mes, semana, proveedor, tipo_carga, metodo_pago (combustible) / vehiculo, mes, semana, tipo, proveedor, estado (mantenimiento)
- `order_by`: gasto, cargas, litros, servicios
- `order_dir`: ASC, DESC

```
GET /api/reportes.php?report=combustible&group_by=mes&order_by=gasto&order_dir=DESC
```
Respuesta agrega campo `agrupado`: `[{ "grupo": "2024-01", "litros": 500, "gasto": 12000, "cargas": 20 }]`
