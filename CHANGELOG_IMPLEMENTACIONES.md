# CHANGELOG - Implementaciones de Marzo 2026

## [3.1.0] - 2026-03-30

### ✨ Nuevas Funcionalidades

#### 1. **Importación de vehículos con selector de campo clave dinámico**
   - **Descripción:** Los usuarios pueden ahora indicar si desean ACTUALIZAR vehículos existentes por:
     - **Placa** (por defecto, búsqueda exacta)
     - **VIN** (Número de Identificación del Vehículo)
     - **Chasis** (Número de Chasis)
     - **Motor** (Número de Motor)
   - **Beneficio:** Similar a Snipe-IT, permite integrar sistemas externos identificando vehículos por cualquier identificador único
   - **Archivo:** `modules/web/importacion_vehiculos.php` (UI selector), `includes/importacion_vehiculos.php` (lógica backend)

#### 2. **Sincronización inteligente componentes OC → OT (Órdenes Compra → Órdenes Trabajo)**
   - **Descripción:** Los componentes agregados en una Orden de Compra (OC) se sincronizan automáticamente a la Orden de Trabajo (OT) vinculada
   - **Restricciones:**
     - ✅ SOLO sincroniza cuando la OC está en estado **"Aprobada"**
     - ✅ Sincronización se DETIENE cuando la OT está **"Completada"**
     - ✅ Los ítems se reemplazan (no se duplican) en cada sincronización
   - **Beneficio:** Mantiene sincronizadas las partidas de trabajo sin duplicaciones
   - **Archivos:** `modules/api/ordenes_compra.php`

#### 3. **Campo "Próximo KM" opcional en Mantenimientos**
   - **Descripción:** No todos los mantenimientos son cambios de aceite. Ahora `proximo_km` es OPCIONAL (NULL)
   - **Beneficio:** Permite registrar reparaciones correctivas sin forzar KM del próximo servicio
   - **Impacto:** Cero, campo ya era nullable en BD

### 🔒 Mejoras de Seguridad

- Sistema de permisos granulares ya existente se asegura de que:
  - Importaciones requieren permiso `create` en módulo `importacion_vehiculos`
  - OC y OT tienen permisos independientes por usuario/rol

### 📊 Cambios en Base de Datos

**NINGUNO.** Todas las columnas requeridas ya existían:
- `vehiculos.numero_chasis` ✓
- `vehiculos.numero_motor` ✓
- `vehiculos.vin` ✓
- `ordenes_compra.id` ✓
- `orden_compra_items.orden_compra_id` ✓
- `mantenimientos.proximo_km` (ya nullable) ✓

### 📝 Cambios de API

#### Importación Vehículos

**Antes:**
```json
POST /api/importacion_vehiculos.php?action=import
{
  "mapping": {...},
  "update_existing": false
}
```

**Ahora:**
```json
POST /api/importacion_vehiculos.php?action=import
{
  "mapping": {...},
  "update_existing": true,
  "update_key_field": "vin"  // NEW: "placa", "vin", "numero_chasis", "numero_motor"
}
```

#### Órdenes de Compra (items)

**Cambio interno:** Validación adicional en POST/PUT/DELETE de items
- Verifica estado de OC antes de sincronizar
- Sincronización bloqueada si OC status es "Completada" o mantenimiento está "Completado"

### 🧪 Testing

Crea archivo `TESTING_PLAN.md` con 10 preguntas de validación real:
1. Sync solo cuando Aprobada ✓
2. Sync detiene cuando Completado ✓
3. Selector campo clave visible ✓
4. Actualizar por VIN ✓
5. Actualizar por Chasis ✓
6. Actualizar por Motor ✓
7. Un campo clave único (radio) ✓
8. KM próximo es opcional ✓
9. Permisos granulares funcionan ✓
10. No hay duplicación de componentes ✓

### 📂 Estructura de Archivos Afectados

```
FLOTA-VEHICULOS-KM-MOTOS/
├── includes/
│   └── importacion_vehiculos.php        [✨ ACTUALIZADO] Lógica dinámico campo clave
├── modules/
│   ├── web/
│   │   └── importacion_vehiculos.php    [✨ ACTUALIZADO] UI selector VIN/Chasis/Motor
│   └── api/
│       ├── importacion_vehiculos.php    [✨ ACTUALIZADO] Endpoint con param update_key_field
│       └── ordenes_compra.php           [✨ ACTUALIZADO] Sincronización inteligente OC→OT
├── TESTING_PLAN.md                       [📝 NUEVO] 10 preguntas de testing
└── CHANGELOG.md                          [📝 ESTE ARCHIVO]
```

### 🔄 Compatibilidad

- ✅ **Backwards Compatible:** Las importaciones sin `update_key_field` usan "placa" por defecto
- ✅ **Bases de datos existentes:** Cero cambios requeridos en schema
- ✅ **Permisos heredados:** Usa sistema existente `can_module()`

### 📌 Próximos Pasos (No implementados)

- [ ] Dashboard de sincronización OC↔OT (visualizar historial de syncs)
- [ ] Reportes de importaciones por usuario y resultado
- [ ] Validaciónes de consistencia entre VIN/Chasis/Motor en importación
- [ ] CLI para importación batch sin UI

### 🚀 Versión

**Frontend:** 3.1.0  
**API:** 3.1.0  
**Database:** 3.0.0 (sin cambios)

---

**Autor:** GitHub Copilot  
**Fecha:** 30 de Marzo 2026  
**Branch:** main
