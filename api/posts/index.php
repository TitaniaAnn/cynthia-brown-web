<?php
// api/posts/index.php — Admin CRUD for writing posts.
// Public pages read directly from the DB so they can server-render OG tags;
// this endpoint exists to power the admin UI.
//
// GET    /api/posts/         — list all posts (drafts + published)
// GET    /api/posts/?id=N    — single post with linked project IDs
// POST   /api/posts/         — create
// PUT    /api/posts/?id=N    — update
// DELETE /api/posts/?id=N    — delete
//
// After any write the static RSS feed at public/writing/feed.xml is rebuilt.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/util.php';
require_once __DIR__ . '/../../includes/upload.php';

cors_headers();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

require_admin();
if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
    require_admin_write();
}
$admin = current_admin();

// ── GET ────────────────────────────────────────────────────
if ($method === 'GET') {
    if ($id) {
        $row = load_post($id);
        if (!$row) json_response(['error' => 'Not found'], 404);
        json_response($row);
    }
    $rows = db()->query('
        SELECT id, slug, title, excerpt, cover_image, tags, is_published, published_at, created_at, updated_at
        FROM posts
        ORDER BY COALESCE(published_at, created_at) DESC, id DESC
    ')->fetchAll();
    foreach ($rows as &$r) {
        $r['tags'] = csv_to_array($r['tags']);
        $r['is_published'] = (int)$r['is_published'] === 1;
    }
    json_response($rows);
}

// ── POST — create ──────────────────────────────────────────
if ($method === 'POST') {
    $b   = get_json_body();
    $err = validate_post_body($b);
    if ($err) json_response(['error' => $err], 422);

    $slug = unique_slug($b['slug'] ?? $b['title']);
    $publishedAt = ($b['is_published'] ?? false) ? ($b['published_at'] ?? date('Y-m-d H:i:s')) : null;

    $stmt = db()->prepare('
        INSERT INTO posts (slug, title, excerpt, body_markdown, cover_image, tags, is_published, published_at)
        VALUES (?,?,?,?,?,?,?,?)
    ');
    $stmt->execute([
        $slug,
        trim((string)$b['title']),
        nullable_string($b['excerpt'] ?? ''),
        (string)$b['body_markdown'],
        clean_image_src($b['cover_image'] ?? ''),
        normalize_tags($b['tags'] ?? ''),
        ($b['is_published'] ?? false) ? 1 : 0,
        $publishedAt,
    ]);
    $newId = (int) db()->lastInsertId();
    sync_post_projects($newId, $b['project_ids'] ?? []);

    regenerate_rss();
    audit_log('post.create', $admin['id'], "id={$newId} slug={$slug}");
    json_response(load_post($newId), 201);
}

// ── PUT — update ───────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) json_response(['error' => 'id required'], 422);
    $current = load_post($id);
    if (!$current) json_response(['error' => 'Not found'], 404);
    $b = get_json_body();

    $fields = [];
    $params = [];

    if (array_key_exists('title', $b)) {
        $title = trim((string)$b['title']);
        if ($title === '' || strlen($title) > 255) json_response(['error' => 'invalid title'], 422);
        $fields[] = '`title` = ?'; $params[] = $title;
    }
    if (array_key_exists('slug', $b)) {
        $slug = sanitize_slug($b['slug']);
        if ($slug === '') json_response(['error' => 'invalid slug'], 422);
        if ($slug !== $current['slug']) $slug = unique_slug($slug);
        $fields[] = '`slug` = ?'; $params[] = $slug;
    }
    if (array_key_exists('excerpt', $b)) {
        $fields[] = '`excerpt` = ?'; $params[] = nullable_string($b['excerpt']);
    }
    if (array_key_exists('body_markdown', $b)) {
        $body = (string)$b['body_markdown'];
        if (trim($body) === '') json_response(['error' => 'body_markdown cannot be empty'], 422);
        $fields[] = '`body_markdown` = ?'; $params[] = $body;
    }
    if (array_key_exists('cover_image', $b)) {
        $newCover = clean_image_src($b['cover_image']);
        // If the old cover was an uploaded file and it's being replaced or
        // cleared, unlink it so the upload dir doesn't accumulate orphans.
        $oldCover = (string)($current['cover_image'] ?? '');
        if ($oldCover !== '' && $oldCover !== $newCover) {
            delete_local_upload($oldCover);
        }
        $fields[] = '`cover_image` = ?'; $params[] = $newCover;
    }
    if (array_key_exists('tags', $b)) {
        $fields[] = '`tags` = ?'; $params[] = normalize_tags($b['tags']);
    }
    if (array_key_exists('is_published', $b)) {
        $newPub = $b['is_published'] ? 1 : 0;
        $fields[] = '`is_published` = ?'; $params[] = $newPub;
        // Stamp published_at the first time a post flips to published, unless
        // the caller passed an explicit value.
        if ($newPub && (int)$current['is_published'] !== 1 && empty($b['published_at'])) {
            $fields[] = '`published_at` = ?'; $params[] = date('Y-m-d H:i:s');
        }
    }
    if (array_key_exists('published_at', $b)) {
        $val = trim((string)$b['published_at']);
        $fields[] = '`published_at` = ?'; $params[] = $val !== '' ? $val : null;
    }

    if ($fields) {
        $params[] = $id;
        db()->prepare('UPDATE posts SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    }
    if (array_key_exists('project_ids', $b)) {
        sync_post_projects($id, $b['project_ids']);
    }

    regenerate_rss();
    audit_log('post.update', $admin['id'], "id={$id}");
    json_response(load_post($id));
}

// ── DELETE ─────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) json_response(['error' => 'id required'], 422);
    // Snapshot the cover URL before the row goes away so we can unlink it.
    $existing = load_post($id);
    db()->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
    if ($existing && !empty($existing['cover_image'])) {
        delete_local_upload((string)$existing['cover_image']);
    }
    regenerate_rss();
    audit_log('post.delete', $admin['id'], "id={$id}");
    json_response(['success' => true, 'deleted_id' => $id]);
}

json_response(['error' => 'Method not allowed'], 405);

// ── Helpers ────────────────────────────────────────────────
function load_post(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['tags']         = csv_to_array($row['tags']);
    $row['is_published'] = (int)$row['is_published'] === 1;
    $stmt2 = db()->prepare('SELECT project_id FROM post_projects WHERE post_id = ?');
    $stmt2->execute([$id]);
    $row['project_ids'] = array_map('intval', $stmt2->fetchAll(PDO::FETCH_COLUMN));
    return $row;
}

function validate_post_body(array $b): ?string {
    $title = trim((string)($b['title'] ?? ''));
    $body  = (string)($b['body_markdown'] ?? '');
    if ($title === '')         return 'title required';
    if (strlen($title) > 255)  return 'title too long';
    if (trim($body) === '')    return 'body_markdown required';
    return null;
}

function nullable_string($v): ?string {
    $v = trim((string)$v);
    return $v === '' ? null : $v;
}

function normalize_tags($v): string {
    $csv = is_array($v) ? implode(',', array_map(static fn($t) => (string)$t, $v)) : (string)$v;
    return implode(',', csv_to_array($csv));
}

/**
 * Lowercase URL slug: alphanumerics + hyphens, max 120 chars.
 * Strips diacritics so "Café" → "cafe".
 */
function sanitize_slug(string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return substr($s, 0, 120);
}

/** Pick a slug not already taken (appends -2, -3, ... on collision). */
function unique_slug(string $candidate): string {
    $base = sanitize_slug($candidate);
    if ($base === '') $base = 'post';
    $slug = $base;
    $n = 2;
    $stmt = db()->prepare('SELECT 1 FROM posts WHERE slug = ?');
    while (true) {
        $stmt->execute([$slug]);
        if (!$stmt->fetchColumn()) return $slug;
        $slug = $base . '-' . $n++;
        if ($n > 999) return $base . '-' . bin2hex(random_bytes(3));
    }
}

function sync_post_projects(int $postId, $ids): void {
    if (!is_array($ids)) $ids = [];
    $clean = array_values(array_unique(array_map('intval', array_filter($ids, 'is_numeric'))));
    db()->prepare('DELETE FROM post_projects WHERE post_id = ?')->execute([$postId]);
    if (!$clean) return;
    $stmt = db()->prepare('INSERT IGNORE INTO post_projects (post_id, project_id) VALUES (?,?)');
    foreach ($clean as $pid) {
        $stmt->execute([$postId, $pid]);
    }
}

/**
 * Rebuild the static RSS feed at public/writing/feed.xml. Called after every
 * post write. Failures are logged but never block the API response — a stale
 * feed is recoverable, a broken admin save isn't.
 */
function regenerate_rss(): void {
    try {
        $rows = db()->query("
            SELECT slug, title, excerpt, body_markdown, published_at
            FROM posts
            WHERE is_published = 1 AND published_at IS NOT NULL
            ORDER BY published_at DESC
            LIMIT 50
        ")->fetchAll();

        $settings = fetch_settings();
        $author   = $settings['name']   ?? 'Author';
        $tagline  = $settings['tagline'] ?? '';
        $base     = APP_URL;

        require_once __DIR__ . '/../../includes/markdown.php';

        $items = '';
        $latest = null;
        foreach ($rows as $r) {
            if (!$latest) $latest = $r['published_at'];
            $url   = $base . '/writing/' . rawurlencode($r['slug']) . '/';
            $excerpt = $r['excerpt'] ?: markdown_excerpt($r['body_markdown'], 280);
            $pubDate = date(DATE_RSS, strtotime($r['published_at']));
            $items .= "    <item>\n"
                   . '      <title>'       . xml_esc($r['title']) . "</title>\n"
                   . '      <link>'        . xml_esc($url)        . "</link>\n"
                   . '      <guid isPermaLink="true">' . xml_esc($url) . "</guid>\n"
                   . '      <pubDate>'     . $pubDate              . "</pubDate>\n"
                   . '      <description>' . xml_esc($excerpt)    . "</description>\n"
                   . "    </item>\n";
        }
        $lastBuild = $latest ? date(DATE_RSS, strtotime($latest)) : date(DATE_RSS);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n"
             . "  <channel>\n"
             . '    <title>'         . xml_esc($author . ' — Writing') . "</title>\n"
             . '    <link>'          . xml_esc($base . '/writing/')    . "</link>\n"
             . '    <atom:link href="' . xml_esc($base . '/writing/feed.xml') . '" rel="self" type="application/rss+xml" />' . "\n"
             . '    <description>'   . xml_esc($tagline ?: 'Writing')  . "</description>\n"
             . '    <language>en-us</language>' . "\n"
             . '    <lastBuildDate>' . $lastBuild . "</lastBuildDate>\n"
             . $items
             . "  </channel>\n"
             . "</rss>\n";

        $path = dirname(__DIR__, 2) . '/public/writing/feed.xml';
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }
        // Atomic write so a partial file is never served.
        $tmp = $path . '.tmp';
        file_put_contents($tmp, $xml);
        rename($tmp, $path);
    } catch (Throwable $e) {
        error_log('[rss] regenerate failed: ' . $e->getMessage());
    }
}

function xml_esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}
