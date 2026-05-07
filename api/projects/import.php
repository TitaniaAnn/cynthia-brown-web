<?php
// api/projects/import.php — Bulk import projects from JSON (admin only).
// Accepts either a single project object or an array of project objects.
// Skips rows whose title already exists (case-insensitive). Image URLs are
// stored as-is — they only resolve when the export and import live on the
// same instance, which is the supported use case.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/util.php';

cors_headers();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}
$admin = require_admin_write();

$body = get_json_body();
// Allow {projects: [...]}, a bare array, or a single project object.
if (isset($body['projects']) && is_array($body['projects'])) {
    $items = $body['projects'];
} elseif (array_is_list($body)) {
    $items = $body;
} else {
    $items = [$body];
}
if (!$items) json_response(['error' => 'no projects provided'], 422);
if (count($items) > 500) json_response(['error' => 'too many rows (max 500)'], 422);

$existing = [];
foreach (db()->query('SELECT title FROM projects')->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $existing[strtolower($t)] = true;
}

$pdo = db();
$insertProj = $pdo->prepare('
    INSERT INTO projects (title, short_description, description, language, tags, github_url, demo_url, summary_image, status, sort_order, year)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)
');
$insertImg = $pdo->prepare('INSERT INTO project_images (project_id, url, sort_order) VALUES (?,?,?)');

$created = 0;
$skipped = 0;
$errors  = [];

foreach ($items as $i => $p) {
    if (!is_array($p)) { $errors[] = "row $i: not an object"; continue; }
    $title = trim((string)($p['title'] ?? ''));
    $desc  = trim((string)($p['description'] ?? ''));
    $lang  = trim((string)($p['language'] ?? ''));
    if ($title === '' || $desc === '' || $lang === '') {
        $errors[] = "row $i: missing title/description/language";
        continue;
    }
    if (strlen($title) > 255 || strlen($lang) > 100) {
        $errors[] = "row $i: title or language too long";
        continue;
    }
    $key = strtolower($title);
    if (isset($existing[$key])) { $skipped++; continue; }

    $shortDesc = trim((string)($p['short_description'] ?? ''));
    $tagsCsv   = is_array($p['tags'] ?? null)
        ? implode(',', array_map(static fn($t) => (string)$t, $p['tags']))
        : (string)($p['tags'] ?? '');
    $tags      = implode(',', csv_to_array($tagsCsv));
    $github    = clean_url($p['github_url'] ?? '');
    $demo      = clean_url($p['demo_url']   ?? '');
    $summary   = clean_image_src($p['summary_image'] ?? '');
    $status    = in_array($p['status'] ?? '', ['active','wip','archived'], true) ? $p['status'] : 'active';
    $sort      = (int)($p['sort_order'] ?? 0);
    $year      = !empty($p['year']) ? (int)$p['year'] : null;

    try {
        $insertProj->execute([
            $title, $shortDesc !== '' ? $shortDesc : null, $desc, $lang, $tags,
            $github, $demo, $summary, $status, $sort, $year
        ]);
        $newId = (int) $pdo->lastInsertId();
        foreach (string_list($p['images'] ?? []) as $idx => $url) {
            $insertImg->execute([$newId, $url, $idx]);
        }
        $existing[$key] = true;
        $created++;
    } catch (Throwable $e) {
        $errors[] = "row $i: " . $e->getMessage();
    }
}

audit_log('project.import', $admin['id'], "created={$created} skipped={$skipped} errors=" . count($errors));
json_response([
    'success' => true,
    'created' => $created,
    'skipped' => $skipped,
    'errors'  => $errors,
]);
