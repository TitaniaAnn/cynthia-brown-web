<?php
// includes/util.php — Small shared helpers used across endpoints.

require_once __DIR__ . '/db.php';

/**
 * Convert a comma-separated string into a trimmed array of non-empty pieces.
 * Used for projects.tags and skill_groups.skills.
 */
function csv_to_array(?string $csv): array {
    if ($csv === null || $csv === '') return [];
    $parts = array_map('trim', explode(',', $csv));
    return array_values(array_filter($parts, static fn($s) => $s !== ''));
}

/**
 * Validate that a value is a list of strings (used for project image URLs).
 * Non-string entries are dropped silently; the result is always a clean array.
 */
function string_list($value, int $maxItems = 64): array {
    if (!is_array($value)) return [];
    $out = [];
    foreach ($value as $v) {
        if (!is_string($v)) continue;
        $v = trim($v);
        if ($v === '') continue;
        $out[] = $v;
        if (count($out) >= $maxItems) break;
    }
    return $out;
}

/**
 * Validate a URL submitted from the admin. Returns the trimmed URL on success
 * or null on empty / invalid / overlong / non-http(s) input. Caps at 500
 * chars to fit the VARCHAR(512) columns with room for query params.
 *
 * The scheme whitelist is defense-in-depth: stored values land in href and
 * src attributes (project links, post cover images), so allowing only
 * http(s) keeps anything weird out — `javascript:` and `data:` are already
 * rejected by FILTER_VALIDATE_URL because they have no `://`, but ftp://,
 * file://, gopher:// etc. would otherwise pass.
 */
function clean_url(?string $url): ?string {
    if ($url === null) return null;
    $url = trim($url);
    if ($url === '') return null;
    if (strlen($url) > 500) return null;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return null;
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if ($scheme !== 'http' && $scheme !== 'https') return null;
    return $url;
}

/**
 * Validate an image source — used for cover_image / summary_image fields.
 * Accepts EITHER:
 *   - a root-relative path under /uploads/ (what api/uploads/ returns), OR
 *   - an http(s) URL (admin pasting an external image link).
 *
 * clean_url() alone breaks the upload flow because FILTER_VALIDATE_URL
 * requires a scheme — "/uploads/projects/abc.png" gets rejected and the
 * field is silently stored as NULL. Don't use clean_url() for these.
 */
function clean_image_src(?string $src): ?string {
    if ($src === null) return null;
    $src = trim($src);
    if ($src === '') return null;
    if (strlen($src) > 500) return null;

    if (str_starts_with($src, '/uploads/')) {
        // Reject any traversal attempt or non-filename chars. The path is
        // re-resolved against disk by delete_local_upload() too, but a
        // tight regex here keeps obviously-bad values out of the DB.
        if (str_contains($src, '..')) return null;
        return preg_match('#^/uploads/[A-Za-z0-9._/-]+$#', $src) ? $src : null;
    }
    return clean_url($src);
}

/** True iff the named column exists in the named table on the active DB. */
function column_exists(string $table, string $column): bool {
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

/** True iff the named table exists on the active DB. */
function table_exists(string $table): bool {
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

/** True iff the named index exists on the named table. */
function index_exists(string $table, string $indexName): bool {
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
    );
    $stmt->execute([$table, $indexName]);
    return (bool) $stmt->fetchColumn();
}

/** Return all settings rows as a flat key=>value associative array. */
function fetch_settings(): array {
    $rows = db()->query('SELECT `key`, `value` FROM settings')->fetchAll();
    $out  = [];
    foreach ($rows as $r) $out[$r['key']] = $r['value'];
    return $out;
}

/**
 * Append an audit log row. Never throws — failures are silent so a logging
 * outage cannot prevent a write from succeeding.
 *
 * $action: short verb-like identifier ('login.success', 'project.create', ...).
 * $detail: free-form context string (max 1000 chars, longer is truncated).
 */
function audit_log(string $action, ?int $adminId = null, ?string $detail = null): void {
    try {
        if (!table_exists('audit_log')) return;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if ($detail !== null && strlen($detail) > 1000) {
            $detail = substr($detail, 0, 1000);
        }
        db()->prepare(
            'INSERT INTO audit_log (admin_id, action, detail, ip) VALUES (?,?,?,?)'
        )->execute([$adminId, $action, $detail, $ip]);
    } catch (Throwable $e) {
        // swallow — audit must never break the main flow
    }
}
