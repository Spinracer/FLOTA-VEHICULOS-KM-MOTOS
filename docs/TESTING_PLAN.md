# Plan de Testing - Nuevas Implementaciones

**Fecha:** 30 de Marzo 2026  
**Versión:** 1.0  
**Cambios Probadas:**
- Sincronización automática OC→OT (solo cuando aprobada)
- Importación de vehículos con selector de campo clave (VIN/CHASIS/MOTOR)
-KM próximo mantenimiento opcional

---

## 10 Preguntas de Testing Real

### 1. ¿La sincronización de componentes OC→OT funciona solo cuando la OC está "Aprobada"?

**Procedimiento:**
- Crear una OC en estado "Pendiente" con 3 ítems (tuerca, aceite, filtro)
- Vincular a un Mantenimiento (OT)
- Verificar que los ítems NO aparezcan en la OT
- Cambiar OC a "Aprobada"
- Verificar que los ítems aparezcan automáticamente en la OT

**Resultado esperado:** ✅ Los ítems se sincronizan SOLO cuando la OC aprobada, NO en estado Pendiente.

---

### 2. ¿La sincronización se detiene cuando el mantenimiento está "Completado"?

**Procedimiento:**
- Tener una OC vinculada a una OT (ambas con ítems sincronizados)
- Completar la OT (estado = Completado)
- Agregar un nuevo ítem a la OC
- Verificar que el nuevo ítem NO aparezca en la OT completada

**Resultado esperado:** ✅ Los ítems NO se sincronizan a OT cerradas (Completado).

---

### 3. ¿El selector de campo clave (VIN/CHASIS/MOTOR) aparece al activar "Actualizar"?

**Procedimiento:**
- Ir a Importar Vehículos
- NO marcar "Actualizar vehículos existentes"
- Ver que NO aparezca el selector de campo clave
- Marcar "Actualizar vehículos existentes"
- Verificar que aparezca el selector con opciones: VIN, Chasis, Motor, Placa

**Resultado esperado:** ✅ Selector visible solo cuando modo actualizar está activo.

---

### 4. ¿La importación actualiza correctamente por VIN?

**Procedimiento:**
- Crear un vehículo con placa "ABC123" y VIN "V1234567890" pero sin modelo
- En importación:
  - Marcar "Actualizar existentes"
  - Seleccionar "VIN" como campo clave
  - Subir CSV con mismo VIN pero modelo="SUV Modelo X"
- Ejecutar importación
- Verificar en BD que el modelo fue actualizado

**Resultado esperado:** ✅ Vehículo actualizado correctamente por VIN (no por placa).

---

### 5. ¿La importación actualiza correctamente por CHASIS?

**Procedimiento:**
- Similar al anterior pero:
  - Crear vehículo con Chasis="CH987654321"
  - Seleccionar "Chasis" como campo clave
  - Importar CSV con mismo chasis pero color="Azul"
- Verificar actualización en BD

**Resultado esperado:** ✅ Vehículo actualizado correctamente por Chasis.

---

### 6. ¿La importación actualiza correctamente por MOTOR?

**Procedimiento:**
- Similar pero:
  - Crear vehículo con Motor="MOT123456"
  - Seleccionar "Motor" como campo clave
  - Importar CSV con mismo motor pero año="2023"
- Verificar actualización

**Resultado esperado:** ✅ Vehículo actualizado correctamente por Motor.

---

### 7. ¿Solo se actualiza UN campo clave a la vez (no ambos)?

**Procedimiento:**
- Intentar seleccionar VIN y Chasis al mismo tiempo
- Ver que solo uno esté seleccionable (radio button)

**Resultado esperado:** ✅ Solo puede seleccionarse un campo (radio, no checkbox).

---

### 8. ¿El campo "Próximo KM" es opcional en mantenimientos?

**Procedimiento:**
- Crear un mantenimiento sin llenar "Próximo servicio (km)"
- Guardar
- Verificar que se guarde exitosamente

**Resultado esperado:** ✅ Mantenimiento guardado sin error, próximo_km = NULL.

---

### 9. ¿Los permisos granulares por usuario funcionan?

**Procedimiento:**
- Ir a Gestión de Permisos → Usuarios
- Seleccionar un usuario (ej: "soporte")
- Denegar permiso "edit" en módulo "importacion_vehiculos"
- Acceder con ese usuario a Importar Vehículos
- Intentar ejecutar importación
- Verificar que se muestre un error de permisos (403)

**Resultado esperado:** ✅ El usuario ve el módulo pero no puede ejecutar la importación.

---

### 10. ¿Los componentes de OC→OT NO se duplican cuando se sincroniza múltiples veces?

**Procedimiento:**
- Crear OC con 2 ítems, vincular a OT
- Cambiar OC a Aprobada (sincroniza)
- Editar un ítem de OC (ej: cambiar cantidad)
- Verificar que en OT solo hay 2 ítems (no 4 duplicados)

**Resultado esperado:** ✅ Los ítems se reemplazan, no se duplican. (Usa `DELETE ... WHERE notas LIKE 'Importado desde OC-%'`).

---

## Resultado Final

| # | Pregunta | Estado | Notas |
|---|----------|--------|-------|
| 1 | Sync solo Aprobada | ⬜ | Pendiente |
| 2 | Sync detiene Completado | ⬜ | Pendiente |
| 3 | Selector campo clave visible | ⬜ | Pendiente |
| 4 | Actualizar por VIN | ⬜ | Pendiente |
| 5 | Actualizar por CHASIS | ⬜ | Pendiente |
| 6 | Actualizar por MOTOR | ⬜ | Pendiente |
| 7 | Solo un campo clave | ⬜ | Pendiente |
| 8 | KM próximo opcional | ⬜ | Pendiente |
| 9 | Permisos granulares | ⬜ | Pendiente |
| 10 | No duplicación componentes | ⬜ | Pendiente |

---

## Notas de Configuración
- **Host:** http://localhost:8080
- **Usuario Admin:** admin@flotacontrol.local / 123
- **Base Datos:** localhost:3307 (root/rootpass)
