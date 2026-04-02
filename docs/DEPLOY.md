# Guía de despliegue

## 1) Requisitos

- PHP 8.3 con extensiones: `pdo_mysql`, `session`, `mbstring`, `json`
- MySQL 8 o MariaDB compatible
- Servidor web Apache/Nginx (o servidor embebido PHP para pruebas)

## 2) Variables de entorno

1. Copia `.env.example` a `.env`
2. Ajusta credenciales:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=flotacontrol
DB_USER=root
DB_PASS=tu_password
```

## 3) Inicialización de base de datos

Ejecuta una vez:

```bash
php8.3 install.php
```

Este paso crea tablas base, catálogos semilla, auditoría y odómetro.

## 4) Levantar en local (desarrollo)

### Opción A: Docker Compose (recomendado)

```bash
cp .env.example .env
# Editar .env con tus credenciales
docker compose up -d
```

App disponible en: `http://localhost:8080`

Ver `INSTRUCCIONES_DOCKER.md` para la guia completa.

### Opción B: MySQL en Docker + PHP embebido

```bash
docker run -d --name flotacontrol-db \
  -e MYSQL_ROOT_PASSWORD=YOUR_SECURE_PASSWORD \
  -e MYSQL_DATABASE=flotacontrol \
  -p 3306:3306 mysql:8

php8.3 -S 0.0.0.0:8080
```

App disponible en: `http://127.0.0.1:8080`

### Opción C: Servicios locales instalados

- Asegura MySQL escuchando en `DB_HOST:DB_PORT`
- Ejecuta `php8.3 -S 0.0.0.0:8080`

## 5) Producción (recomendaciones)

- No exponer `install.php` tras instalación (eliminar o bloquear)
- Mantener `.env` fuera de Git
- Forzar HTTPS
- Configurar backup periódico de DB
- Monitorear logs de aplicación y web server

## 6) Nuevas Features v3.1.0 (Marzo 2026)

### Importación de Vehículos con Selector de Campo Clave

En el módulo **Vehículos → Importar**, ahora puedes:

1. **Marcar opción:** "Actualizar vehículos existentes"
2. **Elegir campo clave** para detectar duplicados:
   - **Placa** (por defecto, búsqueda exacta)
   - **VIN** (Número de Identificación del Vehículo)
   - **Número Chasis**
   - **Número Motor**

**Ejemplo API Request:**
```json
POST /api/importacion_vehiculos.php?action=import
{
  "mapping": {
    "0": "placa",
    "1": "marca",
    "2": "numero_vin"
  },
  "update_existing": true,
  "update_key_field": "vin"
}
```

### Sincronización Inteligente OC ↔ OT

Cuando agregas componentes a una **Orden de Compra (OC)**:
- ✅ Se sincronizan automáticamente a la **Orden de Trabajo (OT)** asociada
- ✅ **Solo si** la OC está en estado `Aprobada`
- ✅ La sincronización se **detiene** si la OT está `Completada`
- ✅ Los ítems se **reemplazan** (sin duplicación)

### Campo "Próximo KM" Opcional

El campo `proximo_km` en **Mantenimientos** es ahora **OPCIONAL**, permitiendo:
- Registrar reparaciones correctivas sin KM programado
- Servicios que no tienen intervalo de mantenimiento definido
- Mayor flexibilidad en registros de reparación

---

## 7) Verificación rápida

- `GET /` debe responder 200
- Login debe redirigir a dashboard
- Menú de Sistema debe mostrar Catálogos para admin
- Menú principal debe mostrar Asignaciones
- En Proveedores debe existir campo de taller autorizado
- Usuarios admin permite crear rol Taller con proveedor asignado
- Operadores debe permitir abrir historial consolidado (📚)
- En Combustible debe pedirse conductor, método de pago y número de recibo
- Operaciones CRUD deben registrar actividad en `audit_logs`

**Nuevas verificaciones v3.1.0:**
- Importar vehículos → checkbox UPDATE visible
- Selector de campo clave (VIN/Chasis/Motor) disponible
- OC → agregar componente → se sincroniza a OT solo si Aprobada
- Mantenimiento → campo KM próximo acepta NULL sin error
