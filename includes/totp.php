<?php
// ─────────────────────────────────────────────────────────
// FlotaControl — TOTP Two-Factor Authentication
// ─────────────────────────────────────────────────────────
// Pure PHP implementation of RFC 6238 TOTP (Time-based OTP).
// Compatible with Google Authenticator, Authy, etc.
// ─────────────────────────────────────────────────────────

/**
 * Generates a random Base32-encoded secret key (160 bits).
 */
function totp_generate_secret(int $length = 20): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    $bytes = random_bytes($length);
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[ord($bytes[$i]) % 32];
    }
    return $secret;
}

/**
 * Decodes a Base32-encoded string to raw bytes.
 */
function totp_base32_decode(string $b32): string {
    $lut = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
    $b32 = strtoupper(rtrim($b32, '='));
    $buffer = 0;
    $bitsLeft = 0;
    $result = '';
    for ($i = 0, $len = strlen($b32); $i < $len; $i++) {
        $val = $lut[$b32[$i]] ?? null;
        if ($val === null) continue;
        $buffer = ($buffer << 5) | $val;
        $bitsLeft += 5;
        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $result .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }
    return $result;
}

/**
 * Generates a TOTP code for the given secret and time.
 *
 * @param string $secret Base32-encoded secret
 * @param int|null $time Unix timestamp (defaults to now)
 * @param int $period Time step in seconds (default 30)
 * @param int $digits Number of digits (default 6)
 * @return string Zero-padded OTP code
 */
function totp_generate(string $secret, ?int $time = null, int $period = 30, int $digits = 6): string {
    $time = $time ?? time();
    $counter = (int)floor($time / $period);
    $key = totp_base32_decode($secret);

    // Counter as 8-byte big-endian
    $counterBytes = pack('N*', 0, $counter);

    $hash = hash_hmac('sha1', $counterBytes, $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $code = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    ) % (10 ** $digits);

    return str_pad((string)$code, $digits, '0', STR_PAD_LEFT);
}

/**
 * Verifies a TOTP code with a time window tolerance.
 *
 * @param string $secret Base32-encoded secret
 * @param string $code User-provided code
 * @param int $window Number of periods to check before/after (default 1 = ±30s)
 * @return bool True if code is valid
 */
function totp_verify(string $secret, string $code, int $window = 1): bool {
    $code = trim($code);
    $time = time();
    for ($i = -$window; $i <= $window; $i++) {
        $expected = totp_generate($secret, $time + ($i * 30));
        if (hash_equals($expected, $code)) {
            return true;
        }
    }
    return false;
}

/**
 * Generates the otpauth:// URI for QR code generation.
 *
 * @param string $secret Base32-encoded secret
 * @param string $account User's email or identifier
 * @param string $issuer Application name
 * @return string otpauth:// URI
 */
function totp_uri(string $secret, string $account, string $issuer = 'FlotaControl'): string {
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
        . '?secret=' . $secret
        . '&issuer=' . rawurlencode($issuer)
        . '&digits=6&period=30';
}

/**
 * Generates a data URI for a QR code SVG (no external dependencies).
 * Uses a minimal QR code library implemented inline.
 *
 * Falls back to a text representation if QR generation isn't available.
 *
 * @param string $uri The otpauth:// URI to encode
 * @return string HTML with the QR code display
 */
function totp_qr_html(string $uri, string $secret): string {
    $encodedUri = htmlspecialchars($uri, ENT_QUOTES, 'UTF-8');
    $escapedSecret = htmlspecialchars($secret, ENT_QUOTES, 'UTF-8');
    // Use a JavaScript-based QR renderer (qrcode.js from CDN)
    $id = 'qr-' . bin2hex(random_bytes(4));
    return <<<HTML
<div class="text-center">
  <div id="{$id}" class="inline-block bg-white p-4 rounded-xl mb-4"></div>
  <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
  <script>
  (function(){
    var qr = qrcode(0, 'M');
    qr.addData('{$encodedUri}');
    qr.make();
    document.getElementById('{$id}').innerHTML = qr.createSvgTag(5, 0);
  })();
  </script>
  <div class="mt-3">
    <p class="text-xs text-muted mb-1">O ingresa este código manualmente:</p>
    <code class="text-accent font-mono text-lg tracking-widest select-all">{$escapedSecret}</code>
  </div>
</div>
HTML;
}

/**
 * Checks if 2FA is enabled for a user.
 */
function totp_is_enabled(PDO $db, int $userId): bool {
    $stmt = $db->prepare("SELECT totp_enabled FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return (bool)($stmt->fetchColumn() ?: 0);
}

/**
 * Gets the TOTP secret for a user.
 */
function totp_get_secret(PDO $db, int $userId): ?string {
    $stmt = $db->prepare("SELECT totp_secret FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $val = $stmt->fetchColumn();
    return $val ?: null;
}

/**
 * Enables 2FA for a user (sets secret and enabled flag).
 */
function totp_enable(PDO $db, int $userId, string $secret): bool {
    $stmt = $db->prepare("UPDATE usuarios SET totp_secret = ?, totp_enabled = 1 WHERE id = ?");
    return $stmt->execute([$secret, $userId]);
}

/**
 * Disables 2FA for a user.
 */
function totp_disable(PDO $db, int $userId): bool {
    $stmt = $db->prepare("UPDATE usuarios SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?");
    return $stmt->execute([$userId]);
}
