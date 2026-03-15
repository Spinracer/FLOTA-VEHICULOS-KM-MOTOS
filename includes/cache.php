<?php
// ─────────────────────────────────────────────────────────
// FlotaControl — File-Based Cache System
// ─────────────────────────────────────────────────────────
// Simple file cache with TTL support. No external dependencies.
// Cache files stored in /tmp/flotacontrol_cache/ by default.
// ─────────────────────────────────────────────────────────

define('CACHE_DIR', getenv('CACHE_DIR') ?: '/tmp/flotacontrol_cache');
define('CACHE_ENABLED', (getenv('CACHE_ENABLED') ?: '1') === '1');

// Default TTLs per category (seconds)
const CACHE_TTLS = [
    'dashboard'   => 120,   // 2 min — KPIs and charts
    'alertas'     => 180,   // 3 min — alert scans
    'reportes'    => 300,   // 5 min — reports
    'stats'       => 60,    // 1 min — generic stats
    'catalogo'    => 600,   // 10 min — catalogs rarely change
];

/**
 * Get a cached value. Returns null if missing or expired.
 *
 * @param string $key Cache key (alphanumeric + colons/hyphens)
 * @param string $category Category for TTL lookup
 * @return mixed|null Cached data or null
 */
function cache_get(string $key, string $category = 'stats') {
    if (!CACHE_ENABLED) return null;

    $file = cache_path($key);
    if (!file_exists($file)) return null;

    $data = @file_get_contents($file);
    if ($data === false) return null;

    $entry = @json_decode($data, true);
    if (!is_array($entry) || !isset($entry['expires'], $entry['value'])) {
        // Try legacy unserialize for migration, then delete
        $entry = @unserialize($data);
        if (!is_array($entry) || !isset($entry['expires'], $entry['value'])) {
            @unlink($file);
            return null;
        }
        // Re-save as JSON
        @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    if (time() > $entry['expires']) {
        @unlink($file);
        return null;
    }

    return $entry['value'];
}

/**
 * Store a value in cache.
 *
 * @param string $key Cache key
 * @param mixed $value Data to cache (must be serializable)
 * @param string $category Category for default TTL
 * @param int|null $ttl Override TTL in seconds
 */
function cache_set(string $key, $value, string $category = 'stats', ?int $ttl = null): void {
    if (!CACHE_ENABLED) return;

    $dir = CACHE_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    $ttl = $ttl ?? (CACHE_TTLS[$category] ?? 60);
    $entry = [
        'expires' => time() + $ttl,
        'value'   => $value,
        'created' => time(),
    ];

    $file = cache_path($key);
    @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Invalidate a specific cache key.
 */
function cache_delete(string $key): void {
    $file = cache_path($key);
    if (file_exists($file)) @unlink($file);
}

/**
 * Invalidate all cache entries matching a prefix.
 * Example: cache_invalidate_prefix('dashboard') clears all dashboard caches.
 */
function cache_invalidate_prefix(string $prefix): int {
    $dir = CACHE_DIR;
    if (!is_dir($dir)) return 0;

    $count = 0;
    $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $prefix);
    foreach (glob($dir . '/' . $safePrefix . '*') as $file) {
        if (@unlink($file)) $count++;
    }
    return $count;
}

/**
 * Invalidate ALL cache entries.
 */
function cache_flush(): int {
    $dir = CACHE_DIR;
    if (!is_dir($dir)) return 0;

    $count = 0;
    foreach (glob($dir . '/*') as $file) {
        if (is_file($file) && @unlink($file)) $count++;
    }
    return $count;
}

/**
 * Get or compute: checks cache first, computes and stores if missing.
 *
 * @param string $key Cache key
 * @param callable $compute Function that returns the value to cache
 * @param string $category Category for TTL
 * @param int|null $ttl Override TTL
 * @return mixed Cached or freshly computed value
 */
function cache_remember(string $key, callable $compute, string $category = 'stats', ?int $ttl = null) {
    $cached = cache_get($key, $category);
    if ($cached !== null) return $cached;

    $value = $compute();
    cache_set($key, $value, $category, $ttl);
    return $value;
}

/**
 * Returns cache statistics.
 */
function cache_stats(): array {
    $dir = CACHE_DIR;
    if (!is_dir($dir)) return ['files' => 0, 'size_bytes' => 0, 'expired' => 0, 'active' => 0];

    $files = glob($dir . '/*');
    $total = count($files);
    $size = 0;
    $expired = 0;
    $now = time();

    foreach ($files as $file) {
        if (!is_file($file)) continue;
        $size += filesize($file);
        $data = @file_get_contents($file);
        $entry = @json_decode($data, true) ?: @unserialize($data);
        if (is_array($entry) && isset($entry['expires']) && $now > $entry['expires']) {
            $expired++;
        }
    }

    return [
        'files'      => $total,
        'size_bytes' => $size,
        'size_human' => $size > 1048576 ? round($size / 1048576, 1) . ' MB' : round($size / 1024, 1) . ' KB',
        'expired'    => $expired,
        'active'     => $total - $expired,
    ];
}

/**
 * Cleanup expired entries. Run periodically or on-demand.
 */
function cache_cleanup(): int {
    $dir = CACHE_DIR;
    if (!is_dir($dir)) return 0;

    $count = 0;
    $now = time();
    foreach (glob($dir . '/*') as $file) {
        if (!is_file($file)) continue;
        $data = @file_get_contents($file);
        $entry = @json_decode($data, true) ?: @unserialize($data);
        if (!is_array($entry) || !isset($entry['expires']) || $now > $entry['expires']) {
            if (@unlink($file)) $count++;
        }
    }
    return $count;
}

/**
 * Generates the cache file path for a key.
 */
function cache_path(string $key): string {
    // Sanitize key to safe filename
    $safe = preg_replace('/[^a-zA-Z0-9_:-]/', '_', $key);
    return CACHE_DIR . '/' . $safe . '.cache';
}
