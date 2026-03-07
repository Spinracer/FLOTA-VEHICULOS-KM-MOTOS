<?php
// ─────────────────────────────────────────────────────────
// FlotaControl — Rate Limiting
// ─────────────────────────────────────────────────────────
// Database-backed rate limiter using rate_limits table.
// Supports configurable windows and limits per action.
// ─────────────────────────────────────────────────────────

// Default limits: [max_requests, window_seconds]
const RATE_LIMITS = [
    'login'     => [5, 60],      // 5 attempts per minute
    'api_write' => [60, 60],     // 60 writes per minute
    'api_read'  => [120, 60],    // 120 reads per minute
];

/**
 * Checks and enforces rate limiting for a given action.
 *
 * @param string $action  Action key (e.g., 'login', 'api_write', 'api_read')
 * @param string|null $identifier  Unique identifier (IP, user_id, etc). Defaults to IP.
 * @return bool True if within limits, false if exceeded.
 */
function rate_limit_check(string $action, ?string $identifier = null): bool {
    $identifier = $identifier ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $limits = RATE_LIMITS[$action] ?? [60, 60];
    [$maxRequests, $windowSeconds] = $limits;

    $key = $action . ':' . $identifier;

    try {
        $db = getDB();

        // Clean old entries periodically (1% chance per request to avoid overhead)
        if (random_int(1, 100) === 1) {
            $db->exec("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        }

        // Get or create rate limit entry
        $stmt = $db->prepare(
            "SELECT hits, window_start FROM rate_limits WHERE rate_key = ? LIMIT 1"
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        $now = time();

        if ($row) {
            $windowStart = strtotime($row['window_start']);
            if ($now - $windowStart > $windowSeconds) {
                // Window expired — reset
                $db->prepare(
                    "UPDATE rate_limits SET hits = 1, window_start = NOW() WHERE rate_key = ?"
                )->execute([$key]);
                return true;
            }
            if ((int)$row['hits'] >= $maxRequests) {
                return false; // Rate limit exceeded
            }
            // Increment hits
            $db->prepare(
                "UPDATE rate_limits SET hits = hits + 1 WHERE rate_key = ?"
            )->execute([$key]);
            return true;
        }

        // First request — create entry
        $db->prepare(
            "INSERT INTO rate_limits (rate_key, hits, window_start) VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE hits = 1, window_start = NOW()"
        )->execute([$key]);
        return true;

    } catch (Throwable $e) {
        // If rate_limits table doesn't exist, allow the request (fail-open)
        return true;
    }
}

/**
 * Enforces rate limiting, returning 429 if exceeded.
 *
 * @param string $action Action key
 * @param string|null $identifier Unique identifier
 */
function rate_limit_enforce(string $action, ?string $identifier = null): void {
    if (!rate_limit_check($action, $identifier)) {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: 60');
        echo json_encode(['error' => 'Demasiadas solicitudes. Intenta de nuevo en un momento.']);
        exit;
    }
}

/**
 * Returns remaining requests for a given action.
 */
function rate_limit_remaining(string $action, ?string $identifier = null): int {
    $identifier = $identifier ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $limits = RATE_LIMITS[$action] ?? [60, 60];
    [$maxRequests, $windowSeconds] = $limits;
    $key = $action . ':' . $identifier;

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT hits, window_start FROM rate_limits WHERE rate_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) return $maxRequests;
        $windowStart = strtotime($row['window_start']);
        if (time() - $windowStart > $windowSeconds) return $maxRequests;
        return max(0, $maxRequests - (int)$row['hits']);
    } catch (Throwable $e) {
        return $maxRequests;
    }
}
