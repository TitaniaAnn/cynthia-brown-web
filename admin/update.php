<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/util.php';

$admin = current_admin();
if (!$admin) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

// ── Migration definitions ────────────────────────────────────────────────
// Each migration: a check() that returns true when already applied, and a
// sql string to run when pending. Helper functions (column_exists,
// table_exists, index_exists) live in includes/util.php.

$migrations = [
    [
        'id'     => 'drop_image_url',
        'label'  => 'Remove legacy image_url column from projects',
        'detail' => 'Drops the old single-image column replaced by project_images.',
        'check'  => fn() => !column_exists('projects', 'image_url'),
        'sql'    => "ALTER TABLE projects DROP COLUMN image_url",
    ],
    [
        'id'     => 'create_project_images',
        'label'  => 'Create project_images table',
        'detail' => 'One-to-many gallery images linked to each project.',
        'check'  => fn() => table_exists('project_images'),
        'sql'    => "CREATE TABLE IF NOT EXISTS project_images (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            url        VARCHAR(512) NOT NULL,
            sort_order INT DEFAULT 0,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        )",
    ],
    [
        'id'     => 'add_short_description',
        'label'  => 'Add short_description column to projects',
        'detail' => 'Short summary shown on project cards.',
        'check'  => fn() => column_exists('projects', 'short_description'),
        'sql'    => "ALTER TABLE projects ADD COLUMN short_description TEXT AFTER title",
    ],
    [
        'id'     => 'add_summary_image',
        'label'  => 'Add summary_image column to projects',
        'detail' => 'URL of the hero image displayed on the project card.',
        'check'  => fn() => column_exists('projects', 'summary_image'),
        'sql'    => "ALTER TABLE projects ADD COLUMN summary_image VARCHAR(512) AFTER demo_url",
    ],
    [
        'id'     => 'create_skill_groups',
        'label'  => 'Create skill_groups table',
        'detail' => 'Stores the grouped skill tags shown in the About section.',
        'check'  => fn() => table_exists('skill_groups'),
        'sql'    => "CREATE TABLE IF NOT EXISTS skill_groups (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            label      VARCHAR(255) NOT NULL,
            skills     TEXT,
            sort_order INT DEFAULT 0
        )",
    ],
    [
        'id'     => 'add_year',
        'label'  => 'Add year column to projects',
        'detail' => 'Year the project was created or shipped.',
        'check'  => fn() => column_exists('projects', 'year'),
        'sql'    => "ALTER TABLE projects ADD COLUMN year YEAR NULL AFTER sort_order",
    ],
    [
        'id'     => 'add_session_csrf_token',
        'label'  => 'Add csrf_token column to sessions',
        'detail' => 'Per-session CSRF token used to validate state-changing requests.',
        'check'  => fn() => column_exists('sessions', 'csrf_token'),
        'sql'    => "ALTER TABLE sessions ADD COLUMN csrf_token VARCHAR(128) NOT NULL DEFAULT '' AFTER admin_id",
    ],
    [
        'id'     => 'create_audit_log',
        'label'  => 'Create audit_log table',
        'detail' => 'Records security-relevant events (logins, denials, admin writes).',
        'check'  => fn() => table_exists('audit_log'),
        'sql'    => "CREATE TABLE IF NOT EXISTS audit_log (
            id         BIGINT AUTO_INCREMENT PRIMARY KEY,
            admin_id   INT NULL,
            action     VARCHAR(64) NOT NULL,
            detail     VARCHAR(1024) NULL,
            ip         VARCHAR(64) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_log_created (created_at),
            INDEX idx_audit_log_admin   (admin_id)
        )",
    ],
    [
        'id'     => 'index_sessions_expires',
        'label'  => 'Index sessions(expires_at)',
        'detail' => 'Speeds up the session-validity check on every authenticated request.',
        'check'  => fn() => index_exists('sessions', 'idx_sessions_expires'),
        'sql'    => "ALTER TABLE sessions ADD INDEX idx_sessions_expires (expires_at)",
    ],
    [
        'id'     => 'index_projects_sort',
        'label'  => 'Index projects(sort_order)',
        'detail' => 'Speeds up the public project listing.',
        'check'  => fn() => index_exists('projects', 'idx_projects_sort'),
        'sql'    => "ALTER TABLE projects ADD INDEX idx_projects_sort (sort_order)",
    ],
    [
        'id'     => 'index_skill_groups_sort',
        'label'  => 'Index skill_groups(sort_order)',
        'detail' => 'Speeds up the About-section skill query.',
        'check'  => fn() => index_exists('skill_groups', 'idx_skill_groups_sort'),
        'sql'    => "ALTER TABLE skill_groups ADD INDEX idx_skill_groups_sort (sort_order)",
    ],
    [
        'id'     => 'index_project_images_project',
        'label'  => 'Index project_images(project_id)',
        'detail' => 'Speeds up the per-project image lookup.',
        'check'  => fn() => index_exists('project_images', 'idx_project_images_project'),
        'sql'    => "ALTER TABLE project_images ADD INDEX idx_project_images_project (project_id)",
    ],
    [
        'id'     => 'create_posts',
        'label'  => 'Create posts table',
        'detail' => 'Stores writing entries — title, slug, markdown body, tags, publish state.',
        'check'  => fn() => table_exists('posts'),
        'sql'    => "CREATE TABLE IF NOT EXISTS posts (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            slug          VARCHAR(160) NOT NULL UNIQUE,
            title         VARCHAR(255) NOT NULL,
            excerpt       TEXT NULL,
            body_markdown MEDIUMTEXT NOT NULL,
            cover_image   VARCHAR(512) NULL,
            tags          VARCHAR(512) NOT NULL DEFAULT '',
            is_published  TINYINT(1) NOT NULL DEFAULT 0,
            published_at  DATETIME NULL,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_posts_pub (is_published, published_at)
        )",
    ],
    [
        'id'     => 'create_post_projects',
        'label'  => 'Create post_projects link table',
        'detail' => 'Many-to-many link between writing posts and projects.',
        'check'  => fn() => table_exists('post_projects'),
        'sql'    => "CREATE TABLE IF NOT EXISTS post_projects (
            post_id    INT NOT NULL,
            project_id INT NOT NULL,
            PRIMARY KEY (post_id, project_id),
            FOREIGN KEY (post_id)    REFERENCES posts(id)    ON DELETE CASCADE,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            INDEX idx_post_projects_project (project_id)
        )",
    ],
];

// CSRF token for the form. Issued before the POST handler runs so a
// fabricated request can't slip past with an empty session token —
// hash_equals('', '') returns true, which would otherwise be a bypass.
session_start_secure();
if (empty($_SESSION['csrf_form_token'])) {
    $_SESSION['csrf_form_token'] = bin2hex(random_bytes(32));
}
$csrfFormToken = $_SESSION['csrf_form_token'];

// ── Run pending migrations on POST ────────────────────────────────────────
$run_results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run') {
    $supplied = $_POST['csrf_form_token'] ?? '';
    if ($supplied === '' || !hash_equals($csrfFormToken, $supplied)) {
        http_response_code(403);
        die('CSRF token mismatch. Reload the page and try again.');
    }
    foreach ($migrations as $m) {
        if (($m['check'])()) {
            $run_results[$m['id']] = ['status' => 'skipped', 'msg' => 'Already applied — skipped'];
            continue;
        }
        try {
            db()->exec($m['sql']);
            $run_results[$m['id']] = ['status' => 'ok', 'msg' => 'Applied successfully'];
            audit_log('migration.applied', $admin['id'], $m['id']);
        } catch (PDOException $e) {
            error_log("[migration {$m['id']}] " . $e->getMessage());
            $run_results[$m['id']] = ['status' => 'error', 'msg' => $e->getMessage()];
            audit_log('migration.failed', $admin['id'], $m['id'] . ': ' . $e->getMessage());
        }
    }
}

// ── Evaluate current status for display ──────────────────────────────────
foreach ($migrations as &$m) {
    $m['applied'] = ($m['check'])();
}
unset($m);

$pending_count = count(array_filter($migrations, fn($m) => !$m['applied']));
$total         = count($migrations);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Database Migrations — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:#0d0d0d; --bg2:#141414; --bg3:#1a1a1a; --border:#2a2a2a;
    --amber:#f59e0b; --amber-dim:#b45309; --amber-glow:rgba(245,158,11,0.12);
    --text:#e8e0d0; --muted:#555;
    --green:#4ade80; --red:#f87171; --blue:#60a5fa;
    --mono:'JetBrains Mono',monospace; --display:'Syne',sans-serif;
  }
  *{margin:0;padding:0;box-sizing:border-box}
  body{background:var(--bg);color:var(--text);font-family:var(--mono);min-height:100vh}
  body::before{content:'';position:fixed;inset:0;pointer-events:none;background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,0.02) 2px,rgba(0,0,0,0.02) 4px)}

  .topbar{position:fixed;top:0;left:0;right:0;z-index:100;height:52px;padding:0 1.5rem;display:flex;align-items:center;justify-content:space-between;background:rgba(13,13,13,0.96);backdrop-filter:blur(12px);border-bottom:1px solid var(--border)}
  .tb-left{display:flex;align-items:center;gap:1rem}
  .tb-logo{font-family:var(--display);font-size:1rem;font-weight:800;color:var(--amber)}
  .tb-breadcrumb{font-size:0.65rem;color:var(--muted);letter-spacing:0.1em}
  .tb-right{display:flex;align-items:center;gap:1rem}
  .btn-sm{background:transparent;border:1px solid var(--border);color:var(--muted);font-family:var(--mono);font-size:0.62rem;letter-spacing:0.1em;padding:0.3rem 0.7rem;cursor:pointer;text-transform:uppercase;text-decoration:none;transition:all 0.2s}
  .btn-sm:hover,.btn-sm:focus-visible{border-color:var(--amber);color:var(--amber);outline:none}

  .main{padding:5rem 2rem 4rem;max-width:820px;margin:0 auto}
  .page-title{font-family:var(--display);font-size:1.5rem;font-weight:700;margin-bottom:0.3rem}
  .page-sub{font-size:0.72rem;color:var(--muted);margin-bottom:2rem}

  .summary{display:flex;align-items:center;gap:1.5rem;background:var(--bg2);border:1px solid var(--border);padding:1rem 1.2rem;margin-bottom:2rem}
  .summary-num{font-family:var(--display);font-size:1.5rem;font-weight:800;color:var(--amber);margin-right:0.3rem}
  .summary-label{font-size:0.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.1em}
  .summary-divider{width:1px;height:30px;background:var(--border)}

  .migration-list{display:flex;flex-direction:column;gap:0.6rem;margin-bottom:2rem}
  .migration-row{background:var(--bg2);border:1px solid var(--border);padding:1rem 1.2rem;display:flex;align-items:flex-start;gap:1rem}
  .migration-row.applied{border-left:3px solid var(--green)}
  .migration-row.pending{border-left:3px solid var(--amber-dim)}
  .migration-row.error{border-left:3px solid var(--red)}
  .m-badge{font-size:0.58rem;letter-spacing:0.1em;text-transform:uppercase;padding:0.15rem 0.5rem;flex-shrink:0;margin-top:0.15rem}
  .m-badge.applied{color:var(--green);border:1px solid var(--green)}
  .m-badge.pending{color:var(--amber);border:1px solid var(--amber-dim)}
  .m-badge.error{color:var(--red);border:1px solid var(--red)}
  .m-badge.skipped{color:var(--muted);border:1px solid var(--border)}
  .m-body{flex:1;min-width:0}
  .m-label{font-size:0.8rem;font-weight:600;margin-bottom:0.2rem}
  .m-detail{font-size:0.68rem;color:var(--muted);line-height:1.5}
  .m-result{font-size:0.68rem;margin-top:0.4rem;word-break:break-word}
  .m-result.ok{color:var(--green)}
  .m-result.error{color:var(--red)}
  .m-result.skipped{color:var(--muted)}

  .btn-run{background:var(--amber);color:#0d0d0d;border:none;padding:0.75rem 2rem;font-family:var(--mono);font-size:0.78rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;cursor:pointer;transition:all 0.2s}
  .btn-run:hover,.btn-run:focus-visible{background:#fbbf24;outline:2px solid var(--amber);outline-offset:2px}
  .btn-run:disabled{opacity:0.4;cursor:not-allowed}
  .all-done{background:var(--bg2);border:1px solid var(--green);color:var(--green);padding:1rem 1.2rem;font-size:0.78rem;display:flex;align-items:center;gap:0.6rem}
</style>
</head>
<body>

<div class="topbar">
  <div class="tb-left">
    <div class="tb-logo">⚙ Admin</div>
    <div class="tb-breadcrumb">/ database migrations</div>
  </div>
  <div class="tb-right">
    <a href="/admin/" class="btn-sm">← Dashboard</a>
    <form action="/api/auth/logout.php" method="post" style="display:inline">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($admin['csrf_token'] ?? '') ?>">
      <button type="submit" class="btn-sm">Logout</button>
    </form>
  </div>
</div>

<div class="main">
  <h1 class="page-title">Database Migrations</h1>
  <div class="page-sub">Apply schema changes to bring the database up to date with the current codebase.</div>

  <div class="summary">
    <div>
      <span class="summary-num"><?= $total - $pending_count ?></span>
      <span class="summary-label">Applied</span>
    </div>
    <div class="summary-divider"></div>
    <div>
      <span class="summary-num" style="color:<?= $pending_count ? 'var(--amber)' : 'var(--green)' ?>"><?= $pending_count ?></span>
      <span class="summary-label">Pending</span>
    </div>
    <div class="summary-divider"></div>
    <div>
      <span class="summary-num" style="color:var(--muted)"><?= $total ?></span>
      <span class="summary-label">Total</span>
    </div>
  </div>

  <div class="migration-list">
    <?php foreach ($migrations as $m):
      $result   = $run_results[$m['id']] ?? null;
      $rowClass = $result ? ($result['status'] === 'error' ? 'error' : 'applied') : ($m['applied'] ? 'applied' : 'pending');
      $badge    = $result ? $result['status'] : ($m['applied'] ? 'applied' : 'pending');
    ?>
    <div class="migration-row <?= $rowClass ?>">
      <span class="m-badge <?= $badge ?>"><?= $badge ?></span>
      <div class="m-body">
        <div class="m-label"><?= htmlspecialchars($m['label']) ?></div>
        <div class="m-detail"><?= htmlspecialchars($m['detail']) ?></div>
        <?php if ($result): ?>
        <div class="m-result <?= $result['status'] ?>"><?= htmlspecialchars($result['msg']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($pending_count === 0 && !$run_results): ?>
  <div class="all-done">✓ All migrations are applied — the database is up to date.</div>
  <?php else: ?>
  <form method="POST">
    <input type="hidden" name="action" value="run">
    <input type="hidden" name="csrf_form_token" value="<?= htmlspecialchars($csrfFormToken) ?>">
    <button type="submit" class="btn-run" <?= $pending_count === 0 ? 'disabled' : '' ?>>
      ▶ Run <?= $pending_count ?> Pending Migration<?= $pending_count !== 1 ? 's' : '' ?>
    </button>
  </form>
  <?php endif; ?>
</div>

</body>
</html>
