<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/util.php';

$admin = current_admin();
if (!$admin) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}
$csrfToken = $admin['csrf_token'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
<title>Admin Dashboard</title>
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="icon" href="/favicon.ico">
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

  /* Reusable focus ring for keyboard users — applied to every interactive element. */
  button:focus-visible,a:focus-visible,input:focus-visible,textarea:focus-visible,select:focus-visible,[tabindex]:focus-visible{
    outline:2px solid var(--amber);outline-offset:2px;
  }

  /* TOPBAR */
  .topbar{position:fixed;top:0;left:0;right:0;z-index:100;height:52px;padding:0 1.5rem;display:flex;align-items:center;justify-content:space-between;background:rgba(13,13,13,0.96);backdrop-filter:blur(12px);border-bottom:1px solid var(--border)}
  .tb-left{display:flex;align-items:center;gap:1rem}
  .tb-logo{font-family:var(--display);font-size:1rem;font-weight:800;color:var(--amber)}
  .tb-breadcrumb{font-size:0.65rem;color:var(--muted);letter-spacing:0.1em}
  .tb-right{display:flex;align-items:center;gap:1rem}
  .tb-user{display:flex;align-items:center;gap:0.6rem;font-size:0.72rem;color:var(--muted)}
  .tb-avatar{width:26px;height:26px;border-radius:50%;border:1px solid var(--border);object-fit:cover}
  .btn-sm{background:transparent;border:1px solid var(--border);color:var(--muted);font-family:var(--mono);font-size:0.62rem;letter-spacing:0.1em;padding:0.3rem 0.7rem;cursor:pointer;text-transform:uppercase;text-decoration:none;transition:all 0.2s}
  .btn-sm:hover{border-color:var(--amber);color:var(--amber)}

  /* LAYOUT */
  .layout{display:flex;min-height:100vh;padding-top:52px}
  .sidebar{width:220px;flex-shrink:0;background:var(--bg2);border-right:1px solid var(--border);padding:1.5rem 0;position:fixed;top:52px;bottom:0;overflow-y:auto}
  .main{flex:1;padding:2rem 2rem 4rem;margin-left:220px}
  @media(max-width:700px){.sidebar{display:none}.main{margin-left:0}}

  .nav-item{display:flex;align-items:center;gap:0.6rem;padding:0.65rem 1.2rem;font-size:0.72rem;letter-spacing:0.06em;color:var(--muted);text-decoration:none;transition:all 0.2s;cursor:pointer;border:none;background:none;width:100%;text-align:left}
  .nav-item.active,.nav-item:hover{color:var(--amber);background:var(--amber-glow)}
  .nav-item .nav-icon{width:16px;text-align:center}
  .nav-section{font-size:0.55rem;letter-spacing:0.18em;text-transform:uppercase;color:var(--border);padding:1rem 1.2rem 0.3rem}

  /* PANELS */
  .panel{display:none}.panel.active{display:block}
  .panel-header{margin-bottom:2rem}
  .panel-title{font-family:var(--display);font-size:1.5rem;font-weight:700;margin-bottom:0.3rem}
  .panel-sub{font-size:0.72rem;color:var(--muted)}

  /* STATS ROW */
  .stats-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;margin-bottom:2rem}
  .stat-card{background:var(--bg2);border:1px solid var(--border);padding:1.2rem;position:relative;overflow:hidden}
  .stat-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--amber),transparent)}
  .stat-card-num{font-family:var(--display);font-size:1.8rem;font-weight:800;color:var(--amber)}
  .stat-card-label{font-size:0.63rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.1em;margin-top:0.3rem}

  /* FORMS */
  .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
  @media(max-width:600px){.form-grid{grid-template-columns:1fr}}
  .form-group{margin-bottom:1.2rem}
  .form-group.full{grid-column:1/-1}
  label{display:block;font-size:0.63rem;color:var(--muted);letter-spacing:0.12em;text-transform:uppercase;margin-bottom:0.4rem}
  label .req{color:var(--amber)}
  input,textarea,select{width:100%;background:var(--bg);border:1px solid var(--border);color:var(--text);font-family:var(--mono);font-size:0.8rem;padding:0.6rem 0.8rem;outline:none;transition:border-color 0.2s;resize:vertical}
  input:focus,textarea:focus,select:focus{border-color:var(--amber-dim);box-shadow:0 0 0 2px var(--amber-glow)}
  select option{background:var(--bg2)}
  .hint{font-size:0.6rem;color:var(--muted);margin-top:0.3rem}
  .form-actions{display:flex;gap:0.8rem;align-items:center;margin-top:1rem}
  .btn-save{background:var(--amber);color:#0d0d0d;border:none;padding:0.7rem 1.6rem;font-family:var(--mono);font-size:0.75rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;cursor:pointer;transition:all 0.2s}
  .btn-save:hover{background:#fbbf24}
  .btn-reset{background:transparent;border:1px solid var(--border);color:var(--muted);font-family:var(--mono);font-size:0.7rem;letter-spacing:0.08em;padding:0.7rem 1.2rem;cursor:pointer;text-transform:uppercase;transition:all 0.2s}
  .btn-reset:hover{border-color:var(--muted);color:var(--text)}

  /* PROJECT TABLE */
  .proj-table{width:100%;border-collapse:collapse;margin-top:0.5rem}
  .proj-table th{font-size:0.6rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);padding:0.5rem 0.8rem;border-bottom:1px solid var(--border);text-align:left;font-weight:400}
  .proj-table td{padding:0.9rem 0.8rem;border-bottom:1px solid var(--border);font-size:0.78rem;vertical-align:middle}
  .proj-table tr:hover td{background:var(--bg3)}
  .proj-lang-badge{display:inline-block;font-size:0.58rem;letter-spacing:0.1em;text-transform:uppercase;color:var(--amber);background:var(--amber-glow);padding:0.1rem 0.45rem}
  .status-badge{display:inline-block;font-size:0.56rem;letter-spacing:0.1em;text-transform:uppercase;padding:0.12rem 0.4rem}
  .status-badge.active{color:var(--green);border:1px solid var(--green)}
  .status-badge.wip{color:var(--blue);border:1px solid var(--blue)}
  .status-badge.archived{color:var(--muted);border:1px solid var(--border)}
  .tbl-link-amber{color:var(--amber);text-decoration:none}
  .tbl-link-blue{color:var(--blue);text-decoration:none}
  .tbl-actions{display:flex;gap:0.4rem}
  .tbl-btn{background:none;border:1px solid var(--border);color:var(--muted);font-family:var(--mono);font-size:0.6rem;letter-spacing:0.08em;padding:0.25rem 0.5rem;cursor:pointer;text-transform:uppercase;transition:all 0.2s}
  .tbl-btn:hover{border-color:var(--amber);color:var(--amber)}
  .tbl-btn.del:hover{border-color:var(--red);color:var(--red)}
  .empty-row td{text-align:center;color:var(--muted);padding:3rem}
  .drag-handle{cursor:grab;color:var(--border);font-size:1rem;padding:0.9rem 0.5rem;user-select:none;transition:color 0.2s}
  .drag-handle:hover{color:var(--muted)}
  .proj-table tr.dragging td{opacity:0.35}
  .proj-table tr.drag-over td{border-top:2px solid var(--amber)}

  /* TOAST */
  .toast{position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;background:var(--bg3);border:1px solid var(--green);color:var(--green);font-size:0.75rem;padding:0.7rem 1rem;pointer-events:none;animation:toastIn 0.25s ease}
  @keyframes toastIn{from{transform:translateY(6px);opacity:0}}
  .toast.err{border-color:var(--red);color:var(--red)}

  /* MODAL */
  .modal-overlay{display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,0.8);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:1rem}
  .modal-overlay.open{display:flex}
  .modal{background:var(--bg2);border:1px solid var(--amber-dim);width:100%;max-width:620px;max-height:90vh;overflow-y:auto}
  .modal-header{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid var(--border)}
  .modal-title{font-family:var(--display);font-size:1rem;font-weight:700;color:var(--amber)}
  .modal-close{background:none;border:none;color:var(--muted);font-size:1.1rem;cursor:pointer;font-family:var(--mono);padding:0.2rem 0.4rem;transition:color 0.2s}
  .modal-close:hover{color:var(--red)}
  .modal-body{padding:1.5rem}

  /* IMAGE GALLERY */
  .img-gallery{display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.6rem;min-height:0}
  .img-thumb{position:relative;width:90px;height:70px;flex-shrink:0}
  .img-thumb img{width:100%;height:100%;object-fit:cover;border:1px solid var(--border);display:block}
  .img-thumb-del{position:absolute;top:2px;right:2px;background:rgba(0,0,0,0.75);border:none;color:var(--red);font-size:0.6rem;cursor:pointer;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-family:var(--mono);line-height:1}
  .img-thumb-del:hover{background:var(--red);color:#fff}
  .img-upload-area{border:1px dashed var(--border);padding:0.8rem;cursor:pointer;transition:border-color 0.2s;text-align:center;position:relative}
  .img-upload-area:hover{border-color:var(--amber-dim)}
  .img-upload-area input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
  .img-upload-label{font-size:0.65rem;color:var(--muted);letter-spacing:0.08em}
  .img-uploading{font-size:0.65rem;color:var(--amber);margin-top:0.4rem}
  .img-thumb-star{position:absolute;bottom:2px;right:2px;background:rgba(0,0,0,0.75);border:none;color:var(--muted);font-size:0.72rem;cursor:pointer;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-family:var(--mono);line-height:1;transition:color 0.2s;padding:0}
  .img-thumb-star:hover{color:var(--amber)}
  .img-thumb.is-summary{outline:2px solid var(--amber)}
  .img-thumb.is-summary .img-thumb-star{color:var(--amber)}
  .img-thumb[draggable]{cursor:grab}
  .img-thumb.img-dragging{opacity:0.35}
  .img-thumb.img-drag-over{outline:2px solid var(--amber);opacity:0.6}

  /* SHARED TILE / LIST UTILITIES (used by skills list, resume tile, account tile) */
  .tile{background:var(--bg2);border:1px solid var(--border);padding:1.2rem}
  .tile-pad-sm{padding:1rem 1.2rem}
  .tile-row{display:flex;align-items:flex-start;gap:1rem;background:var(--bg2);border:1px solid var(--border);padding:1rem 1.2rem}
  .tile-row .tile-body{flex:1;min-width:0}
  .tile-label-amber{font-size:0.58rem;letter-spacing:0.16em;text-transform:uppercase;color:var(--amber);margin-bottom:0.5rem}
  .tile-tags{display:flex;flex-wrap:wrap;gap:0.35rem}
  .tile-tag{font-size:0.68rem;color:var(--muted);border:1px solid var(--border);padding:0.15rem 0.5rem}
  .skill-list{display:flex;flex-direction:column;gap:0.6rem;margin-bottom:2rem}
  .add-group-tile{background:var(--bg2);border:1px solid var(--border);padding:1.2rem}
  .add-group-tile-label{font-size:0.63rem;color:var(--amber);letter-spacing:0.14em;text-transform:uppercase;margin-bottom:1rem}
  .resume-tile{display:flex;align-items:center;justify-content:space-between;max-width:400px;background:var(--bg2);border:1px solid var(--border);padding:1rem}
  .resume-tile-label{font-size:0.63rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:0.3rem}
  .resume-link{color:var(--amber);font-size:0.8rem;text-decoration:none}
  .account-tile{background:var(--bg2);border:1px solid var(--border);padding:1.5rem;max-width:400px}
  .account-row{display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem}
  .account-avatar{width:48px;height:48px;border-radius:50%;border:1px solid var(--border)}
  .account-name{font-weight:600;margin-bottom:0.2rem}
  .account-email{font-size:0.72rem;color:var(--muted)}
  .account-meta{font-size:0.68rem;color:var(--muted);border-top:1px solid var(--border);padding-top:1rem}
  .account-meta .row{margin-bottom:0.5rem}
  .account-meta .amber{color:var(--amber)}
  .account-actions{margin-top:1.2rem}

  /* AUDIT LOG */
  .audit-controls{display:flex;gap:0.8rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap}
  .audit-controls input{flex:1;max-width:400px}
  .audit-table{width:100%;border-collapse:collapse;margin-top:0.5rem}
  .audit-table th{font-size:0.55rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);padding:0.5rem 0.7rem;border-bottom:1px solid var(--border);text-align:left;font-weight:400}
  .audit-table td{padding:0.55rem 0.7rem;border-bottom:1px solid var(--border);font-size:0.7rem;vertical-align:top;color:var(--text)}
  .audit-table tr:hover td{background:var(--bg3)}
  .audit-time{font-family:var(--mono);font-size:0.62rem;color:var(--muted);white-space:nowrap}
  .audit-action{display:inline-block;font-size:0.58rem;letter-spacing:0.08em;color:var(--amber);background:var(--amber-glow);padding:0.1rem 0.45rem;font-family:var(--mono)}
  .audit-detail{color:var(--muted);font-size:0.66rem;word-break:break-word;max-width:380px;line-height:1.5}
  .audit-ip{font-family:var(--mono);font-size:0.62rem;color:var(--muted);white-space:nowrap}
  .audit-pager{display:flex;align-items:center;gap:1rem;margin-top:1.2rem;justify-content:center}
  .audit-pager .btn-reset:disabled{opacity:0.3;cursor:not-allowed}
  #audit-page-info{font-size:0.7rem;color:var(--muted)}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <div class="tb-left">
    <div class="tb-logo">⚙ Admin</div>
    <div class="tb-breadcrumb">/ dashboard</div>
  </div>
  <div class="tb-right">
    <div class="tb-user">
      <?php if ($admin['avatar_url']): ?>
      <img src="<?= htmlspecialchars($admin['avatar_url']) ?>" class="tb-avatar" alt="<?= htmlspecialchars($admin['name']) ?> avatar">
      <?php endif; ?>
      <span><?= htmlspecialchars($admin['name']) ?></span>
    </div>
    <a href="/" class="btn-sm">← Site</a>
    <form action="/api/auth/logout.php" method="post" style="display:inline">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <button type="submit" class="btn-sm">Logout</button>
    </form>
  </div>
</div>

<div class="layout">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="nav-section">Content</div>
    <button class="nav-item active" type="button" onclick="showPanel('projects', this)"><span class="nav-icon" aria-hidden="true">◈</span> Projects</button>
    <button class="nav-item" type="button" onclick="showPanel('add-project', this)"><span class="nav-icon" aria-hidden="true">＋</span> Add Project</button>
    <button class="nav-item" type="button" onclick="showPanel('writing', this)"><span class="nav-icon" aria-hidden="true">✎</span> Writing</button>
    <button class="nav-item" type="button" onclick="showPanel('skills', this)"><span class="nav-icon" aria-hidden="true">◧</span> Skills</button>
    <div class="nav-section">Site</div>
    <button class="nav-item" type="button" onclick="showPanel('settings', this)"><span class="nav-icon" aria-hidden="true">⚙</span> Settings</button>
    <button class="nav-item" type="button" onclick="showPanel('resume', this)"><span class="nav-icon" aria-hidden="true">▤</span> Resume</button>
    <div class="nav-section">System</div>
    <button class="nav-item" type="button" onclick="showPanel('audit', this)"><span class="nav-icon" aria-hidden="true">≣</span> Audit Log</button>
    <a href="/admin/update.php" class="nav-item"><span class="nav-icon" aria-hidden="true">⬆</span> DB Migrations</a>
    <div class="nav-section">Account</div>
    <button class="nav-item" type="button" onclick="showPanel('account', this)"><span class="nav-icon" aria-hidden="true">◉</span> Account</button>
  </aside>

  <!-- MAIN -->
  <main class="main">

    <!-- ── PROJECTS LIST ── -->
    <div class="panel active" id="panel-projects">
      <div class="panel-header">
        <h1 class="panel-title">Projects</h1>
        <div class="panel-sub">Manage your portfolio projects</div>
      </div>
      <div class="stats-row">
        <div class="stat-card"><div class="stat-card-num" id="count-total">—</div><div class="stat-card-label">Total</div></div>
        <div class="stat-card"><div class="stat-card-num" id="count-active">—</div><div class="stat-card-label">Active</div></div>
        <div class="stat-card"><div class="stat-card-num" id="count-wip">—</div><div class="stat-card-label">WIP</div></div>
      </div>
      <div class="audit-controls" style="justify-content:flex-end">
        <button class="btn-reset" type="button" onclick="exportAllProjects()">⬇ Export All</button>
        <button class="btn-reset" type="button" onclick="document.getElementById('import-file').click()">⬆ Import</button>
        <input type="file" id="import-file" accept="application/json,.json" hidden onchange="importProjects(this)">
      </div>
      <table class="proj-table">
        <thead>
          <tr>
            <th><span class="visually-hidden">Reorder</span></th>
            <th>Title</th>
            <th>Language</th>
            <th>Status</th>
            <th>Links</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="proj-tbody">
          <tr class="empty-row"><td colspan="6">Loading...</td></tr>
        </tbody>
      </table>
    </div>

    <!-- ── ADD PROJECT ── -->
    <div class="panel" id="panel-add-project">
      <div class="panel-header">
        <h1 class="panel-title">Add Project</h1>
        <div class="panel-sub">Add a new project to your portfolio</div>
      </div>
      <div class="form-grid">
        <div class="form-group full">
          <label for="ap-title">Title <span class="req">*</span></label>
          <input type="text" id="ap-title" placeholder="My Awesome Project">
        </div>
        <div class="form-group full">
          <label for="ap-short-desc">Short Description <span class="req">*</span></label>
          <textarea id="ap-short-desc" rows="2" placeholder="1–2 sentence summary shown on the project card"></textarea>
        </div>
        <div class="form-group full">
          <label for="ap-desc">Full Description <span class="req">*</span></label>
          <textarea id="ap-desc" rows="4" placeholder="Detailed description shown in the project detail view"></textarea>
          <div class="hint">Markdown: **bold** · *italic* · `code` · ``code with `backticks` `` · ```block``` · [text](url) · # Heading · ## Sub-heading</div>
        </div>
        <div class="form-group">
          <label for="ap-lang">Primary Language <span class="req">*</span></label>
          <input type="text" id="ap-lang" list="lang-options" placeholder="e.g. JavaScript">
        </div>
        <div class="form-group">
          <label for="ap-status">Status</label>
          <select id="ap-status">
            <option value="active">Active</option>
            <option value="wip">WIP</option>
            <option value="archived">Archived</option>
          </select>
        </div>
        <div class="form-group">
          <label for="ap-github">GitHub URL</label>
          <input type="url" id="ap-github" placeholder="https://github.com/user/repo">
        </div>
        <div class="form-group">
          <label for="ap-demo">Live Demo URL</label>
          <input type="url" id="ap-demo" placeholder="https://myproject.dev">
        </div>
        <div class="form-group">
          <label for="ap-tags">Tags</label>
          <input type="text" id="ap-tags" placeholder="React, Node.js, PostgreSQL">
          <div class="hint">Comma-separated</div>
        </div>
        <div class="form-group">
          <label for="ap-sort">Sort Order</label>
          <input type="text" id="ap-sort" placeholder="0" value="0">
          <div class="hint">Lower = appears first</div>
        </div>
        <div class="form-group">
          <label for="ap-year">Year Created</label>
          <input type="number" id="ap-year" placeholder="2024" min="1900" max="2099">
        </div>
        <div class="form-group full">
          <label>Project Images</label>
          <div class="img-gallery" id="ap-img-gallery"></div>
          <div class="img-upload-area">
            <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple onchange="uploadImages(this,'ap')" aria-label="Upload project images">
            <div class="img-upload-label">＋ Add images — jpg, png, gif, webp, max 5 MB each</div>
          </div>
          <div id="ap-img-status" class="img-uploading" style="display:none">Uploading...</div>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn-save" type="button" onclick="addProject()">＋ Add Project</button>
        <button class="btn-reset" type="button" onclick="resetAddForm()">Reset</button>
      </div>
    </div>

    <!-- ── SKILLS ── -->
    <div class="panel" id="panel-skills">
      <div class="panel-header">
        <h1 class="panel-title">Skills</h1>
        <div class="panel-sub">Manage the grouped skill tags shown in the About section</div>
      </div>
      <div id="skill-groups-list" class="skill-list"></div>
      <div class="add-group-tile">
        <div class="add-group-tile-label">Add Group</div>
        <div class="form-grid">
          <div class="form-group"><label for="sg-label">Group Label <span class="req">*</span></label><input type="text" id="sg-label" placeholder="e.g. Mobile &amp; Frontend"></div>
          <div class="form-group"><label for="sg-sort">Sort Order</label><input type="text" id="sg-sort" value="0"><div class="hint">Lower = appears first</div></div>
          <div class="form-group full"><label for="sg-skills">Skills <span class="req">*</span></label><input type="text" id="sg-skills" placeholder="Flutter, Dart, React"><div class="hint">Comma-separated</div></div>
        </div>
        <div class="form-actions">
          <button class="btn-save" type="button" onclick="addSkillGroup()">＋ Add Group</button>
        </div>
      </div>
    </div>

    <!-- ── WRITING ── -->
    <div class="panel" id="panel-writing">
      <div class="panel-header">
        <h1 class="panel-title">Writing</h1>
        <div class="panel-sub">Markdown posts published at <a href="/writing/" target="_blank" style="color:var(--amber)">/writing/</a> · <a href="/writing/feed.xml" target="_blank" style="color:var(--muted);font-size:0.7rem">RSS</a></div>
      </div>

      <div class="audit-controls" style="justify-content:flex-end;margin-bottom:1.5rem">
        <button class="btn-save" type="button" onclick="newPostForm()">＋ New Post</button>
      </div>

      <table class="proj-table" id="posts-table">
        <thead>
          <tr>
            <th>Title</th>
            <th>Tags</th>
            <th>Status</th>
            <th>Published</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="posts-tbody">
          <tr class="empty-row"><td colspan="5">Loading…</td></tr>
        </tbody>
      </table>
    </div>

    <!-- ── SETTINGS ── -->
    <div class="panel" id="panel-settings">
      <div class="panel-header">
        <h1 class="panel-title">Site Settings</h1>
        <div class="panel-sub">Manage your portfolio content</div>
      </div>
      <div class="form-grid">
        <div class="form-group"><label for="s-name">Your Name</label><input type="text" id="s-name" placeholder="Jane Smith"></div>
        <div class="form-group"><label for="s-role">Role / Title</label><input type="text" id="s-role" placeholder="Full-Stack Developer"></div>
        <div class="form-group full"><label for="s-bio">Bio</label><textarea id="s-bio" rows="3" placeholder="A short paragraph about yourself..."></textarea></div>
        <div class="form-group"><label for="s-email">Email</label><input type="text" id="s-email" placeholder="you@example.com"></div>
        <div class="form-group"><label for="s-github">GitHub URL</label><input type="url" id="s-github" placeholder="https://github.com/username"></div>
        <div class="form-group"><label for="s-linkedin">LinkedIn URL</label><input type="url" id="s-linkedin" placeholder="https://linkedin.com/in/username"></div>
        <div class="form-group"><label for="s-location">Location</label><input type="text" id="s-location" placeholder="San Francisco, CA"></div>
        <div class="form-group"><label for="s-tagline">Tagline</label><input type="text" id="s-tagline" placeholder="Building great software, one commit at a time"></div>
        <div class="form-group"><label for="s-years">Years of Experience</label><input type="text" id="s-years" placeholder="5+"></div>
        <div class="form-group full">
          <label for="s-ticker">Ticker Items</label>
          <textarea id="s-ticker" rows="5" placeholder="Full-Stack Developer&#10;WordPress Developer&#10;Ceramics Instructor"></textarea>
          <div class="hint">One item per line. Cycles through these in the hero typed effect.</div>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn-save" type="button" onclick="saveSettings()">💾 Save Settings</button>
      </div>
    </div>

    <!-- ── RESUME ── -->
    <div class="panel" id="panel-resume">
      <div class="panel-header">
        <h1 class="panel-title">Resume</h1>
        <div class="panel-sub">Upload a PDF resume for your portfolio</div>
      </div>
      <div id="resume-current" style="margin-bottom:1.5rem;display:none">
        <div class="resume-tile">
          <div>
            <div class="resume-tile-label">Current Resume</div>
            <a id="resume-link" href="" target="_blank" class="resume-link">resume.pdf ↗</a>
          </div>
        </div>
      </div>
      <div style="max-width:400px">
        <div class="img-upload-area">
          <input type="file" accept="application/pdf" onchange="uploadResume(this)" aria-label="Upload resume PDF">
          <div class="img-upload-label">＋ Replace resume — PDF only, max 10 MB</div>
        </div>
        <div id="resume-status" class="img-uploading" style="display:none">Uploading...</div>
      </div>
    </div>

    <!-- ── AUDIT LOG ── -->
    <div class="panel" id="panel-audit">
      <div class="panel-header">
        <h1 class="panel-title">Audit Log</h1>
        <div class="panel-sub">Security-relevant events: logins, denials, admin writes, migrations</div>
      </div>
      <div class="audit-controls">
        <input type="text" id="audit-filter" placeholder="Filter by action (e.g. login, project, settings, migration)" aria-label="Filter audit log by action">
        <button class="btn-reset" type="button" onclick="loadAudit()">Refresh</button>
      </div>
      <table class="audit-table">
        <thead>
          <tr>
            <th>Time</th>
            <th>Admin</th>
            <th>Action</th>
            <th>Detail</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody id="audit-tbody">
          <tr class="empty-row"><td colspan="5">Loading…</td></tr>
        </tbody>
      </table>
      <div class="audit-pager">
        <button class="btn-reset" type="button" id="audit-prev" onclick="auditPage(-1)">← Prev</button>
        <span id="audit-page-info">—</span>
        <button class="btn-reset" type="button" id="audit-next" onclick="auditPage(1)">Next →</button>
      </div>
    </div>

    <!-- ── ACCOUNT ── -->
    <div class="panel" id="panel-account">
      <div class="panel-header">
        <h1 class="panel-title">Account</h1>
        <div class="panel-sub">Your admin account details</div>
      </div>
      <div class="account-tile">
        <div class="account-row">
          <?php if ($admin['avatar_url']): ?>
          <img src="<?= htmlspecialchars($admin['avatar_url']) ?>" class="account-avatar" alt="<?= htmlspecialchars($admin['name']) ?> avatar">
          <?php endif; ?>
          <div>
            <div class="account-name"><?= htmlspecialchars($admin['name']) ?></div>
            <div class="account-email"><?= htmlspecialchars($admin['email']) ?></div>
          </div>
        </div>
        <div class="account-meta">
          <div class="row">Provider: <span class="amber"><?= htmlspecialchars($admin['provider']) ?></span></div>
          <div>Session expires in 8 hours of inactivity</div>
        </div>
        <div class="account-actions">
          <form action="/api/auth/logout.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <button type="submit" class="btn-reset" style="display:inline-block">Sign Out</button>
          </form>
        </div>
      </div>
    </div>

  </main>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="edit-modal" role="dialog" aria-modal="true" aria-labelledby="edit-modal-title">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="edit-modal-title">Edit Project</div>
      <button class="modal-close" type="button" data-modal-close aria-label="Close edit project dialog">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="edit-id">
      <div class="form-grid">
        <div class="form-group full"><label for="edit-title">Title <span class="req">*</span></label><input type="text" id="edit-title"></div>
        <div class="form-group full"><label for="edit-short-desc">Short Description <span class="req">*</span></label><textarea id="edit-short-desc" rows="2"></textarea></div>
        <div class="form-group full"><label for="edit-desc">Full Description <span class="req">*</span></label><textarea id="edit-desc" rows="4"></textarea><div class="hint">Markdown: **bold** · *italic* · `code` · ``code with `backticks` `` · ```block``` · [text](url) · # Heading · ## Sub-heading</div></div>
        <div class="form-group"><label for="edit-lang">Language <span class="req">*</span></label>
          <input type="text" id="edit-lang" list="lang-options" placeholder="e.g. JavaScript">
        </div>
        <div class="form-group"><label for="edit-status">Status</label>
          <select id="edit-status"><option value="active">Active</option><option value="wip">WIP</option><option value="archived">Archived</option></select>
        </div>
        <div class="form-group"><label for="edit-github">GitHub URL</label><input type="url" id="edit-github"></div>
        <div class="form-group"><label for="edit-demo">Demo URL</label><input type="url" id="edit-demo"></div>
        <div class="form-group"><label for="edit-tags">Tags</label><input type="text" id="edit-tags"><div class="hint">Comma-separated</div></div>
        <div class="form-group"><label for="edit-sort">Sort Order</label><input type="text" id="edit-sort"></div>
        <div class="form-group"><label for="edit-year">Year Created</label><input type="number" id="edit-year" min="1900" max="2099"></div>
        <div class="form-group full">
          <label>Project Images</label>
          <div class="img-gallery" id="edit-img-gallery"></div>
          <div class="img-upload-area">
            <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple onchange="uploadImages(this,'edit')" aria-label="Upload project images">
            <div class="img-upload-label">＋ Add images — jpg, png, gif, webp, max 5 MB each</div>
          </div>
          <div id="edit-img-status" class="img-uploading" style="display:none">Uploading...</div>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn-save" type="button" onclick="saveEdit()">💾 Save Changes</button>
        <button class="btn-reset" type="button" data-modal-close>Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- SKILL GROUP EDIT MODAL -->
<div class="modal-overlay" id="skill-modal" role="dialog" aria-modal="true" aria-labelledby="skill-modal-title">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="skill-modal-title">Edit Skill Group</div>
      <button class="modal-close" type="button" data-modal-close aria-label="Close edit skill group dialog">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="sk-id">
      <div class="form-group"><label for="sk-label">Group Label <span class="req">*</span></label><input type="text" id="sk-label"></div>
      <div class="form-group"><label for="sk-sort">Sort Order</label><input type="text" id="sk-sort"><div class="hint">Lower = appears first</div></div>
      <div class="form-group"><label for="sk-skills">Skills</label><input type="text" id="sk-skills"><div class="hint">Comma-separated</div></div>
      <div class="form-actions">
        <button class="btn-save" type="button" onclick="saveSkillGroup()">💾 Save Changes</button>
        <button class="btn-reset" type="button" data-modal-close>Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- POST EDIT MODAL -->
<style>
  .post-modal{max-width:920px}
  .post-modal-tabs{display:flex;border-bottom:1px solid var(--border);margin-bottom:1rem}
  .post-tab{background:none;border:none;border-bottom:2px solid transparent;color:var(--muted);font-family:var(--mono);font-size:0.7rem;letter-spacing:0.1em;text-transform:uppercase;padding:0.6rem 1rem;cursor:pointer;transition:all 0.2s}
  .post-tab.active{color:var(--amber);border-bottom-color:var(--amber)}
  .post-tab:hover{color:var(--text)}
  .post-pane{display:none}
  .post-pane.active{display:block}
  #post-body{font-family:var(--mono);min-height:380px;line-height:1.55}
  .post-preview-pane{background:var(--bg);border:1px solid var(--border);padding:1.5rem;min-height:380px;max-height:60vh;overflow-y:auto;font-family:var(--mono);font-size:0.85rem;line-height:1.7}
  .post-preview-pane h1,.post-preview-pane h2,.post-preview-pane h3{font-family:var(--display);margin:1.2rem 0 0.6rem}
  .post-preview-pane h1{font-size:1.4rem}
  .post-preview-pane h2{font-size:1.15rem}
  .post-preview-pane h3{font-size:1rem}
  .post-preview-pane code{background:var(--bg3);border:1px solid var(--border);padding:0.05rem 0.3rem;color:var(--amber);font-size:0.85em}
  .post-preview-pane pre{background:var(--bg2);border:1px solid var(--border);padding:0.8rem 1rem;overflow-x:auto;margin:1rem 0}
  .post-preview-pane pre code{background:none;border:none;padding:0;color:var(--text)}
  .post-preview-pane img{max-width:100%;height:auto;border:1px solid var(--border);margin:1rem 0;display:block}
  .post-preview-pane blockquote{border-left:3px solid var(--amber-dim);padding:0.5rem 1rem;margin:1rem 0;color:var(--muted)}
  .post-preview-pane a{color:var(--amber)}
  .post-preview-pane p{margin:0 0 1rem}
  .post-preview-pane ul,.post-preview-pane ol{margin:0 0 1rem 1.5rem}

  .project-checks{display:flex;flex-direction:column;gap:0.4rem;max-height:180px;overflow-y:auto;border:1px solid var(--border);background:var(--bg);padding:0.6rem 0.8rem}
  .project-check{display:flex;align-items:center;gap:0.5rem;font-size:0.78rem;cursor:pointer}
  .project-check input{width:auto}

  .pub-toggle{display:flex;align-items:center;gap:0.6rem;font-size:0.78rem}
  .pub-toggle input{width:auto}
  .post-cover-row{display:flex;align-items:center;gap:0.6rem}
  .post-cover-row input{flex:1}
  .post-cover-thumb{width:44px;height:44px;background:var(--bg3);border:1px solid var(--border);object-fit:cover;flex-shrink:0}
</style>
<div class="modal-overlay" id="post-modal" role="dialog" aria-modal="true" aria-labelledby="post-modal-title">
  <div class="modal post-modal">
    <div class="modal-header">
      <div class="modal-title" id="post-modal-title">New Post</div>
      <button class="modal-close" type="button" data-modal-close aria-label="Close post dialog">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="post-id">
      <div class="form-grid">
        <div class="form-group full"><label for="post-title">Title <span class="req">*</span></label><input type="text" id="post-title" oninput="suggestSlug(this.value)"></div>
        <div class="form-group"><label for="post-slug">Slug</label><input type="text" id="post-slug" placeholder="auto-from-title"><div class="hint">URL: /writing/&lt;slug&gt;/</div></div>
        <div class="form-group"><label for="post-published-at">Publish Date</label><input type="datetime-local" id="post-published-at"><div class="hint">Leave blank to use now</div></div>
        <div class="form-group full"><label for="post-excerpt">Excerpt</label><textarea id="post-excerpt" rows="2" placeholder="Optional summary used on the index card and in social shares"></textarea><div class="hint">Auto-generated from the body if left blank.</div></div>
        <div class="form-group full">
          <label for="post-cover">Cover Image (URL)</label>
          <div class="post-cover-row">
            <input type="url" id="post-cover" placeholder="https://… or /uploads/projects/…" oninput="updateCoverThumb()">
            <img id="post-cover-thumb" class="post-cover-thumb" alt="" style="display:none">
            <label class="btn-reset" style="cursor:pointer;display:inline-block;margin:0">
              Upload
              <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" hidden onchange="uploadPostCover(this)">
            </label>
          </div>
          <div id="post-cover-status" class="img-uploading" style="display:none">Uploading…</div>
        </div>
        <div class="form-group full"><label for="post-tags">Tags</label><input type="text" id="post-tags" placeholder="rust, debugging, war-stories"><div class="hint">Comma-separated</div></div>

        <div class="form-group full">
          <label>Body <span class="req">*</span> · <span style="text-transform:none;letter-spacing:0;font-size:0.65rem;color:var(--muted)">markdown — paste from your editor</span></label>
          <div class="post-modal-tabs" role="tablist">
            <button class="post-tab active" type="button" role="tab" data-pane="post-pane-edit">Write</button>
            <button class="post-tab"        type="button" role="tab" data-pane="post-pane-preview">Preview</button>
          </div>
          <div class="post-pane active" id="post-pane-edit">
            <textarea id="post-body" rows="18" placeholder="# My Post&#10;&#10;Write in markdown. Embed images with ![alt](/uploads/projects/foo.png).&#10;&#10;Drag images into the upload field above to get a URL."></textarea>
          </div>
          <div class="post-pane" id="post-pane-preview" aria-live="polite">
            <div class="post-preview-pane" id="post-preview-html"><em style="color:var(--muted)">Click Preview to render…</em></div>
          </div>
        </div>

        <div class="form-group full">
          <label>Linked Projects</label>
          <div class="project-checks" id="post-project-checks"><span style="color:var(--muted);font-size:0.7rem">Loading projects…</span></div>
          <div class="hint">Shown at the bottom of the post.</div>
        </div>

        <div class="form-group full">
          <label class="pub-toggle">
            <input type="checkbox" id="post-published">
            <span>Published — visible at /writing/ and in the RSS feed</span>
          </label>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn-save" type="button" onclick="savePost()">💾 Save</button>
        <button class="btn-reset" type="button" data-modal-close>Cancel</button>
        <button class="btn-reset" type="button" id="post-delete-btn" onclick="deleteCurrentPost()" style="margin-left:auto;color:var(--red);border-color:var(--border);display:none">Delete</button>
      </div>
    </div>
  </div>
</div>

<datalist id="lang-options">
  <option>JavaScript</option>
  <option>TypeScript</option>
  <option>Python</option>
  <option>PHP</option>
  <option>Flutter</option>
  <option>Go</option>
  <option>Rust</option>
  <option>Ruby</option>
  <option>C++</option>
  <option>Swift</option>
  <option>Kotlin</option>
  <option>Other</option>
</datalist>

<script>
const API = '/api';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content') || '';
let allProjects = [];
let allSkillGroups = [];

const projectImages       = { ap: [], edit: [] };
const projectSummaryImage = { ap: null, edit: null };

// ── Network: single helper that adds CSRF on writes and surfaces errors ─────
async function apiFetch(path, opts = {}) {
  const method = (opts.method || 'GET').toUpperCase();
  const isWrite = !['GET', 'HEAD', 'OPTIONS'].includes(method);
  const headers = { ...(opts.headers || {}) };
  if (isWrite && CSRF_TOKEN) headers['X-CSRF-Token'] = CSRF_TOKEN;

  let res;
  try {
    res = await fetch(`${API}${path}`, { ...opts, headers });
  } catch (e) {
    throw new Error('Network error');
  }
  if (!res.ok) {
    let msg = `HTTP ${res.status}`;
    try { const j = await res.json(); if (j && j.error) msg = j.error; } catch (e) {}
    throw new Error(msg);
  }
  const text = await res.text();
  if (!text) return null;
  try { return JSON.parse(text); }
  catch (e) {
    // Surface the first chunk of the bad body so the cause is debuggable
    // (PHP warning prefix, stray BOM, HTML error page, etc.).
    const preview = text.slice(0, 160).replace(/\s+/g, ' ').trim();
    console.error('Bad JSON from', path, '— first 160 chars:', preview);
    throw new Error('Invalid JSON: ' + preview);
  }
}

// ── Toast ────────────────────────────────────────────────────
function toast(msg, err=false) {
  const t = document.createElement('div');
  t.className = 'toast' + (err ? ' err' : '');
  t.setAttribute('role', err ? 'alert' : 'status');
  t.textContent = (err ? '⚠ ' : '✓ ') + msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 2500);
}

function esc(s) {
  if (s === null || s === undefined) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function intOrZero(v) { const n = parseInt(v, 10); return Number.isFinite(n) ? n : 0; }
function intOrNull(v) { const n = parseInt(v, 10); return Number.isFinite(n) ? n : null; }

// ── Panel navigation ────────────────────────────────────────
function showPanel(name, btn) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const panel = document.getElementById('panel-' + name);
  if (panel) panel.classList.add('active');
  if (btn && btn.classList) btn.classList.add('active');
}
function showPanelById(name) {
  const btn = Array.from(document.querySelectorAll('.nav-item'))
    .find(n => (n.getAttribute('onclick') || '').includes(`'${name}'`));
  showPanel(name, btn);
}

// ── Generic drag-and-drop reorder helper ────────────────────
// Wires drag/dragend/dragover/drop on every `itemSelector` element in
// `container`. When a drop happens, calls onReorder(srcIdx, destIdx) where
// indexes come from getIndex(element).
function enableDragReorder({ container, itemSelector, getIndex, onReorder, dragClass = 'dragging', overClass = 'drag-over' }) {
  let dragSrc = null;
  const items = container.querySelectorAll(itemSelector);
  items.forEach(item => {
    item.addEventListener('dragstart', function(e) {
      dragSrc = this;
      this.classList.add(dragClass);
      e.dataTransfer.effectAllowed = 'move';
    });
    item.addEventListener('dragend', function() {
      this.classList.remove(dragClass);
      container.querySelectorAll(itemSelector).forEach(t => t.classList.remove(overClass));
    });
    item.addEventListener('dragover', function(e) {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      container.querySelectorAll(itemSelector).forEach(t => t.classList.remove(overClass));
      if (this !== dragSrc) this.classList.add(overClass);
    });
    item.addEventListener('drop', function(e) {
      e.preventDefault();
      if (!dragSrc || this === dragSrc) return;
      onReorder(getIndex(dragSrc), getIndex(this));
    });
  });
}

// ── Generic modal binder: Esc to close, click-overlay to close,
//    [data-modal-close] buttons to close, focus trap + restore focus.
function bindModal(modalId, { onOpen, onClose, focusSelector } = {}) {
  const modal = document.getElementById(modalId);
  let lastFocused = null;

  modal.addEventListener('click', e => { if (e.target === modal) close(); });
  modal.querySelectorAll('[data-modal-close]').forEach(b => b.addEventListener('click', close));
  modal.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

  function open() {
    lastFocused = document.activeElement;
    modal.classList.add('open');
    if (typeof onOpen === 'function') onOpen();
    if (focusSelector) {
      const el = modal.querySelector(focusSelector);
      if (el) setTimeout(() => el.focus(), 30);
    }
  }
  function close() {
    if (!modal.classList.contains('open')) return;
    modal.classList.remove('open');
    if (typeof onClose === 'function') onClose();
    if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
  }
  return { open, close };
}

const editModal  = bindModal('edit-modal',  { onClose: resetEditImages, focusSelector: '#edit-title' });
const skillModal = bindModal('skill-modal', { focusSelector: '#sk-label' });
const postModal  = bindModal('post-modal',  { focusSelector: '#post-title' });

// ── Loaders ──────────────────────────────────────────────────
async function loadProjects() {
  try {
    allProjects = await apiFetch('/projects/') || [];
    renderTable(allProjects);
    updateCounts(allProjects);
  } catch (e) { toast('Failed to load projects: ' + e.message, true); }
}

async function loadSettings() {
  try {
    const s = await apiFetch('/settings/') || {};
    document.getElementById('s-name').value     = s.name         || '';
    document.getElementById('s-role').value     = s.role         || '';
    document.getElementById('s-bio').value      = s.bio          || '';
    document.getElementById('s-email').value    = s.email        || '';
    document.getElementById('s-github').value   = s.github       || '';
    document.getElementById('s-linkedin').value = s.linkedin     || '';
    document.getElementById('s-location').value = s.location     || '';
    document.getElementById('s-tagline').value  = s.tagline      || '';
    document.getElementById('s-years').value    = s.years_exp    || '';
    document.getElementById('s-ticker').value   = s.ticker_items || '';
  } catch (e) { toast('Failed to load settings: ' + e.message, true); }
}

async function loadSkills() {
  try {
    allSkillGroups = await apiFetch('/skills/') || [];
    renderSkillGroups();
  } catch (e) { toast('Failed to load skills: ' + e.message, true); }
}

async function loadResume() {
  try {
    const data = await apiFetch('/uploads/resume.php');
    if (data && data.url) {
      document.getElementById('resume-current').style.display = 'block';
      document.getElementById('resume-link').href = data.url;
    }
  } catch (e) { /* no-op: resume may simply not exist yet */ }
}

// ── Render: project table ────────────────────────────────────
function renderTable(projects) {
  const tbody = document.getElementById('proj-tbody');
  if (!projects.length) {
    tbody.innerHTML = '<tr class="empty-row"><td colspan="6">No projects yet — add one!</td></tr>';
    return;
  }
  tbody.innerHTML = projects.map(p => `
    <tr draggable="true" data-id="${p.id}">
      <td class="drag-handle" title="Drag to reorder" aria-label="Drag handle for ${esc(p.title)}">⠿</td>
      <td><strong>${esc(p.title)}</strong></td>
      <td><span class="proj-lang-badge">${esc(p.language)}</span></td>
      <td><span class="status-badge ${esc(p.status)}">${esc(p.status)}</span></td>
      <td style="font-size:0.65rem">
        ${p.github_url ? `<a href="${esc(p.github_url)}" target="_blank" class="tbl-link-amber">GitHub</a>` : '—'}
        ${p.demo_url   ? ` · <a href="${esc(p.demo_url)}" target="_blank" class="tbl-link-blue">Demo</a>` : ''}
      </td>
      <td>
        <div class="tbl-actions">
          <button class="tbl-btn edit" type="button" onclick="openEdit(${p.id})">Edit</button>
          <button class="tbl-btn"      type="button" onclick="exportProject(${p.id})">Export</button>
          <button class="tbl-btn del"  type="button" onclick="deleteProject(${p.id})">Delete</button>
        </div>
      </td>
    </tr>
  `).join('');

  enableDragReorder({
    container: tbody,
    itemSelector: 'tr[data-id]',
    getIndex: el => allProjects.findIndex(p => p.id === intOrZero(el.dataset.id)),
    onReorder: (srcIdx, destIdx) => {
      const [moved] = allProjects.splice(srcIdx, 1);
      allProjects.splice(destIdx, 0, moved);
      allProjects.forEach((p, i) => p.sort_order = i);
      renderTable(allProjects);
      updateCounts(allProjects);
      saveOrder();
    },
  });
}

function updateCounts(projects) {
  document.getElementById('count-total').textContent  = projects.length;
  document.getElementById('count-active').textContent = projects.filter(p=>p.status==='active').length;
  document.getElementById('count-wip').textContent    = projects.filter(p=>p.status==='wip').length;
}

async function saveOrder() {
  try {
    await apiFetch('/projects/', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(allProjects.map((p, i) => ({ id: p.id, sort_order: i }))),
    });
    toast('Order saved');
  } catch (e) { toast('Error saving order: ' + e.message, true); }
}

// ── Image gallery ────────────────────────────────────────────
function setSummaryImage(prefix, i) {
  const url = projectImages[prefix][i];
  projectSummaryImage[prefix] = projectSummaryImage[prefix] === url ? null : url;
  renderImageGallery(prefix);
}

async function uploadImages(input, prefix) {
  const files = Array.from(input.files);
  if (!files.length) return;
  const statusEl = document.getElementById(`${prefix}-img-status`);
  statusEl.style.display = 'block';
  for (const file of files) {
    try {
      const fd = new FormData();
      fd.append('image', file);
      const data = await apiFetch('/uploads/', { method: 'POST', body: fd });
      if (data && data.url) projectImages[prefix].push(data.url);
    } catch (e) {
      toast(`Failed to upload ${file.name}: ${e.message}`, true);
    }
  }
  statusEl.style.display = 'none';
  input.value = '';
  renderImageGallery(prefix);
}

function removeProjectImage(prefix, i) {
  const removed = projectImages[prefix][i];
  if (projectSummaryImage[prefix] === removed) projectSummaryImage[prefix] = null;
  projectImages[prefix].splice(i, 1);
  renderImageGallery(prefix);
}

function renderImageGallery(prefix) {
  const gallery = document.getElementById(`${prefix}-img-gallery`);
  gallery.innerHTML = projectImages[prefix].map((url, i) => {
    const isSummary = projectSummaryImage[prefix] === url;
    const starLabel = isSummary ? 'Summary image (click to unset)' : 'Set as card summary image';
    return `
      <div class="img-thumb${isSummary ? ' is-summary' : ''}" draggable="true" data-index="${i}">
        <img src="${esc(url)}" alt="Project image ${i + 1}">
        <button type="button" class="img-thumb-del" onclick="removeProjectImage('${prefix}',${i})" aria-label="Remove image ${i + 1}">✕</button>
        <button type="button" class="img-thumb-star" onclick="setSummaryImage('${prefix}',${i})" title="${starLabel}" aria-label="${starLabel}">${isSummary ? '★' : '☆'}</button>
      </div>
    `;
  }).join('');

  enableDragReorder({
    container: gallery,
    itemSelector: '.img-thumb',
    getIndex: el => intOrZero(el.dataset.index),
    onReorder: (srcIdx, destIdx) => {
      const imgs = projectImages[prefix];
      const [moved] = imgs.splice(srcIdx, 1);
      imgs.splice(destIdx, 0, moved);
      renderImageGallery(prefix);
    },
    dragClass: 'img-dragging',
    overClass: 'img-drag-over',
  });
}

// ── Add project ──────────────────────────────────────────────
async function addProject() {
  const title     = document.getElementById('ap-title').value.trim();
  const shortDesc = document.getElementById('ap-short-desc').value.trim();
  const desc      = document.getElementById('ap-desc').value.trim();
  const lang      = document.getElementById('ap-lang').value.trim();
  if (!title || !desc || !lang) { toast('Fill in required fields', true); return; }

  const tags = document.getElementById('ap-tags').value.split(',').map(t=>t.trim()).filter(Boolean);
  const body = {
    title, short_description: shortDesc, description: desc, language: lang,
    tags,
    github_url:    document.getElementById('ap-github').value.trim(),
    demo_url:      document.getElementById('ap-demo').value.trim(),
    images:        projectImages.ap,
    summary_image: projectSummaryImage.ap || null,
    status:        document.getElementById('ap-status').value,
    sort_order:    intOrZero(document.getElementById('ap-sort').value),
    year:          intOrNull(document.getElementById('ap-year').value),
  };

  try {
    await apiFetch('/projects/', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
    toast('Project added!');
    resetAddForm();
    await loadProjects();
    showPanelById('projects');
  } catch (e) { toast(e.message, true); }
}

function resetAddForm() {
  ['ap-title','ap-short-desc','ap-desc','ap-tags','ap-github','ap-demo'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('ap-lang').value   = '';
  document.getElementById('ap-status').value = 'active';
  document.getElementById('ap-sort').value   = '0';
  document.getElementById('ap-year').value   = '';
  projectImages.ap       = [];
  projectSummaryImage.ap = null;
  renderImageGallery('ap');
}

async function deleteProject(id) {
  if (!confirm('Delete this project? This cannot be undone.')) return;
  try {
    await apiFetch(`/projects/?id=${id}`, { method:'DELETE' });
    toast('Project deleted');
    await loadProjects();
  } catch (e) { toast('Error deleting: ' + e.message, true); }
}

// ── Import / Export ──────────────────────────────────────────
// Strip server-side fields so re-importing doesn't try to preserve IDs
// or stale timestamps.
function stripForExport(p) {
  const { id, created_at, updated_at, ...rest } = p;
  return rest;
}

function downloadJson(data, filename) {
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 0);
}

function safeFilename(s) {
  return (s || 'project').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'project';
}

function exportAllProjects() {
  if (!allProjects.length) { toast('Nothing to export', true); return; }
  const payload = allProjects.map(stripForExport);
  const date = new Date().toISOString().slice(0, 10);
  downloadJson(payload, `projects-${date}.json`);
  toast(`Exported ${payload.length} project${payload.length === 1 ? '' : 's'}`);
}

function exportProject(id) {
  const p = allProjects.find(x => x.id === id);
  if (!p) { toast('Project not found', true); return; }
  downloadJson(stripForExport(p), `project-${safeFilename(p.title)}.json`);
  toast('Exported');
}

async function importProjects(input) {
  const file = input.files[0];
  if (!file) return;
  let parsed;
  try {
    parsed = JSON.parse(await file.text());
  } catch (e) {
    toast('Not valid JSON: ' + e.message, true);
    input.value = '';
    return;
  }
  const count = Array.isArray(parsed) ? parsed.length : (parsed && typeof parsed === 'object' ? 1 : 0);
  if (!count) { toast('No projects in file', true); input.value = ''; return; }
  if (!confirm(`Import ${count} project${count === 1 ? '' : 's'}? Existing titles will be updated when their data has changed.`)) {
    input.value = '';
    return;
  }
  try {
    const res = await apiFetch('/projects/import.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(parsed),
    });
    const parts = [`Created ${res.created}`];
    if (res.updated) parts.push(`updated ${res.updated}`);
    if (res.skipped) parts.push(`skipped ${res.skipped}`);
    if (res.errors && res.errors.length) parts.push(`${res.errors.length} error${res.errors.length === 1 ? '' : 's'}`);
    toast(parts.join(' · '), Boolean(res.errors && res.errors.length));
    if (res.errors && res.errors.length) console.warn('Import errors:', res.errors);
    await loadProjects();
  } catch (e) {
    toast('Import failed: ' + e.message, true);
  } finally {
    input.value = '';
  }
}

// ── Edit modal ───────────────────────────────────────────────
function openEdit(id) {
  const p = allProjects.find(proj => proj.id === id);
  if (!p) return;
  document.getElementById('edit-id').value          = p.id;
  document.getElementById('edit-title').value       = p.title;
  document.getElementById('edit-short-desc').value  = p.short_description || '';
  document.getElementById('edit-desc').value        = p.description;
  document.getElementById('edit-lang').value        = p.language;
  document.getElementById('edit-status').value      = p.status;
  document.getElementById('edit-github').value      = p.github_url || '';
  document.getElementById('edit-demo').value        = p.demo_url   || '';
  document.getElementById('edit-tags').value        = (p.tags||[]).join(', ');
  document.getElementById('edit-sort').value        = p.sort_order || 0;
  document.getElementById('edit-year').value        = p.year || '';
  projectImages.edit       = [...(p.images || [])];
  projectSummaryImage.edit = p.summary_image || null;
  renderImageGallery('edit');
  editModal.open();
}

async function saveEdit() {
  const id   = document.getElementById('edit-id').value;
  const tags = document.getElementById('edit-tags').value.split(',').map(t=>t.trim()).filter(Boolean);
  const body = {
    title:             document.getElementById('edit-title').value.trim(),
    short_description: document.getElementById('edit-short-desc').value.trim(),
    description:       document.getElementById('edit-desc').value.trim(),
    language:          document.getElementById('edit-lang').value.trim(),
    status:            document.getElementById('edit-status').value,
    github_url:        document.getElementById('edit-github').value.trim(),
    demo_url:          document.getElementById('edit-demo').value.trim(),
    images:            projectImages.edit,
    summary_image:     projectSummaryImage.edit || null,
    tags,
    sort_order:        intOrZero(document.getElementById('edit-sort').value),
    year:              intOrNull(document.getElementById('edit-year').value),
  };

  try {
    await apiFetch(`/projects/?id=${id}`, { method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
    editModal.close();
    toast('Project updated!');
    await loadProjects();
  } catch (e) { toast('Error saving: ' + e.message, true); }
}

function resetEditImages() {
  projectImages.edit       = [];
  projectSummaryImage.edit = null;
  renderImageGallery('edit');
}

// ── Save settings ────────────────────────────────────────────
async function saveSettings() {
  const body = {
    name:         document.getElementById('s-name').value.trim(),
    role:         document.getElementById('s-role').value.trim(),
    bio:          document.getElementById('s-bio').value.trim(),
    email:        document.getElementById('s-email').value.trim(),
    github:       document.getElementById('s-github').value.trim(),
    linkedin:     document.getElementById('s-linkedin').value.trim(),
    location:     document.getElementById('s-location').value.trim(),
    tagline:      document.getElementById('s-tagline').value.trim(),
    years_exp:    document.getElementById('s-years').value.trim(),
    ticker_items: document.getElementById('s-ticker').value.trim(),
  };
  try {
    await apiFetch('/settings/', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
    toast('Settings saved!');
  } catch (e) { toast('Error saving settings: ' + e.message, true); }
}

// ── Skills ───────────────────────────────────────────────────
function renderSkillGroups() {
  const list = document.getElementById('skill-groups-list');
  if (!allSkillGroups.length) {
    list.innerHTML = '<div style="color:var(--muted);font-size:0.78rem;padding:0.5rem 0">No skill groups yet — add one below.</div>';
    return;
  }
  list.innerHTML = allSkillGroups.map(g => `
    <div class="tile-row">
      <div class="tile-body">
        <div class="tile-label-amber">${esc(g.label)}</div>
        <div class="tile-tags">
          ${(g.skills||[]).map(s=>`<span class="tile-tag">${esc(s)}</span>`).join('')}
        </div>
      </div>
      <div class="tbl-actions">
        <button class="tbl-btn" type="button" onclick="openSkillEdit(${g.id})">Edit</button>
        <button class="tbl-btn del" type="button" onclick="deleteSkillGroup(${g.id})">Delete</button>
      </div>
    </div>
  `).join('');
}

function openSkillEdit(id) {
  const g = allSkillGroups.find(x => x.id === id);
  if (!g) return;
  document.getElementById('sk-id').value     = g.id;
  document.getElementById('sk-label').value  = g.label;
  document.getElementById('sk-sort').value   = g.sort_order ?? 0;
  document.getElementById('sk-skills').value = (g.skills||[]).join(', ');
  skillModal.open();
}

async function saveSkillGroup() {
  const id = document.getElementById('sk-id').value;
  const body = {
    label:      document.getElementById('sk-label').value.trim(),
    skills:     document.getElementById('sk-skills').value.split(',').map(s=>s.trim()).filter(Boolean),
    sort_order: intOrZero(document.getElementById('sk-sort').value),
  };
  if (!body.label) { toast('Label is required', true); return; }
  try {
    await apiFetch(`/skills/?id=${id}`, { method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
    skillModal.close();
    toast('Skill group updated!');
    await loadSkills();
  } catch (e) { toast('Error saving: ' + e.message, true); }
}

async function addSkillGroup() {
  const body = {
    label:      document.getElementById('sg-label').value.trim(),
    skills:     document.getElementById('sg-skills').value.split(',').map(s=>s.trim()).filter(Boolean),
    sort_order: intOrZero(document.getElementById('sg-sort').value),
  };
  if (!body.label) { toast('Label is required', true); return; }
  try {
    await apiFetch('/skills/', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
    document.getElementById('sg-label').value  = '';
    document.getElementById('sg-skills').value = '';
    document.getElementById('sg-sort').value   = '0';
    toast('Skill group added!');
    await loadSkills();
  } catch (e) { toast('Error adding: ' + e.message, true); }
}

async function deleteSkillGroup(id) {
  if (!confirm('Delete this skill group?')) return;
  try {
    await apiFetch(`/skills/?id=${id}`, { method:'DELETE' });
    toast('Deleted');
    await loadSkills();
  } catch (e) { toast('Error deleting: ' + e.message, true); }
}

// ── Resume ───────────────────────────────────────────────────
async function uploadResume(input) {
  if (!input.files[0]) return;
  const statusEl = document.getElementById('resume-status');
  statusEl.style.display = 'block';
  const fd = new FormData();
  fd.append('resume', input.files[0]);
  try {
    const data = await apiFetch('/uploads/resume.php', { method: 'POST', body: fd });
    document.getElementById('resume-current').style.display = 'block';
    document.getElementById('resume-link').href = data.url;
    toast('Resume uploaded!');
  } catch (e) {
    toast('Upload failed: ' + e.message, true);
  } finally {
    statusEl.style.display = 'none';
    input.value = '';
  }
}

// ── Audit log ────────────────────────────────────────────────
const AUDIT_PAGE_SIZE = 50;
let auditOffset = 0;
let auditTotal  = 0;
let auditFilterTimer = null;

async function loadAudit() {
  const filter = document.getElementById('audit-filter').value.trim();
  const params = new URLSearchParams({ limit: String(AUDIT_PAGE_SIZE), offset: String(auditOffset) });
  if (filter) params.set('action', filter);
  try {
    const data = await apiFetch('/audit/?' + params.toString());
    auditTotal = data.total || 0;
    renderAuditRows(data.rows || []);
    const info = document.getElementById('audit-page-info');
    if (auditTotal === 0) {
      info.textContent = 'No entries';
    } else {
      const start = auditOffset + 1;
      const end   = Math.min(auditOffset + AUDIT_PAGE_SIZE, auditTotal);
      info.textContent = `${start}–${end} of ${auditTotal}`;
    }
    document.getElementById('audit-prev').disabled = auditOffset === 0;
    document.getElementById('audit-next').disabled = auditOffset + AUDIT_PAGE_SIZE >= auditTotal;
  } catch (e) {
    toast('Failed to load audit log: ' + e.message, true);
  }
}

function renderAuditRows(rows) {
  const tbody = document.getElementById('audit-tbody');
  if (!rows.length) {
    tbody.innerHTML = '<tr class="empty-row"><td colspan="5">No entries match.</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map(r => {
    const who = r.admin_id
      ? esc(r.admin_name || r.admin_email || ('#' + r.admin_id))
      : '—';
    return `
      <tr>
        <td class="audit-time">${esc(r.created_at)}</td>
        <td>${who}</td>
        <td><span class="audit-action">${esc(r.action)}</span></td>
        <td class="audit-detail">${esc(r.detail || '—')}</td>
        <td class="audit-ip">${esc(r.ip || '—')}</td>
      </tr>
    `;
  }).join('');
}

function auditPage(dir) {
  const next = auditOffset + dir * AUDIT_PAGE_SIZE;
  auditOffset = Math.max(0, next);
  loadAudit();
}

document.getElementById('audit-filter').addEventListener('input', () => {
  auditOffset = 0;
  clearTimeout(auditFilterTimer);
  auditFilterTimer = setTimeout(loadAudit, 250);
});

// ── Writing posts ────────────────────────────────────────────
let allPosts = [];

async function loadPosts() {
  try {
    allPosts = await apiFetch('/posts/') || [];
    renderPostsTable();
  } catch (e) { toast('Failed to load posts: ' + e.message, true); }
}

function renderPostsTable() {
  const tbody = document.getElementById('posts-tbody');
  if (!allPosts.length) {
    tbody.innerHTML = '<tr class="empty-row"><td colspan="5">No posts yet — click ＋ New Post.</td></tr>';
    return;
  }
  tbody.innerHTML = allPosts.map(p => {
    const date = p.published_at ? new Date(p.published_at.replace(' ', 'T')).toLocaleDateString() : '—';
    const status = p.is_published
      ? '<span class="status-badge active">Published</span>'
      : '<span class="status-badge wip">Draft</span>';
    const tags = (p.tags || []).slice(0, 3).map(t => `<span class="proj-lang-badge" style="margin-right:0.2rem">${esc(t)}</span>`).join('');
    return `
      <tr>
        <td><strong>${esc(p.title)}</strong><div style="font-size:0.6rem;color:var(--muted);margin-top:0.2rem">/writing/${esc(p.slug)}/</div></td>
        <td>${tags || '—'}</td>
        <td>${status}</td>
        <td style="font-size:0.7rem;color:var(--muted)">${esc(date)}</td>
        <td>
          <div class="tbl-actions">
            <button class="tbl-btn edit" type="button" onclick="openPostEdit(${p.id})">Edit</button>
            ${p.is_published ? `<a class="tbl-btn" href="/writing/${encodeURIComponent(p.slug)}/" target="_blank">View</a>` : ''}
            <button class="tbl-btn del" type="button" onclick="deletePost(${p.id})">Delete</button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

function newPostForm() {
  document.getElementById('post-modal-title').textContent = 'New Post';
  document.getElementById('post-id').value           = '';
  document.getElementById('post-title').value        = '';
  document.getElementById('post-slug').value         = '';
  document.getElementById('post-excerpt').value      = '';
  document.getElementById('post-cover').value        = '';
  document.getElementById('post-tags').value         = '';
  document.getElementById('post-body').value         = '';
  document.getElementById('post-published').checked  = false;
  document.getElementById('post-published-at').value = '';
  document.getElementById('post-delete-btn').style.display = 'none';
  document.getElementById('post-preview-html').innerHTML = '<em style="color:var(--muted)">Click Preview to render…</em>';
  switchPostTab('post-pane-edit');
  renderProjectChecks([]);
  updateCoverThumb();
  postModal.open();
}

async function openPostEdit(id) {
  try {
    const p = await apiFetch(`/posts/?id=${id}`);
    document.getElementById('post-modal-title').textContent = 'Edit Post';
    document.getElementById('post-id').value           = p.id;
    document.getElementById('post-title').value        = p.title || '';
    document.getElementById('post-slug').value         = p.slug || '';
    document.getElementById('post-excerpt').value      = p.excerpt || '';
    document.getElementById('post-cover').value        = p.cover_image || '';
    document.getElementById('post-tags').value         = (p.tags || []).join(', ');
    document.getElementById('post-body').value         = p.body_markdown || '';
    document.getElementById('post-published').checked  = !!p.is_published;
    document.getElementById('post-published-at').value = p.published_at ? p.published_at.replace(' ', 'T').slice(0, 16) : '';
    document.getElementById('post-delete-btn').style.display = '';
    document.getElementById('post-preview-html').innerHTML = '<em style="color:var(--muted)">Click Preview to render…</em>';
    switchPostTab('post-pane-edit');
    renderProjectChecks(p.project_ids || []);
    updateCoverThumb();
    postModal.open();
  } catch (e) {
    toast('Failed to load post: ' + e.message, true);
  }
}

function renderProjectChecks(selectedIds) {
  const wrap = document.getElementById('post-project-checks');
  if (!allProjects.length) {
    wrap.innerHTML = '<span style="color:var(--muted);font-size:0.7rem">No projects to link yet.</span>';
    return;
  }
  const sel = new Set(selectedIds.map(Number));
  wrap.innerHTML = allProjects.map(p => `
    <label class="project-check">
      <input type="checkbox" name="post-project" value="${p.id}" ${sel.has(p.id) ? 'checked' : ''}>
      <span>${esc(p.title)} <span style="color:var(--muted);font-size:0.65rem">· ${esc(p.language)}</span></span>
    </label>
  `).join('');
}

function suggestSlug(title) {
  const slugInput = document.getElementById('post-slug');
  const id = document.getElementById('post-id').value;
  // Only auto-fill the slug for *new* posts and only while the user hasn't
  // typed their own — clobbering an edited slug would break URLs.
  if (id) return;
  if (slugInput.dataset.touched === '1') return;
  slugInput.value = (title || '').toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120);
}
document.getElementById('post-slug').addEventListener('input', e => { e.target.dataset.touched = '1'; });

function updateCoverThumb() {
  const url = document.getElementById('post-cover').value.trim();
  const img = document.getElementById('post-cover-thumb');
  if (url) { img.src = url; img.style.display = ''; }
  else     { img.src = '';  img.style.display = 'none'; }
}

async function uploadPostCover(input) {
  const file = input.files[0];
  if (!file) return;
  const status = document.getElementById('post-cover-status');
  status.style.display = 'block';
  try {
    const fd = new FormData();
    fd.append('image', file);
    const data = await apiFetch('/uploads/', { method: 'POST', body: fd });
    if (data && data.url) {
      document.getElementById('post-cover').value = data.url;
      updateCoverThumb();
    }
  } catch (e) {
    toast('Upload failed: ' + e.message, true);
  } finally {
    status.style.display = 'none';
    input.value = '';
  }
}

function switchPostTab(paneId) {
  document.querySelectorAll('#post-modal .post-tab').forEach(t => {
    t.classList.toggle('active', t.dataset.pane === paneId);
  });
  document.querySelectorAll('#post-modal .post-pane').forEach(p => {
    p.classList.toggle('active', p.id === paneId);
  });
  if (paneId === 'post-pane-preview') refreshPreview();
}
document.querySelectorAll('#post-modal .post-tab').forEach(t => {
  t.addEventListener('click', () => switchPostTab(t.dataset.pane));
});

async function refreshPreview() {
  const md = document.getElementById('post-body').value;
  const target = document.getElementById('post-preview-html');
  if (!md.trim()) { target.innerHTML = '<em style="color:var(--muted)">Nothing to preview yet.</em>'; return; }
  target.innerHTML = '<em style="color:var(--muted)">Rendering…</em>';
  try {
    const res = await apiFetch('/posts/preview.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ body_markdown: md }),
    });
    target.innerHTML = res.html || '<em style="color:var(--muted)">(empty)</em>';
  } catch (e) {
    target.innerHTML = `<span style="color:var(--red)">Preview failed: ${esc(e.message)}</span>`;
  }
}

function gatherPostBody() {
  const projectIds = Array.from(document.querySelectorAll('#post-project-checks input[name=post-project]:checked'))
    .map(el => parseInt(el.value, 10));
  let publishedAt = document.getElementById('post-published-at').value;
  if (publishedAt) publishedAt = publishedAt.replace('T', ' ') + ':00';
  return {
    title:         document.getElementById('post-title').value.trim(),
    slug:          document.getElementById('post-slug').value.trim(),
    excerpt:       document.getElementById('post-excerpt').value.trim(),
    cover_image:   document.getElementById('post-cover').value.trim(),
    tags:          document.getElementById('post-tags').value.split(',').map(s => s.trim()).filter(Boolean),
    body_markdown: document.getElementById('post-body').value,
    is_published:  document.getElementById('post-published').checked,
    published_at:  publishedAt || null,
    project_ids:   projectIds,
  };
}

async function savePost() {
  const id   = document.getElementById('post-id').value;
  const body = gatherPostBody();
  if (!body.title || !body.body_markdown.trim()) {
    toast('Title and body are required', true);
    return;
  }
  try {
    if (id) {
      await apiFetch(`/posts/?id=${id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      toast('Post updated');
    } else {
      await apiFetch('/posts/', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      toast('Post created');
    }
    postModal.close();
    await loadPosts();
  } catch (e) {
    toast('Save failed: ' + e.message, true);
  }
}

async function deleteCurrentPost() {
  const id = document.getElementById('post-id').value;
  if (!id) return;
  if (!confirm('Delete this post? This cannot be undone.')) return;
  try {
    await apiFetch(`/posts/?id=${id}`, { method: 'DELETE' });
    toast('Post deleted');
    postModal.close();
    await loadPosts();
  } catch (e) { toast('Delete failed: ' + e.message, true); }
}

async function deletePost(id) {
  if (!confirm('Delete this post? This cannot be undone.')) return;
  try {
    await apiFetch(`/posts/?id=${id}`, { method: 'DELETE' });
    toast('Post deleted');
    await loadPosts();
  } catch (e) { toast('Delete failed: ' + e.message, true); }
}

// ── Init — fetches run in parallel; each handles its own errors ─────────
loadProjects();
loadSettings();
loadSkills();
loadResume();
loadAudit();
loadPosts();
</script>
</body>
</html>
