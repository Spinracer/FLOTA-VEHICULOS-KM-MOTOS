# FlotaControl — Reglas de Negocio

## 1. Odómetro
- **No puede disminuir**: Si el km nuevo es menor al último registrado, se bloquea.
- **Override admin**: Coordinador IT puede forzar con justificación (registra audit).
- **Auto-registro**: Se registra automáticamente al crear/editar combustible, mantenimiento, y asignaciones (inicio/cierre).
- **Fuente de verdad**: `MAX(vehiculos.km_actual, MAX(odometer_logs.reading_km))`.

## 2. Asignaciones
- **Bloqueos duros**:
  - No asignar si el vehículo ya tiene asignación activa.
  - No asignar si tiene mantenimiento activo (En proceso/Pendiente).
  - No asignar si el vehículo no está en estado "Activo".
  - No asignar si el operador está inactivo/suspendido.
- **Override**: Solo admin con justificación + audit.
- **Cierre**: KM final obligatorio y validado contra odómetro.

## 3. Combustible
- **Bloqueo por mantenimiento**: Si el vehículo está en mantenimiento activo, no se permite carga.
- **Conductor obligatorio**: Debe seleccionarse un operador activo.
- **Total automático**: litros × costo_litro.
- **Rendimiento**: Se calcula como (km_actual - km_carga_anterior) / litros, usando la carga previa del mismo vehículo.
- **Override**: Admin puede saltar bloqueos con justificación.

## 4. Mantenimiento (OT)
- **Taller autorizado**: Solo proveedores con `es_taller_autorizado=1` pueden registrar.
- **Restricción por rol taller**: Solo ven/editan sus propios mantenimientos.
- **Máquina de estados OT**:
  - Pendiente → En proceso
  - Pendiente → Cancelado
  - En proceso → Completado
  - En proceso → Cancelado (solo admin)
  - Completado → (sin transiciones)
  - Cancelado → (sin transiciones)
- **Transiciones automáticas de vehículo**:
  - Al cambiar a "En proceso": vehículo se marca "En mantenimiento".
  - Al cambiar a "Completado"/"Cancelado": vehículo vuelve a "Activo" (si no hay otras OT activas).
- **Bloqueo de edición**: OTs completadas no se pueden editar ni agregar partidas.
- **Partidas (mantenimiento_items)**: Cantidad, unidad, precio unitario, subtotal calculado. El costo total de la OT se recalcula automáticamente al agregar/editar/eliminar partidas.
- **Odómetro**: Se registra automáticamente al crear/editar.

## 5. Vehículos
- **Placa única**: Restricción a nivel de BD.
- **Soft-delete**: Al eliminar, se marca `deleted_at` en vez de borrar (preserva historial).
- **Perfil 360**: Endpoint que consolida asignación activa, mantenimiento activo, último odómetro, totales e historial reciente.

## 6. Operadores
- **Soft-delete**: Similar a vehículos.
- **Estados**: Activo, Inactivo, Suspendido.
- **Regla**: Operador inactivo/suspendido no puede ser asignado ni registrar combustible.

## 7. Catálogos
- **Dinámicos**: Los tipos de mantenimiento, estados de vehículo y servicios de taller se cargan desde la BD.
- **Fallback**: Si el catálogo está vacío, se muestran opciones hardcoded como respaldo.

## 8. Auditoría
- Toda creación, edición, eliminación y override se registra en `audit_logs`.
- Incluye: user_id, email, rol, entidad, acción, before/after JSON, meta, IP, timestamp.

## 9. Transacciones
- Las operaciones multi-query (INSERT + odómetro + km update) usan `beginTransaction/commit/rollBack`.
- Aplica a: combustible (create/update), asignaciones (create/close), mantenimientos (create/update), partidas (create/update/delete con recálculo de costo).

## 10. Componentes/Inventario
- **Catálogo maestro**: Tabla `components` con tipos: tool, safety, document, card, accessory.
- **Asignación por vehículo**: Tabla `vehicle_components` con estado (Bueno, Regular, Malo, Faltante), fechas, N° serie.
- **Sin duplicados**: No se puede asignar el mismo componente dos veces al mismo vehículo (409).
- **Soft-delete en catálogo**: Los componentes se desactivan (`activo=0`) en vez de borrarse.
- **KPIs por vehículo**: Resumen de estados (Bueno/Regular/Malo/Faltante) en la respuesta API.
