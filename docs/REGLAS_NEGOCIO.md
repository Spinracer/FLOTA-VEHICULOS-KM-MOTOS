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

## 11. Snapshots de Componentes por Asignación
- Al **crear asignación** se captura un snapshot "entrega" de todos los componentes del vehículo.
- Al **cerrar asignación** se captura un snapshot "retorno" con posibilidad de reportar daños/observaciones.
- Los overrides de estado en retorno actualizan `vehicle_components` automáticamente.
- Tabla: `assignment_component_snapshots` con momento, estado, observaciones, created_by.

## 12. Reglas de Cierre de OT
- **exit_km obligatorio**: Al completar una OT se exige km de salida que debe ser ≥ km de entrada.
- **Resumen obligatorio**: Se requiere texto describiendo los trabajos realizados.
- **Odómetro automático**: El `exit_km` se registra en `odometer_logs` con source `maintenance_exit`.
- **Timestamps**: Se registra `completed_at` y `completed_by` al cambiar estado a Completado.

## 13. Alertas de Anomalías Combustible
- **Promedio móvil**: Se calcula el rendimiento promedio (km/L) por vehículo historicamente.
- **Rendimiento bajo**: Se alerta si un registro tiene rendimiento inferior al promedio por más del umbral configurado (`fuel.anomaly_threshold`).
- **Cargas cercanas**: Se alerta si dos cargas del mismo vehículo ocurren en menos de 24 horas.
- **Odómetro sospechoso**: Se alerta si hay retroceso de odómetro o saltos >2000 km con pocos litros.

## 14. Mantenimiento Preventivo
- **Intervalos por vehículo**: Configuración de mantenimientos recurrentes por km y/o días.
- **Verificación de vencimientos**: Endpoint que compara km actual y fecha actual contra los intervalos configurados.
- **Estados**: `vencido` (ya superó km o días) y `proximo` (dentro de 500 km o 15 días).
- **Crear OT automática**: Un clic genera una OT Pendiente desde la alerta y actualiza el intervalo con el nuevo km/fecha.

## 15. Settings del Sistema
- `fuel.anomaly_threshold`: Porcentaje mínimo bajo promedio para marcar anomalía (default: 15).
- `fuel.max_litros_evento`: Máximo de litros por carga, 0=sin límite (default: 200). Se valida en POST de combustible.
- `maintenance.umbral_aprobacion`: Costo de OT que requiere aprobación especial (default: 5000).

## 16. Soft-Delete Universal
- Las tablas con `deleted_at` (vehiculos, operadores, proveedores, mantenimientos, combustible, incidentes, recordatorios) usan soft-delete en sus endpoints DELETE.
- Todos los GETs filtran `deleted_at IS NULL` para excluir registros eliminados.
- Los asignaciones se marcan como "Cerrada" en vez de borrarse.

## 17. Permisos Granulares por Módulo
- Tabla `role_module_permissions` con combinaciones (rol, módulo, permiso).
- Módulos: vehiculos, asignaciones, mantenimientos, combustible, incidentes, recordatorios, operadores, proveedores, componentes, preventivos, reportes, catalogos, usuarios, auditoria.
- Permisos: view, create, edit, delete.
- `coordinador_it` y `admin` tienen acceso total sin restricción (hardcoded).
- `taller` solo tiene permisos en mantenimientos, proveedores, componentes y preventivos. El resto solo view.
- `monitoreo` solo tiene view en módulos funcionales, sin acceso a usuarios/catálogos.
- Función `can_module(módulo, permiso)` verifica en BD con fallback al `can()` global si la tabla no existe.
- UI admin permite editar checkbox por módulo para cada rol.

## 18. Sistema de Adjuntos
- Tabla `attachments` con entidad genérica (polimórfica por nombre).
- Almacena en `/uploads/{entidad}/{entidad_id}/`.
- Validaciones: tipos MIME (imágenes, PDF, Office), extensiones whitelist, tamaño máximo 10 MB.
- Soporte multi-archivo en una sola petición.
- Soft-delete con `deleted_at`.
- Audit log en upload y delete.

## 19. Pruebas Automatizadas
- Suite en `tests/test_rules.php` con 10 secciones de verificación.
- Verifica: roles y permisos, odómetro (validación y override), bloqueos de asignación, bloqueos de combustible, máquina de estados OT, reglas de cierre, soft-delete en todas las tablas, adjuntos (constantes y validación), integridad de esquema (todas las tablas), settings del sistema.
- Ejecutar: `php tests/test_rules.php`.
- Exit code 0 = todo pasó, 1 = hay fallos.
