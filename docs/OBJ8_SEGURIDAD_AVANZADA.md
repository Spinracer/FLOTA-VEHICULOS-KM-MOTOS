# Objetivo 8 — Seguridad Avanzada

> Versión: 3.8.0  
> Fecha: 2026-03-07

---

## Componentes implementados

### 1. Protección CSRF (Cross-Site Request Forgery)

**Archivo:** `includes/csrf.php`

#### Funcionamiento
- Se genera un token CSRF único por sesión (`bin2hex(random_bytes(32))`, 64 caracteres hex).
- El token se inyecta como `<meta name="csrf-token">` en el `<head>` del layout.
- La función `api()` en `app.js` lee el meta tag y lo envía como header `X-CSRF-Token` en cada petición.
- Para formularios POST tradicionales (login), se usa un `<input type="hidden" name="_csrf_token">`.
- El servidor valida el token en todas las operaciones de escritura (POST, PUT, PATCH, DELETE).
- GET, HEAD y OPTIONS están exentos.
- Token se regenera al hacer login exitoso para prevenir session fixation.

#### Funciones
| Función | Descripción |
|---|---|
| `csrf_token()` | Genera o retorna el token de sesión |
| `csrf_meta()` | Retorna `<meta>` tag HTML |
| `csrf_field()` | Retorna `<input hidden>` para formularios |
| `csrf_validate()` | Valida token desde header o body |
| `csrf_enforce()` | Valida y aborta con 403 si inválido |
| `csrf_regenerate()` | Regenera token (post-login) |

#### Respuesta de error
```json
HTTP 403
{"error": "Token CSRF inválido o ausente. Recarga la página."}
```

---

### 2. Rate Limiting

**Archivo:** `includes/rate_limit.php`  
**Tabla:** `rate_limits`

#### Límites configurados
| Acción | Máximo | Ventana |
|---|---|---|
| `login` | 5 intentos | 60 segundos |
| `api_write` | 60 requests | 60 segundos |
| `api_read` | 120 requests | 60 segundos |

#### Funcionamiento
- Usa tabla MySQL `rate_limits` con clave compuesta `{acción}:{identificador}`.
- Para login: identificador = IP del cliente.
- Para API: identificador = ID del usuario autenticado.
- Limpieza automática probabilística (1% de requests) de entradas expiradas.
- Fail-open: si la tabla no existe, permite la solicitud.

#### Funciones
| Función | Descripción |
|---|---|
| `rate_limit_check()` | Verifica si está dentro del límite (bool) |
| `rate_limit_enforce()` | Verifica y aborta con 429 si excedido |
| `rate_limit_remaining()` | Retorna solicitudes restantes |

#### Respuesta de error
```json
HTTP 429
Retry-After: 60
{"error": "Demasiadas solicitudes. Intenta de nuevo en un momento."}
```

---

### 3. 2FA con TOTP (Time-based One-Time Password)

**Archivo:** `includes/totp.php`  
**Columnas:** `usuarios.totp_secret`, `usuarios.totp_enabled`

#### Compatibilidad
- Google Authenticator
- Authy
- Microsoft Authenticator
- 1Password
- Cualquier app compatible con RFC 6238

#### Parámetros TOTP
| Parámetro | Valor |
|---|---|
| Algoritmo | HMAC-SHA1 |
| Período | 30 segundos |
| Dígitos | 6 |
| Ventana validación | ±1 período (±30s) |
| Longitud secreto | 20 caracteres Base32 (160 bits) |

#### Flujo de activación
1. Usuario va a `/seguridad.php` → clic "Activar 2FA"
2. API genera secreto aleatorio → almacena en sesión
3. Se muestra QR code (via qrcode-generator.js) + secreto manual
4. Usuario escanea con su app → ingresa código de 6 dígitos
5. API verifica código → si correcto, guarda secreto en BD y activa `totp_enabled`
6. Audit log registra evento `2fa_enabled`

#### Flujo de login con 2FA
1. Usuario ingresa email + contraseña → validación normal
2. Si `totp_enabled = 1`:
   - Se establece `$_SESSION['2fa_pending'] = true`
   - Se muestra formulario de código 2FA
   - `is_logged_in()` retorna `false` mientras 2FA está pendiente
3. Usuario ingresa código → verificación TOTP
4. Si correcto: `$_SESSION['2fa_pending']` se elimina → acceso completo
5. Si incorrecto: error, puede reintentar

#### Flujo de desactivación
- Requiere confirmación con contraseña actual
- Audit log registra evento `2fa_disabled`

#### Reset por administrador
- Admin puede resetear 2FA de cualquier usuario (POST `?action=2fa_admin_reset`)
- Audit log registra `2fa_admin_reset` con ID del admin que ejecutó

---

### 4. Dashboard de Seguridad

**URL:** `/seguridad.php`  
**API:** `/api/seguridad.php`

#### Vista de todos los usuarios
- Estado 2FA (activado/desactivado)
- Configuración guiada con QR code
- Panel de información sobre protecciones activas

#### Vista admin adicional
- KPI: Usuarios con 2FA / Total activos (con %)
- KPI: Logins fallidos últimas 24h
- KPI: Entradas en rate limits
- Tabla de eventos de seguridad recientes (auth + seguridad)

---

## Integración automática

Los controles de seguridad se aplican automáticamente:

- **CSRF**: Se valida en `require_login()` para todos los API endpoints de escritura
- **Rate Limiting**: Se aplica en `require_login()` diferenciando reads vs writes
- **Login**: Rate limiting específico + CSRF en formulario
- **Layout**: Meta tag CSRF inyectado automáticamente en todas las páginas

No se requiere modificar los módulos existentes individualmente.

---

## Migración BD (§3.18)

```sql
CREATE TABLE rate_limits (
    rate_key VARCHAR(200) PRIMARY KEY,
    hits INT UNSIGNED NOT NULL DEFAULT 1,
    window_start DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rl_window (window_start)
);

ALTER TABLE usuarios ADD COLUMN totp_secret VARCHAR(32) NULL;
ALTER TABLE usuarios ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0;
```

---

## Archivos nuevos
| Archivo | Descripción |
|---|---|
| `includes/csrf.php` | Generación y validación de tokens CSRF |
| `includes/rate_limit.php` | Rate limiting con backend MySQL |
| `includes/totp.php` | Implementación TOTP RFC 6238 |
| `modules/api/seguridad.php` | API para gestión 2FA y stats |
| `modules/web/seguridad.php` | UI de seguridad con dashboard |
| `api/seguridad.php` | Wrapper API |
| `seguridad.php` | Wrapper web |

## Archivos modificados
| Archivo | Cambio |
|---|---|
| `includes/auth.php` | Require csrf/rate_limit/totp, enforce en require_login() |
| `includes/layout.php` | Meta tag CSRF, nav entry Seguridad |
| `assets/app.js` | Header X-CSRF-Token en api() |
| `index.php` | CSRF en login, rate limiting, flujo 2FA |
| `install.php` | Migración §3.18 |
