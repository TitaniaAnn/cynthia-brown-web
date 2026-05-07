<?php
// api/projects/index.php — CRUD for projects
// GET    /api/projects/         — list all (public)
// POST   /api/projects/         — create (admin)
// PUT    /api/projects/?id=N    — update (admin)
// PATCH  /api/projects/         — bulk reorder (admin)
// DELETE /api/projects/?id=N    — delete (admin)

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/util.php';
require_once __DIR__ . '/../../includes/upload.php';

cors_headers();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── GET (public) ───────────────────────────────────────────
if ($method === 'GET') {
    $rows = db()->query('SELECT * FROM projects ORDER BY sort_order ASC, created_at DESC')->fetchAll();

    $imgRows = db()->query('SELECT project_id, url FROM project_images ORDER BY project_id, sort_order ASC, id ASC')->fetchAll();
    $imgMap = [];
    foreach ($imgRows as $img) {
        $imgMap[$img['project_id']][] = $img['url'];
    }

    foreach ($rows as &$r) {
        $r['tags']   = csv_to_array($r['tags']);
        $r['images'] = $imgMap[$r['id']] ?? [];
    }
    json_response($rows);
}

// ── All write operations require auth + CSRF ──────────────
$admin = require_admin_write();

// ── POST — create ──────────────────────────────────────────
if ($method === 'POST') {
    $b      = get_json_body();
    $title  = trim($b['title']  ?? '');
    $desc   = trim($b['description'] ?? '');
    $lang   = trim($b['language'] ?? '');
    if ($title === '' || $desc === '' || $lang === '') {
        json_response(['error' => 'title, description, language required'], 422);
    }
    if (strlen($title) > 255 || strlen($lang) > 100) {
        json_response(['error' => 'Field too long'], 422);
    }

    $shortDesc  = trim($b['short_description'] ?? '');
    $tags       = implode(',', csv_to_array(is_array($b['tags'] ?? null) ? implode(',', (array)$b['tags']) : ($b['tags'] ?? '')));
    $github     = clean_url($b['github_url'] ?? '');
    $demo       = clean_url($b['demo_url']   ?? '');
    $summaryImg = clean_image_src($b['summary_image'] ?? '');
    $status     = in_array($b['status'] ?? '', ['active','wip','archived']) ? $b['status'] : 'active';
    $sort       = (int)($b['sort_order'] ?? 0);
    $year       = !empty($b['year']) ? (int)$b['year'] : null;

    $stmt = db()->prepare('
        INSERT INTO projects (title, short_description, description, language, tags, github_url, demo_url, summary_image, status, sort_order, year)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ');
    $stmt->execute([$title, $shortDesc !== '' ? $shortDesc : null, $desc, $lang, $tags, $github, $demo, $summaryImg, $status, $sort, $year]);
    $newId = (int) db()->lastInsertId();

    insert_project_images($newId, string_list($b['images'] ?? []));

    audit_log('project.create', $admin['id'], "id={$newId} title={$title}");
    json_response(load_project($newId), 201);
}

// ── PUT — update ───────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) json_response(['error' => 'id required'], 422);
    $b = get_json_body();

    $fields = [];
    $params = [];

    // Required NOT NULL columns. Reject empties up front so we never feed
    // the DB a value that violates its constraint and 500s.
    $requiredLimits = ['title' => 255, 'description' => 0, 'language' => 100];
    foreach ($requiredLimits as $f => $max) {
        if (!array_key_exists($f, $b)) continue;
        $val = trim((string)$b[$f]);
        if ($val === '') json_response(['error' => "$f cannot be empty"], 422);
        if ($max > 0 && strlen($val) > $max) json_response(['error' => "$f too long"], 422);
        $fields[] = "`$f` = ?";
        $params[] = $val;
    }
    if (array_key_exists('short_description', $b)) {
        $val = trim((string)$b['short_description']);
        $fields[] = '`short_description` = ?';
        $params[] = $val !== '' ? $val : null;
    }
    if (array_key_exists('status', $b)) {
        $val = $b['status'] ?? '';
        if (!in_array($val, ['active','wip','archived'], true)) {
            json_response(['error' => 'invalid status'], 422);
        }
        $fields[] = '`status` = ?';
        $params[] = $val;
    }
    foreach (['github_url','demo_url'] as $f) {
        if (array_key_exists($f, $b)) {
            $fields[] = "`$f` = ?";
            $params[] = clean_url($b[$f]);
        }
    }
    foreach (['sort_order','year'] as $f) {
        if (array_key_exists($f, $b)) {
            $fields[] = "`$f` = ?";
            $params[] = !empty($b[$f]) ? (int)$b[$f] : null;
        }
    }
    if (array_key_exists('tags', $b)) {
        $tags = is_array($b['tags']) ? implode(',', (array)$b['tags']) : (string)$b['tags'];
        $fields[] = '`tags` = ?';
        $params[] = implode(',', csv_to_array($tags));
    }
    if (array_key_exists('summary_image', $b)) {
        $fields[] = '`summary_image` = ?';
        $params[] = clean_image_src($b['summary_image']);
    }

    if ($fields) {
        $params[] = $id;
        db()->prepare('UPDATE projects SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    }

    if (array_key_exists('images', $b)) {
        $newUrls = string_list($b['images']);
        $oldUrls = fetch_images($id);
        db()->prepare('DELETE FROM project_images WHERE project_id = ?')->execute([$id]);
        insert_project_images($id, $newUrls);
        // Drop any uploaded files that aren't being kept — orphaned otherwise.
        $keep = array_flip($newUrls);
        foreach ($oldUrls as $u) {
            if (!isset($keep[$u])) delete_local_upload($u);
        }
    }

    $row = load_project($id);
    if (!$row) json_response(['error' => 'Not found'], 404);

    audit_log('project.update', $admin['id'], "id={$id}");
    json_response($row);
}

// ── PATCH — bulk reorder ───────────────────────────────────
if ($method === 'PATCH') {
    $items = get_json_body();
    if (!is_array($items)) json_response(['error' => 'expected array'], 422);
    $stmt = db()->prepare('UPDATE projects SET sort_order = ? WHERE id = ?');
    $count = 0;
    foreach ($items as $item) {
        if (is_array($item) && isset($item['id'], $item['sort_order'])) {
            $stmt->execute([(int)$item['sort_order'], (int)$item['id']]);
            $count++;
        }
    }
    audit_log('project.reorder', $admin['id'], "rows={$count}");
    json_response(['success' => true]);
}

// ── DELETE ──────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) json_response(['error' => 'id required'], 422);
    // Snapshot file URLs before the FK cascade drops project_images rows.
    $imgUrls = fetch_images($id);
    db()->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
    foreach ($imgUrls as $u) delete_local_upload($u);
    audit_log('project.delete', $admin['id'], "id={$id}");
    json_response(['success' => true, 'deleted_id' => $id]);
}

json_response(['error' => 'Method not allowed'], 405);

// ── Helpers ────────────────────────────────────────────────
function load_project(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM projects WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['tags']   = csv_to_array($row['tags']);
    $row['images'] = fetch_images($id);
    return $row;
}

function fetch_images(int $project_id): array {
    $stmt = db()->prepare('SELECT url FROM project_images WHERE project_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$project_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function insert_project_images(int $project_id, array $urls): void {
    if (!$urls) return;
    $stmt = db()->prepare('INSERT INTO project_images (project_id, url, sort_order) VALUES (?,?,?)');
    foreach ($urls as $i => $url) {
        $stmt->execute([$project_id, $url, $i]);
    }
}
