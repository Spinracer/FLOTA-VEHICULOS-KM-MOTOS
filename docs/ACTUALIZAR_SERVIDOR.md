# Actualizar Servidor Ubuntu — Guía Rápida

## Prerrequisitos
- Servidor con Docker + Docker Compose funcionando
- FlotaControl ya instalado y con `.installed.lock`
- Acceso SSH al servidor

---

## Pasos (copiar/pegar)

### 1) Conectarse al servidor
```bash
ssh usuario@tu_servidor_ip
cd ~/proyectos/FLOTA-VEHICULOS-KM-MOTOS
```

### 2) Respaldar antes de actualizar
```bash
# Backup de BD
docker exec flotacontrol-db mysqldump -u root -p"$(grep DB_PASS .env | cut -d= -f2)" flotacontrol > backup_db_$(date +%F).sql

# Backup de .env
cp .env .env.backup
```

### 3) Traer cambios de GitHub
```bash
git fetch origin
git checkout main
git pull origin main
```

> Si el merge a main aún no se hizo, hacer `git checkout feature/importacion-vehiculos-v1 && git pull` en su lugar.

### 4) Ejecutar migraciones
```bash
# Migración de importación de vehículos
docker exec flotacontrol-app php /var/www/html/scripts/migrate_importacion_vehiculos.php

# Migración de catálogo OC↔OT (items en OC + vinculación con OT)
docker exec flotacontrol-app php /var/www/html/scripts/migrate_catalogo_oc_ot.php
```

Ambas son idempotentes — se pueden ejecutar varias veces sin riesgo.

### 5) Reconstruir y reiniciar
```bash
docker compose build app
docker compose up -d
```

### 6) Verificar
```bash
# Ver estado de contenedores
docker compose ps

# Ver logs (Ctrl+C para salir)
docker compose logs -f app
```

Abrir el navegador en `https://flota.it-kmmotos.online` y verificar:
- Login funciona
- Vehículos: listado, crear, editar
- Importación de vehículos: subir CSV, mapear, importar
- Órdenes de Compra: crear, agregar items/partidas, imprimir PDF
- Mantenimiento: crear OT, vincular OC, imprimir con folio OC
- Catálogo: ver productos/servicios
- Asignaciones/Pase de salida: imprimir con firmas actualizadas

---

## Nuevas funcionalidades en esta actualización

| Funcionalidad | Descripción |
|--------------|-------------|
| Importación Vehículos V1 | Importar CSV/XLSX con mapeo de columnas |
| Items en OC | Partidas desglosadas en órdenes de compra |
| Catálogo | Componentes renombrado a catálogo de productos/servicios |
| OC ↔ OT | Vincular orden de compra con orden de trabajo |
| PDF OC | Impresión de orden de compra con items y firmas |
| PDF OT mejorado | Muestra folio de OC vinculada |
| Firmas actualizadas | IT y Seguridad, Finanzas, Colaborador en todos los PDF |
| CRUD OC mejorado | Vehículo obligatorio, estado automático, botones rápidos |
| Archivos adjuntos | Vista previa de archivos subidos |

---

## Si algo falla

### Revertir código
```bash
git log --oneline -n 5
git checkout <commit_anterior>
docker compose restart app nginx
```

### Restaurar BD
```bash
cat backup_db_YYYY-MM-DD.sql | docker exec -i flotacontrol-db mysql -u root -p"$(grep DB_PASS .env | cut -d= -f2)" flotacontrol
```

### Regla de oro
**NUNCA** ejecutar `docker compose down -v` — borra la base de datos y uploads.
