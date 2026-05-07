-- ============================================================
-- Portfolio Database Schema
-- ============================================================
--
-- This file is the *target* schema (what a fresh install should look like).
-- Existing installs are upgraded by /admin/update.php which runs the
-- migrations defined in admin/update.php.

CREATE DATABASE IF NOT EXISTS bfpcdjmy_programming CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bfpcdjmy_programming;

-- Allowed admin users (whitelist)
CREATE TABLE IF NOT EXISTS admins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(255) NOT NULL UNIQUE,
    name        VARCHAR(255),
    avatar_url  VARCHAR(512),
    provider    ENUM('google','github') NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Sessions (also stores per-session CSRF token for double-submit validation)
CREATE TABLE IF NOT EXISTS sessions (
    id          VARCHAR(128) PRIMARY KEY,
    admin_id    INT NOT NULL,
    csrf_token  VARCHAR(128) NOT NULL DEFAULT '',
    ip          VARCHAR(64),
    user_agent  TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME NOT NULL,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX idx_sessions_expires (expires_at)
);

-- Projects
CREATE TABLE IF NOT EXISTS projects (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    title             VARCHAR(255) NOT NULL,
    short_description TEXT,
    description       TEXT NOT NULL,
    language          VARCHAR(100) NOT NULL,
    tags              VARCHAR(512),
    github_url        VARCHAR(512),
    demo_url          VARCHAR(512),
    summary_image     VARCHAR(512),
    status            ENUM('active','wip','archived') DEFAULT 'active',
    sort_order        INT DEFAULT 0,
    year              YEAR NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_projects_sort (sort_order)
);

-- Project images (one-to-many)
CREATE TABLE IF NOT EXISTS project_images (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT NOT NULL,
    url         VARCHAR(512) NOT NULL,
    sort_order  INT DEFAULT 0,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_images_project (project_id)
);

-- Skill groups (About section)
CREATE TABLE IF NOT EXISTS skill_groups (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    label      VARCHAR(255) NOT NULL,
    skills     TEXT,
    sort_order INT DEFAULT 0,
    INDEX idx_skill_groups_sort (sort_order)
);

-- Site settings (key-value)
CREATE TABLE IF NOT EXISTS settings (
    `key`       VARCHAR(100) PRIMARY KEY,
    `value`     TEXT,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Writing posts (markdown blog/notes)
CREATE TABLE IF NOT EXISTS posts (
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
);

-- Posts ↔ Projects link table
CREATE TABLE IF NOT EXISTS post_projects (
    post_id    INT NOT NULL,
    project_id INT NOT NULL,
    PRIMARY KEY (post_id, project_id),
    FOREIGN KEY (post_id)    REFERENCES posts(id)    ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_post_projects_project (project_id)
);

-- Audit log (security-relevant events)
CREATE TABLE IF NOT EXISTS audit_log (
    id         BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_id   INT NULL,
    action     VARCHAR(64) NOT NULL,
    detail     VARCHAR(1024) NULL,
    ip         VARCHAR(64) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_log_created (created_at),
    INDEX idx_audit_log_admin   (admin_id)
);

-- Seed default settings
INSERT INTO settings (`key`, `value`) VALUES
  ('name',     'Your Name'),
  ('role',     'Full-Stack Developer'),
  ('email',    'you@example.com'),
  ('github',   'https://github.com/yourusername'),
  ('linkedin', ''),
  ('location', 'San Francisco, CA'),
  ('tagline',  'Building fast, reliable software'),
  ('years_exp','5+'),
  ('bio',      'A passionate developer who loves building great software.')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- Admin rows are created on first OAuth login via upsert_admin() — no
-- seed row is needed here. The whitelist of allowed emails lives in
-- ADMIN_EMAILS in .env and is the actual gate for is_allowed_email().

-- Sample projects
INSERT INTO projects (title, description, language, tags, github_url, demo_url, status, sort_order) VALUES
  ('Project Alpha', 'A high-performance REST API with real-time capabilities built on Node.js and PostgreSQL.', 'TypeScript', 'Node.js,PostgreSQL,REST', 'https://github.com', '', 'active', 1),
  ('DataSync CLI', 'Command-line tool for bidirectional database sync across environments with conflict resolution.', 'Go', 'CLI,Database,DevOps', 'https://github.com', '', 'active', 2),
  ('ReactFlow UI', 'Component library of 40+ accessible, animated React components with Storybook documentation.', 'JavaScript', 'React,Storybook,A11y', 'https://github.com', 'https://example.com', 'active', 3);
