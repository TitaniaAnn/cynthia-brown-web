<?php
// api/projects/import.php — Bulk import projects from JSON (admin only).
// Accepts a single project object, a bare array, or {projects: [...]}.
// New titles are inserted; existing titles (case-insensitive match) are
// diffed against the import payload — rows whose data has changed are
// updated, otherwise they are skipped. Image URLs are stored as-is —
// they only resolve when the export and import live on the same instance,
// which is the supported use case.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/util.php';
require_once __DIR__ . '/../../includes/upload.php';

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

$pdo = db();

// Snapshot existing projects keyed by lower-cased title so we can decide
// insert-vs-update without an extra round trip per row.
$existing = [];
foreach ($pdo->query('SELECT * FROM projects')->fetchAll() as $row) {
    $existing[strtolower($row['title'])] = $row;
}

// Pre-load images for those projects in a single query, grouped by project_id.
$imagesByProject = [];
if ($existing) {
    $imgRows = $pdo->query(
        'SELECT project_id, url FROM project_images
         ORDER BY project_id, sort_order ASC, id ASC'
    )->fetchAll();
    foreach ($imgRows as $r) {
        $imagesByProject[(int)$r['project_id']][] = $r['url'];
    }
}

$insertProj = $pdo->prepare('
    INSERT INTO projects (title, short_description, description, language, tags, github_url, demo_url, summary_image, status, sort_order, year)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)
');
$updateProj = $pdo->prepare('
    UPDATE projects
       SET title = ?, short_description = ?, description = ?, language = ?,
           tags = ?, github_url = ?, demo_url = ?, summary_image = ?,
           status = ?, sort_order = ?, year = ?
     WHERE id = ?
');
$insertImg  = $pdo->prepare('INSERT INTO project_images (project_id, url, sort_order) VALUES (?,?,?)');
$deleteImgs = $pdo->prepare('DELETE FROM project_images WHERE project_id = ?');

$created = 0;
$updated = 0;
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

    $shortRaw  = trim((string)($p['short_description'] ?? ''));
    $shortDesc = $shortRaw !== '' ? $shortRaw : null;
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
    $images    = string_list($p['images'] ?? []);

    $key = strtolower($title);
    $existingRow = $existing[$key] ?? null;

    try {
        if ($existingRow === null) {
            $insertProj->execute([
                $title, $shortDesc, $desc, $lang, $tags,
                $github, $demo, $summary, $status, $sort, $year
            ]);
            $newId = (int) $pdo->lastInsertId();
            foreach ($images as $idx => $url) {
                $insertImg->execute([$newId, $url, $idx]);
            }
            // Track in the snapshot so a duplicate title later in the same
            // payload routes to update instead of inserting again.
            $existing[$key] = [
                'id'                => $newId,
                'title'             => $title,
                'short_description' => $shortDesc,
                'description'       => $desc,
                'language'          => $lang,
                'tags'              => $tags,
                'github_url'        => $github,
                'demo_url'          => $demo,
                'summary_image'     => $summary,
                'status'            => $status,
                'sort_order'        => $sort,
                'year'              => $year,
            ];
            $imagesByProject[$newId] = $images;
            $created++;
            continue;
        }

        $existingId     = (int) $existingRow['id'];
        $existingImages = $imagesByProject[$existingId] ?? [];

        $rowChanged =
            (string) $existingRow['title']                       !== $title  ||
            (string) ($existingRow['short_description'] ?? '')   !== (string) ($shortDesc ?? '') ||
            (string) $existingRow['description']                 !== $desc   ||
            (string) $existingRow['language']                    !== $lang   ||
            (string) ($existingRow['tags'] ?? '')                !== $tags   ||
            (string) ($existingRow['github_url']    ?? '')       !== (string) ($github  ?? '') ||
            (string) ($existingRow['demo_url']      ?? '')       !== (string) ($demo    ?? '') ||
            (string) ($existingRow['summary_image'] ?? '')       !== (string) ($summary ?? '') ||
            (string) $existingRow['status']                      !== $status ||
            (int)    $existingRow['sort_order']                  !== $sort   ||
            ($existingRow['year'] !== null ? (int) $existingRow['year'] : null) !== $year;

        $imagesChanged = $existingImages !== $images;

        if (!$rowChanged && !$imagesChanged) {
            $skipped++;
            continue;
        }

        if ($rowChanged) {
            $updateProj->execute([
                $title, $shortDesc, $desc, $lang, $tags,
                $github, $demo, $summary, $status, $sort, $year,
                $existingId,
            ]);
        }
        if ($imagesChanged) {
            $deleteImgs->execute([$existingId]);
            foreach ($images as $idx => $url) {
                $insertImg->execute([$existingId, $url, $idx]);
            }
            // Drop any local upload files that aren't being kept — mirrors
            // PUT /api/projects/?id so re-imports don't leak orphaned files.
            $keep = array_flip($images);
            foreach ($existingImages as $u) {
                if (!isset($keep[$u])) delete_local_upload($u);
            }
            $imagesByProject[$existingId] = $images;
        }
        // Refresh the snapshot so a later duplicate of this title in the same
        // payload compares against the values we just wrote.
        $existing[$key] = array_merge($existingRow, [
            'title'             => $title,
            'short_description' => $shortDesc,
            'description'       => $desc,
            'language'          => $lang,
            'tags'              => $tags,
            'github_url'        => $github,
            'demo_url'          => $demo,
            'summary_image'     => $summary,
            'status'            => $status,
            'sort_order'        => $sort,
            'year'              => $year,
        ]);
        $updated++;
    } catch (Throwable $e) {
        $errors[] = "row $i: " . $e->getMessage();
    }
}

audit_log(
    'project.import',
    $admin['id'],
    "created={$created} updated={$updated} skipped={$skipped} errors=" . count($errors)
);
json_response([
    'success' => true,
    'created' => $created,
    'updated' => $updated,
    'skipped' => $skipped,
    'errors'  => $errors,
]);
