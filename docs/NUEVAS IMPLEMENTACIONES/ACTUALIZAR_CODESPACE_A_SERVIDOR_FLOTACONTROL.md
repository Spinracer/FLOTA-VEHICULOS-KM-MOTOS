# Actualizar FlotaControl desde Codespaces hacia el servidor Ubuntu sin perder configuración ni datos

## Objetivo

Trabajar el cambio en **GitHub Codespaces**, probarlo allí, subirlo a GitHub y luego actualizar el servidor Ubuntu que ya tiene el sistema funcionando, **sin perder**:

- `.env`
- `.installed.lock`
- base de datos
- usuarios, perfiles, permisos
- archivos en `uploads/`
- configuración existente del servicio

---

## Contexto actual del proyecto

Este repositorio:

- usa wrappers en raíz como `vehiculos.php` que delegan a `modules/web/vehiculos.php`
- maneja la lógica API en `modules/api/*.php`
- en Docker monta el código del repo en `/var/www/html`
- guarda `uploads/` y la base MySQL en volúmenes Docker separados
- ignora `.env` en git, por lo que no debe subirse ni sobrescribirse

---

## Flujo correcto de trabajo

## 1) Desarrollar en Codespaces

Trabajar siempre en una rama nueva. Ejemplo:

```bash
git checkout -b feature/importacion-vehiculos-v1
```

Hacer todos los cambios allí:

- vistas nuevas
- endpoints nuevos
- migraciones idempotentes
- pruebas
- documentación

---

## 2) Confirmar que el cambio no rompe producción

Antes de cerrar la versión, validar en Codespaces:

- listado de vehículos sigue funcionando
- modal de crear/editar vehículo sigue funcionando
- API actual de vehículos no pierde compatibilidad
- el nuevo módulo de importación no reemplaza ni rompe el flujo manual
- no se toca `.env`
- no se toca `.installed.lock`
- no se depende de correr `install.php`

---

## 3) Subir cambios a GitHub

```bash
git add .
git commit -m "feat: importacion de vehiculos v1"
git push origin feature/importacion-vehiculos-v1
```

Luego:

- abrir Pull Request
- revisar cambios
- mergear a `main` solo cuando ya esté probado

---

## 4) Respaldar el servidor antes de actualizar

Entrar al servidor Ubuntu y crear respaldo rápido del código actual y de la base de datos.

### Respaldo del código

```bash
cd ~/proyectos/FLOTA-VEHICULOS-KM-MOTOS
tar -czf backup_codigo_$(date +%F_%H%M%S).tar.gz \
  --exclude='.git' \
  --exclude='vendor' \
  --exclude='node_modules' \
  .
```

### Respaldo de base de datos con Docker

```bash
docker exec flotacontrol-db mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > backup_db_$(date +%F_%H%M%S).sql
```

> Si el shell no tiene cargadas esas variables, usar directamente los valores reales del `.env`.

### Respaldo extra del `.env`

```bash
cp .env .env.backup.$(date +%F_%H%M%S)
```

---

## 5) Actualizar el código del servidor

Ir al repo en el servidor:

```bash
cd ~/proyectos/FLOTA-VEHICULOS-KM-MOTOS
git status
git fetch origin
git checkout main
git pull origin main
```

Si el servidor está en otra ruta, usar la ruta real del proyecto.

---

## 6) Ejecutar migración segura del nuevo módulo

La nueva funcionalidad de importación **no debe** depender de `install.php`, porque ese archivo está bloqueado por `.installed.lock` en instalaciones ya activas.

La actualización debe venir con una de estas opciones:

### Opción recomendada
Un script idempotente dedicado, por ejemplo:

```bash
php scripts/migrate_importacion_vehiculos.php
```

o si corre dentro del contenedor app:

```bash
docker exec flotacontrol-app php /var/www/html/scripts/migrate_importacion_vehiculos.php
```

### Reglas del script de migración

Debe:

- crear tablas nuevas solo si no existen
- agregar columnas nuevas solo si no existen
- crear índices solo si no existen
- insertar configuraciones base solo si no existen
- poder ejecutarse varias veces sin romper nada

No debe:

- borrar datos existentes
- modificar `.env`
- eliminar `.installed.lock`
- recrear tablas ya activas
- usar `DROP TABLE`
- usar `docker compose down -v`

---

## 7) Reiniciar solo lo necesario

### Si usas Docker

```bash
docker compose ps
docker compose restart app nginx
```

Si hubo cambio fuerte de dependencias o build:

```bash
docker compose build app
docker compose up -d
```

### Regla crítica

**Nunca** ejecutar:

```bash
docker compose down -v
```

porque eso puede eliminar volúmenes y afectar base de datos o archivos persistentes.

---

## 8) Verificaciones posteriores al despliegue

Confirmar:

- login funcional
- listado de vehículos funcional
- crear vehículo manual funcional
- editar vehículo funcional
- importación visible y funcional
- `uploads/` intacto
- datos históricos intactos
- usuarios y permisos intactos

---

## 9) Plan de reversa si algo falla

### Revertir código a commit anterior

```bash
git log --oneline -n 10
git checkout <commit_estable>
docker compose restart app nginx
```

### Restaurar base de datos si fuera necesario

```bash
cat backup_db_YYYY-MM-DD_HHMMSS.sql | docker exec -i flotacontrol-db mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"
```

---

## 10) Política recomendada por versión

Trabajar así cada versión de importación:

- `feature/importacion-vehiculos-v1`
- `feature/importacion-vehiculos-v2`
- `feature/importacion-vehiculos-v3`
- `feature/importacion-vehiculos-v4`
- `feature/importacion-vehiculos-v5`

Cada versión debe:

- ser desplegable por separado
- dejar el sistema usable aunque la siguiente versión no exista aún
- traer su propia documentación
- incluir su migración segura si agrega estructura nueva

---

## Requisitos para IA / desarrollador

La implementación debe asumir este flujo real:

- desarrollo en Codespaces
- push a GitHub
- pull en servidor Ubuntu
- migración segura
- reinicio controlado
- validación post-deploy

No asumir:

- edición manual del código en producción
- reinstalación del sistema
- borrado de volúmenes
- reemplazo de `.env`
- reseteo de base de datos
