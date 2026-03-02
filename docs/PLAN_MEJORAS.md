# FlotaControl — Plan de Mejoras e Implementaciones Nuevas

> Referencia: `FLOTA_CONTROL_PROMPT_IA.md`  
> Fecha de análisis: 2026-03-02  
> Versión actual del sistema: **v3.0.0**

---

## Inventario: Estado Actual vs Objetivos del Prompt

### Leyenda
- ✅ = Ya implementado
- ⚠️ = Parcialmente implementado
- ❌ = Pendiente / Nuevo

---

## SECCIÓN 2 — ARQUITECTURA OBLIGATORIA

| Objetivo | Estado | Notas |
|---|---|---|
| Migrar backend a Laravel | ❌ | Sistema actual: PHP puro modular. Migración es de gran escala. |
| Separar Controladores/Servicios/Repos/DTOs/Jobs | ❌ | Actualmente la lógica está en modules/api/*.php directamente |
| Clean Architecture | ❌ | Patrón actual: 3 capas (wrapper → module → includes) |
| API REST segura con JWT | ⚠️ | API REST funcional pero con sesiones PHP, no JWT |

---

## SECCIÓN 3 — FRONTEND OBLIGATORIO

| Objetivo | Estado | Notas |
|---|---|---|
| Tailwind CSS como framework principal | ❌ | CSS 100% custom con variables (dark theme) |
| Tema oscuro por defecto + tema claro | ⚠️ | Solo tema oscuro, sin toggle a claro |
| Sistema de componentes reutilizables | ⚠️ | Tiene toast, modales, paginator, pero no componentes Tailwind |
| Diseño responsive móvil → 4K | ⚠️ | Responsive básico, no optimizado para 4K |
| Paginación virtual para tablas grandes | ❌ | Paginación server-side estándar |

---

## SECCIÓN 4 — MÓDULOS A MEJORAR

### 4.1 VEHÍCULOS

| Mejora | Estado | Notas |
|---|---|---|
| Clasificación por etiquetas | ❌ | Solo tiene tipo/marca/modelo/estado |
| Cálculo automático costo por kilómetro | ❌ | Tiene costo OT y combustible por separado, no unificado por km |
| Historial visual de kilometraje (gráfica) | ❌ | Tiene odometer_logs pero sin gráfica |
| Estructura para telemetría futura | ❌ | No existe |

### 4.2 ASIGNACIONES

| Mejora | Estado | Notas |
|---|---|---|
| Calendario visual | ❌ | Solo tabla con lista |
| Evitar conflictos de reserva | ✅ | Bloqueo por asignación activa + override |
| Checklist con plantillas dinámicas | ⚠️ | Checklist existe pero con campos fijos (6 campos) |

### 4.3 MANTENIMIENTOS

| Mejora | Estado | Notas |
|---|---|---|
| OT automática desde preventivos | ✅ | Botón "Crear OT" en preventivos |
| Sistema de aprobación multinivel | ❌ | Solo umbral de aprobación como setting, sin flujo real |
| Control de repuestos más detallado | ⚠️ | Tiene partidas (mantenimiento_items), pero sin inventario de repuestos |

### 4.4 COMBUSTIBLE

| Mejora | Estado | Notas |
|---|---|---|
| Mejorar algoritmo detección anomalías | ⚠️ | Existe sistema básico de anomalías |
| Gráfico comparativo por período | ❌ | Solo stats pills y anomalías |
| Indicador de eficiencia por vehículo | ⚠️ | Calcula km/L por registro, sin dashboard de eficiencia |

### 4.5 INCIDENTES

| Mejora | Estado | Notas |
|---|---|---|
| Adjuntar múltiples archivos | ❌ | No tiene AttachmentWidget integrado |
| Flujo de seguimiento por estados | ⚠️ | Tiene estado básico, sin flujo formal |
| Dashboard de seguridad | ❌ | Solo stats cards en listado |

### 4.6 RECORDATORIOS

| Mejora | Estado | Notas |
|---|---|---|
| Sistema de alertas centralizado | ❌ | Recordatorios y notificaciones son módulos separados |
| Reglas automáticas configurables | ❌ | Solo recordatorios manuales con fecha |

### 4.7 OPERADORES

| Mejora | Estado | Notas |
|---|---|---|
| Historial de capacitaciones | ❌ | No existe |
| Registro de infracciones | ❌ | No existe |
| KPI de desempeño | ⚠️ | Tiene operador_360 en reportes, pero sin KPIs formales |

### 4.8 COMPONENTES

| Mejora | Estado | Notas |
|---|---|---|
| Inventario real con movimientos | ⚠️ | Inventario por vehículo existe pero sin registro de movimientos |
| Alertas de vencimiento automático | ⚠️ | Tiene fecha_vencimiento pero sin alertas automáticas |

### 4.9 PROVEEDORES

| Mejora | Estado | Notas |
|---|---|---|
| Sistema de evaluación de desempeño | ❌ | Solo listado y flag taller |
| Registro de contratos | ❌ | No existe |

### 4.10 SUCURSALES

| Mejora | Estado | Notas |
|---|---|---|
| Dashboard comparativo entre sucursales | ❌ | Solo CRUD con conteo básico |
| Indicadores financieros por sede | ❌ | No existe |

---

## SECCIÓN 5 — NUEVOS MÓDULOS

### 5.1 CENTRO DE ALERTAS

| Funcionalidad | Estado | Notas |
|---|---|---|
| Unificar recordatorios/mant/incidentes/combustible anómalo | ❌ | Cada módulo maneja sus alertas por separado |
| Prioridad | ❌ | |
| Estado de alerta | ❌ | |
| Responsable asignado | ❌ | |
| Historial de alertas | ❌ | |

### 5.2 API EXTERNA

| Funcionalidad | Estado | Notas |
|---|---|---|
| Endpoints REST seguros | ⚠️ | Tiene API v1 pero con sesiones, sin JWT/tokens API |
| Documentación OpenAPI | ✅ | Swagger UI + openapi.json |

### 5.3 DASHBOARD EJECUTIVO

| Funcionalidad | Estado | Notas |
|---|---|---|
| KPI globales | ⚠️ | Dashboard tiene 6 KPIs básicos |
| Gráficas comparativas | ⚠️ | 2 bar charts simples |
| Filtros dinámicos | ❌ | Sin filtros en dashboard |

---

## SECCIÓN 6 — SEGURIDAD

| Objetivo | Estado | Notas |
|---|---|---|
| 2FA opcional | ❌ | |
| Protección CSRF | ❌ | No hay tokens CSRF |
| Rate limiting en API | ❌ | |
| Logs extendidos en auditoría | ✅ | Sistema completo de audit_logs |
| Encriptación de tokens | ⚠️ | Firma digital usa token random 64 chars |

---

## SECCIÓN 7 — RENDIMIENTO

| Objetivo | Estado | Notas |
|---|---|---|
| Indexar correctamente BD | ✅ | 8+ índices compuestos implementados |
| Caché (Redis) | ❌ | Sin caché |
| Colas para procesos pesados | ❌ | Sin sistema de colas |
| Eager loading en consultas | ⚠️ | Consultas optimizadas con JOINs pero sin ORM |

---

## PLAN DE TRABAJO POR OBJETIVOS

### OBJETIVO 1: Frontend con Tailwind CSS + Sistema de Componentes
**Prioridad: ALTA** — Base visual para todas las mejoras futuras
- Integrar Tailwind CSS (CDN o build)
- Migrar layout.php a Tailwind
- Crear componentes reutilizables (botones, tablas, modales, badges, cards, alertas)
- Implementar toggle tema oscuro/claro
- Garantizar responsive hasta 4K

### OBJETIVO 2: Mejoras de Módulos Existentes — Vehículos
- Clasificación por etiquetas
- Cálculo automático de costo por km
- Historial visual de kilometraje (gráfica con Chart.js)
- Estructura base para telemetría

### OBJETIVO 3: Mejoras de Módulos — Asignaciones + Mantenimientos
- Calendario visual de asignaciones
- Checklist con plantillas dinámicas
- Sistema de aprobación multinivel para OTs
- Control de repuestos detallado

### OBJETIVO 4: Mejoras de Módulos — Combustible + Incidentes
- Gráfico comparativo por período
- Indicador de eficiencia por vehículo
- Adjuntos en incidentes
- Flujo de seguimiento por estados en incidentes
- Dashboard de seguridad

### OBJETIVO 5: Mejoras de Módulos — Operadores + Componentes + Proveedores + Sucursales
- Historial de capacitaciones
- Registro de infracciones
- KPIs de desempeño operador
- Inventario con movimientos
- Alertas vencimiento componentes
- Evaluación desempeño proveedores
- Registro de contratos
- Dashboard comparativo sucursales

### OBJETIVO 6: Centro de Alertas (Módulo Nuevo)
- Unificar todas las alertas del sistema
- Prioridad, estado, responsable, historial

### OBJETIVO 7: Dashboard Ejecutivo Mejorado
- KPI globales mejorados
- Gráficas comparativas con Chart.js
- Filtros dinámicos por sucursal/período/vehículo

### OBJETIVO 8: Seguridad Avanzada
- Protección CSRF
- Rate limiting en API
- 2FA opcional

### OBJETIVO 9: Rendimiento
- Sistema de caché
- Optimización de consultas pesadas

---

## Reglas de Ejecución

1. **Un objetivo a la vez** — No se empezará otro hasta completar el actual
2. **Commit + Push** — Al terminar cada objetivo se sube a GitHub
3. **Documentación** — Cada objetivo nuevo genera doc en `docs/`
4. **README actualizado** — Se actualiza con cada feature completada
5. **No romper lo existente** — Migración progresiva, compatibilidad total
6. **Tests** — Verificar que test_rules.php sigue pasando

---

*Documento generado automáticamente como referencia de trabajo.*
