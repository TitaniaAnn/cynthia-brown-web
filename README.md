# Portfolio — Setup Guide

A PHP/MySQL portfolio site with a writing/blog section, an admin dashboard, and OAuth-only login. Designed for shared hosting (Bluehost / cPanel). No build step — plain PHP, edit and deploy.

## Stack

- PHP 8.x, MySQL 8.x
- OAuth via Google and GitHub (no passwords)
- Vendored [Parsedown](includes/Parsedown.php) for markdown rendering (safe mode)
- Server-rendered writing pages with auto-regenerated RSS feed
- No Composer, no npm, no JS framework

## File structure

```
portfolio/
├── .env                        ← secrets and per-deploy config (gitignored)
├── .env.example                ← documents the keys
├── .htaccess                   ← rewrites root → public/, denies sensitive paths
├── config/config.php           ← reads .env into PHP constants
├── includes/                   ← shared helpers (auth, db, response, util,
│                                  upload, http, markdown, Parsedown)
├── api/
│   ├── auth/                   ← Google + GitHub OAuth flows, status, logout
│   ├── projects/               ← CRUD + JSON import
│   ├── posts/                  ← writing posts CRUD + markdown preview
│   ├── settings/               ← key/value site settings
│   ├── skills/                 ← About-section skill groups
│   ├── audit/                  ← read-only audit log feed
│   └── uploads/                ← image and resume upload endpoints
├── admin/
│   ├── index.php               ← dashboard (single-file, inline CSS+JS)
│   ├── login.php               ← OAuth provider picker
│   └── update.php              ← web-based DB migration runner
├── public/
│   ├── index.php               ← portfolio homepage
│   └── writing/                ← server-rendered writing index + post pages
├── sql/schema.sql              ← target schema for fresh installs
├── tests/                      ← zero-dependency test harness
└── uploads/                    ← project images, resume PDF (gitignored content)
```

## Setup

### 1. Upload files

Upload everything to your host with the directory structure intact. The web root should point at the project root — `.htaccess` rewrites traffic into `public/` from there.

### 2. Create the database

In cPanel / phpMyAdmin:
1. Create a MySQL database (e.g. `portfolio`) and a user with full privileges on it.
2. Open [sql/schema.sql](sql/schema.sql) and remove (or edit) the `CREATE DATABASE` and `USE` lines at the top — they reference a Bluehost-specific name.
3. Import the edited file. This creates all tables and seeds default site settings + sample projects.

For existing installs, schema changes are applied via [admin/update.php](admin/update.php) — log in, click "Run", and it applies any pending idempotent migrations.

### 3. Configure `.env`

Copy `.env.example` to `.env` and fill in:

| Key | Value |
|---|---|
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` | from step 2 |
| `APP_URL` | your full domain, no trailing slash (e.g. `https://yourdomain.com`) |
| `APP_SECRET` | run `openssl rand -hex 32` and paste |
| `ADMIN_EMAILS` | comma-separated allowlist of emails permitted to log in |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | from step 4 |
| `GITHUB_CLIENT_ID` / `GITHUB_CLIENT_SECRET` | from step 5 |

`.env` is gitignored and blocked from web access via `.htaccess`. Don't commit it.

### 4. Google OAuth

1. <https://console.cloud.google.com/apis/credentials> → **Create Credentials → OAuth client ID** → Web application.
2. Add Authorised redirect URI: `https://yourdomain.com/api/auth/google/callback.php`
3. Copy the Client ID and Client Secret into `.env`.
4. If the app is in testing mode, add yourself as a Test User on the OAuth consent screen.

### 5. GitHub OAuth

1. <https://github.com/settings/developers> → **New OAuth App**.
2. Authorization callback URL: `https://yourdomain.com/api/auth/github/callback.php`
3. Copy the Client ID, generate a Client Secret, paste both into `.env`.
4. Make sure the email on your GitHub account is set as **primary** and **verified**.

### 6. Enable HTTPS

Uncomment the HTTPS redirect block in `.htaccess` once your SSL certificate is active. Free SSL via Let's Encrypt is available on most cPanel hosts.

### 7. First login

1. Visit `https://yourdomain.com` — homepage loads.
2. Visit `https://yourdomain.com/admin/` — redirects to login.
3. Click **Continue with Google** or **Continue with GitHub**.
4. Your admin row is created automatically on first successful login (via the `INSERT ... ON DUPLICATE KEY` upsert in `auth.php`); the `ADMIN_EMAILS` allowlist in `.env` is what actually gates access.

## Admin features

| Panel | What it does |
|---|---|
| Projects | Title, descriptions, language, GitHub/demo links, tags, year, status. Drag to reorder. Per-project image gallery with star-to-mark-as-summary. Import / export as JSON — re-importing an existing title updates it in place when its data has changed. |
| Writing | Markdown posts with cover image, tags, draft/publish toggle, scheduled `published_at`, link-to-projects, live preview pane. RSS feed regenerates on save. |
| Skills | Grouped skill labels for the About section. |
| Settings | Name, role, bio, email, GitHub, LinkedIn, location, tagline, ticker text. |
| Resume | Upload a PDF; shown publicly via `/api/uploads/resume.php`. |
| Audit Log | Paginated read-only feed of `audit_log` rows — logins, denials, writes, migration runs. Substring filter on action name. |
| Account | OAuth profile, sign-out (POST + CSRF). |

## Security notes

- Only emails in `ADMIN_EMAILS` (`.env`) can log in. OAuth callbacks reject anyone else.
- Sessions live in MySQL with an 8-hour expiry. Cookies are `HttpOnly`, `Secure`, `SameSite=Lax`.
- PHP session ID is regenerated on login to defeat session fixation.
- CSRF: every session has a `csrf_token`. Write endpoints require an `X-CSRF-Token` header (or `csrf_token` field on the logout form).
- OAuth `state` parameter is validated and consumed on callback (single-use).
- All API write endpoints require a valid session.
- `config/`, `includes/`, `.env`, and `.backup/` are blocked from direct web access via `.htaccess`.
- Project image and resume upload directories have their own `.htaccess` that disables PHP execution.
- Markdown bodies render in Parsedown safe mode — raw HTML is escaped, `javascript:` links are stripped.
- Uploaded files use random hex filenames (no user-controlled paths).
- All SQL goes through PDO prepared statements.

## Development

To run locally you need PHP + MySQL — XAMPP, Laravel Herd, or `php -S localhost:8000` from the repo root all work.

### Tests

A zero-dependency test harness lives in [tests/](tests/). Run all:

```bash
php tests/run.php
```

Filter by basename substring:

```bash
php tests/run.php util       # only tests/test_util.php
php tests/run.php markdown   # only tests/test_markdown.php
```

Tests cover the pure helpers (`util.php`, `markdown.php`, `upload.php`) and the JSON parsing in `response.php`. They do not require a running database.

### Where AI agents read

If you're using Claude Code or a similar agent in this repo, [CLAUDE.md](CLAUDE.md) is the architecture overview to point them at.
