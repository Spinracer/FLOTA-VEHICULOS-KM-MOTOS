<?php
// ─────────────────────────────────────────────────────────
// FlotaControl — CSRF Protection
// ─────────────────────────────────────────────────────────
// Generates and validates CSRF tokens per session.
// Token is stored in $_SESSION and emitted as meta tag + header.
// ─────────────────────────────────────────────────────────

/**
 * Generates or returns the current CSRF token for the session.
 */
function csrf_token(): string {
    session_init();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Returns an HTML meta tag with the CSRF token (for layout injection).
 */
function csrf_meta(): string {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<meta name="csrf-token" content="' . $token . '">';
}

/**
 * Returns a hidden input field for traditional form POST.
 */
function csrf_field(): string {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
}

/**
 * Validates the CSRF token from header or POST body.
 * Returns true if valid, false otherwise.
 */
function csrf_validate(): bool {
    session_init();
    $expected = $_SESSION['csrf_token'] ?? '';
    if ($expected === '') return false;

    // Check X-CSRF-Token header first (AJAX requests)
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($headerToken !== '' && hash_equals($expected, $headerToken)) {
        return true;
    }

    // Check POST body fallback (traditional forms)
    $bodyToken = $_POST['_csrf_token'] ?? '';
    if ($bodyToken !== '' && hash_equals($expected, $bodyToken)) {
        return true;
    }

    return false;
}

/**
 * Enforces CSRF validation on write methods (POST/PUT/PATCH/DELETE).
 * GET and OPTIONS requests are always allowed.
 * Returns 403 JSON error if token is missing or invalid.
 */
function csrf_enforce(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }
    if (!csrf_validate()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Token CSRF inválido o ausente. Recarga la página.']);
        exit;
    }
}

/**
 * Regenerates the CSRF token (use after login to prevent fixation).
 */
function csrf_regenerate(): void {
    session_init();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
