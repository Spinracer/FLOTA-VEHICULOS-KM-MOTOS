# ⚡ Guía Rápida de Despliegue por SSH (Copiar y Pegar)

## 🎯 Objetivo
Desplegar FlotaControl en tu servidor propio con **un comando y responder preguntas**.

---

## 📋 Antes de Empezar

Prepara esta información:
- [ ] **IP del servidor:** `192.168.1.100` (ejemplo)
- [ ] **Usuario SSH:** `ubuntu` (o tu usuario)
- [ ] **Contraseña SSH o clave SSH configurada**
- [ ] **Dominio (opcional):** `flota.miempresa.com`
- [ ] **Contraseña BD segura:** Ej: `MySecurePass2024@`

---

## 🔌 Paso 1: Conectar al Servidor (Desde Tu PC)

```bash
ssh usuario@192.168.1.100
# Reemplaza:
# - usuario → tu usuario SSH (ej: ubuntu, root, admin)
# - 192.168.1.100 → IP de tu servidor

# Ingresa la contraseña o se usará tu clave SSH
```

✅ **Resultado esperado:** Ves el prompt del servidor (ej: `ubuntu@servidor:~$`)

---

## 📥 Paso 2: Clonar el Repositorio

```bash
cd /tmp && git clone https://github.com/TuUsuario/FLOTA-VEHICULOS-KM-MOTOS.git && cd FLOTA-VEHICULOS-KM-MOTOS
```

✅ **Resultado esperado:** Ves archivos del proyecto

---

## ⚙️ Paso 3: Ejecutar el Script (PRINCIPAL)

```bash
sudo bash deploy.sh
```

---

## 🤖 Paso 4: Responder las Preguntas (Interactivo)

El script preguntará cosas. Aquí están las respuestas sugeridas:

### **1️⃣ Modo de Despliegue**

```
╔═══════════════════════════════════════════════════╗
║  FlotaControl — Despliegue Interactivo           ║
╚═══════════════════════════════════════════════════╝

── Paso 1/9: Modo de Despliegue ──

  1) Local sin dominio (acceso por IP)
  2) Con dominio propio (ej: flota.miempresa.com + SSL)
  3) Local + Cloudflare Tunnel
  4) Docker

Selecciona modo [1]:
```

**RESPUESTA:**
```
2
```

> Si no tienes dominio, responde `1`

---

### **2️⃣ Dominio (si respondiste 2)**

```
Dominio del servidor (ej: flota.miempresa.com):
```

**RESPUESTA:**
```
flota.miempresa.com
```

> Reemplaza con tu dominio real. **DEBE apuntar a tu IP en el DNS.**

---

### **3️⃣ Directorio de Instalación**

```
Directorio de instalación [/var/www/flotacontrol]:
```

**RESPUESTA:** (presiona **ENTER**)

---

### **4️⃣ Repositorio Git**

```
URL del repositorio Git [https://github.com/Spinracer/FLOTA-VEHICULOS-KM-MOTOS.git]:
```

**RESPUESTA:** (presiona **ENTER** o reemplaza con tu fork)

---

### **5️⃣ Rama**

```
Rama a desplegar [main]:
```

**RESPUESTA:** (presiona **ENTER**)

---

### **6️⃣ Base de Datos**

```
Nombre de la base de datos [flotacontrol]:
```

**RESPUESTA:** (presiona **ENTER**)

```
Usuario de la base de datos [flotacontrol]:
```

**RESPUESTA:** (presiona **ENTER**)

```
Contraseña del usuario de BD:
```

**RESPUESTA:** (ESCRIBE sin presionar ENTER, después presiona ENTER)

```
MySecurePass2024@
```

> 🔒 **La contraseña NO se verá mientras escribes (correcto por seguridad)**

---

### **7️⃣ Almacenamiento**

```
Opción [1]:
  1) Disco único (uploads dentro de /var/www/flotacontrol)
  2) Disco secundario ya montado (symlink, SIN formatear)
```

**SI TIENES DISCO DE 500GB MONTADO:**

```
2
```

```
Punto de montaje del disco de datos (ej: /mnt/data) [/mnt/data]:
```

**RESPUESTA:** (presiona **ENTER**)

---

**SI NO TIENES DISCO SECUNDARIO:**

```
1
```

---

### **8️⃣ Configuración PHP**

Verás varias preguntas. **PRESIONA ENTER en todas:**

```
Versión de PHP a usar [8.3]:
upload_max_filesize [20M]:
post_max_size [25M]:
memory_limit [256M]:
Zona horaria [America/Tegucigalpa]:
```

> Los defaults están optimizados, solo cámbia zona horaria si es necesario

---

### **9️⃣ Resumen y Confirmación**

```
── Resumen de configuración ──
  Modo:           Dominio + SSL
  Dominio/Host:   flota.miempresa.com
  Directorio:     /var/www/flotacontrol
  Repositorio:    https://github.com/TuUsuario/FLOTA-VEHICULOS-KM-MOTOS.git
  Rama:           main
  BD:             flotacontrol (user: flotacontrol)
  Uploads:        /mnt/data/flotacontrol/uploads
  PHP:            8.3
  Timezone:       America/Tegucigalpa

¿Proceder con la instalación? [s/N]:
```

**RESPUESTA:**

```
s
```

---

## ⏳ Paso 5: Esperar...

**El script tardará 5-15 minutos instalando:**

```
[INFO] Verificando requisitos...
[OK]   Sudo disponible
[OK]   Sistema operativo compatible
[INFO] Instalando dependencias (esto puede tardar)...
[OK]   PHP 8.3 instalado
[OK]   Nginx instalado
[OK]   MariaDB instalado
...
```

**⚠️ NO CIERRES LA SESIÓN SSH**

---

## ✅ Paso 6: Verificar que Terminó Bien

La pantalla final dirá:

```
╔═══════════════════════════════════════════════════╗
║  [OK] ✅ ¡Despliegue completado exitosamente!    ║
╚═══════════════════════════════════════════════════╝

📍 Acceso:
   URL:      https://flota.miempresa.com
   Instalador: https://flota.miempresa.com/install.php

🔒 Seguridad:
   .env configurado en: /var/www/flotacontrol/.env
   Permisos: 600 (solo lectura para www-data)

📊 Base de Datos:
   Host:     localhost
   Usuario:  flotacontrol
   BD:       flotacontrol
   Contraseña: ••••••• (la que ingresaste)

📂 Almacenamiento:
   Ruta:     /mnt/data/flotacontrol/uploads
   Tamaño:   Disponible

✨ Próximos pasos:
   1. Abre en navegador: https://flota.miempresa.com
   2. Completa el instalador web
   3. Guarda las credenciales del admin
```

Si ves **`[OK]`** al final, ¡está listo! ✅

Si ves **`[ERR]`**, revisa la sección **Troubleshooting** en `DEPLOY_SSH.md`

---

## 🌐 Paso 7: Abrir en Navegador

Abre tu navegador en tu PC y ve a:

```
https://flota.miempresa.com
```

(O `http://192.168.1.100` si usaste opción 1)

---

## 🛠️ Paso 8: Completar Instalador Web

Verás esto:

```
╔══════════════════════════════════════════╗
║ 🔧 Installer — FlotaControl              ║
║                                          ║
╚══════════════════════════════════════════╝

[✓] Sistema operativo compatible
[✓] PHP 8.3 instalado
[✓] Base de datos conectada
[✓] Arquivos de la app presentes

┌──────────────────────────────────────────┐
│ [Comenzar Instalación]                   │
└──────────────────────────────────────────┘
```

### Haz clic en **"Comenzar Instalación"**

Verás:

```
1. Verificando conectividad...        ✓
2. Creando tablas de BD...            ✓
3. Creando catálogos iniciales...     ✓
4. Crear usuario administrador
```

---

## 👤 Paso 9: Crear Usuario Administrador

Verás un formulario. **LLENA:**

```
Email del administrador: tu@email.com
Contraseña:              TuPassword123
Nombre del administrador: Tu Nombre
```

Luego haz clic en **"Crear Usuario"**

---

## ✅ Paso 10: Listo! (Iniciar Sesión)

Verás:

```
╔══════════════════════════════════════════╗
║  ✅ Instalación Completada               ║
║                                          ║
║  Usuario:     tu@email.com               ║
║  Contraseña:  TuPassword123              ║
║                                          ║
║  [Ir a Login]                            ║
╚══════════════════════════════════════════╝
```

Haz clic en **"Ir a Login"**

Ingresa:
- **Email:** `tu@email.com`
- **Contraseña:** `TuPassword123`

🎉 **¡Ya tienes acceso a FlotaControl!**

---

## 🔒 Paso Final: Seguridad (IMPORTANTE)

En el servidor, ejecuta:

```bash
ssh usuario@192.168.1.100
sudo rm /var/www/flotacontrol/install.php
```

Esto evita que alguien vuelva a instalar la app.

---

## 📊 Verificar Instalación (Opcional)

Si quieres confirmar que todo está corriendo:

```bash
# Conectar al servidor
ssh usuario@192.168.1.100

# Ver que los servicios estén activos
sudo systemctl status nginx
sudo systemctl status php8.3-fpm
sudo systemctl status mysql

# Ver espacio en disco
df -h

# Ver último acceso
sudo tail -20 /var/log/nginx/access.log
```

---

## 🆘 Si Algo Sale Mal

1. **Mientras se está ejecutando el script:**
   - Presiona `Ctrl+C` para detenerlo
   - Revisa qué dice el error
   - Vuelve a ejecutar `sudo bash deploy.sh` (es seguro ejecutarlo otra vez)

2. **Después de completado:**
   - Lee `DEPLOY_SSH.md` → Sección "Troubleshooting"
   - Revisa logs: `sudo tail -50 /var/log/nginx/error.log`
   - Verifica servicios: `sudo systemctl status nginx php8.3-fpm mysql`

---

## 📞 Resumen Rápido

| Acción | Comando |
|--------|---------|
| Conectar al servidor | `ssh usuario@192.168.1.100` |
| Clonar repo | `cd /tmp && git clone https://github.com/TuUsuario/FLOTA-VEHICULOS-KM-MOTOS.git && cd FLOTA-VEHICULOS-KM-MOTOS` |
| Ejecutar despliegue | `sudo bash deploy.sh` |
| Abrir aplicación | `https://flota.miempresa.com` |
| Ver logs | `sudo tail -50 /var/log/nginx/error.log` |
| Reiniciar servicios | `sudo systemctl restart nginx php8.3-fpm mysql` |

---

**🚀 ¡A desplegar!**
