-- =====================================================================
-- DerZ — STEP 45: DERZ ADMIN PORTAL (the DerZ team's own portal, replacing Platform in the app)
--
-- Run once on the DerZ database (re-runnable: tables / columns only added when missing, the
-- built-in roles only inserted when missing). Take a backup first. Needs steps 01-44.
--
--  * admin_staff / admin_roles: the team's own accounts (not app users) with role-based access.
--    The first account (Owner) is made on /setup (see README) or with the command line.
--  * admin_sessions: the portal's sessions (CodeIgniter database sessions), so they can be ended.
--  * admin_login_attempts: failed sign-ins (a pause after too many).
--  * admin_audit: who in the team did what (businesses changed, payments approved ...).
--  * business_crm / business_notes: the business's account manager (agent), stage, tags, next
--    follow-up, and the team's notes on it.
--  * platform_announcements: messages shown at the top of the app to all / some businesses.
--  * businesses.status: 'suspended' blocks the business in the app (with the reason shown).
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_sessions` (
  `id` varchar(128) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `timestamp` int(10) unsigned NOT NULL DEFAULT 0,
  `data` blob NOT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_sessions_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `admin_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `permissions` text NOT NULL COMMENT 'JSON list of permission keys; ["*"] = everything',
  `is_owner` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'the Owner role: everything, cannot be changed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `admin_staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `last_seen_at` datetime DEFAULT NULL,
  `last_ip` varchar(45) NOT NULL DEFAULT '',
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_staff_email` (`email`),
  KEY `idx_admin_staff_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `admin_login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_login_ip` (`ip`, `created_at`),
  KEY `idx_admin_login_email` (`email`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `admin_audit` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL DEFAULT 0,
  `action` varchar(30) NOT NULL,
  `entity` varchar(40) NOT NULL,
  `entity_id` int(11) NOT NULL DEFAULT 0,
  `business_id` int(11) NOT NULL DEFAULT 0,
  `summary` varchar(255) NOT NULL DEFAULT '',
  `details` mediumtext NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_audit_created` (`created_at`),
  KEY `idx_admin_audit_business` (`business_id`, `created_at`),
  KEY `idx_admin_audit_staff` (`staff_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `business_crm` (
  `business_id` int(11) NOT NULL,
  `agent_id` int(11) DEFAULT NULL COMMENT 'admin_staff.id: the account manager',
  `stage` varchar(20) NOT NULL DEFAULT 'onboarding' COMMENT 'onboarding, active, at_risk, churned, vip',
  `tags` varchar(255) NOT NULL DEFAULT '',
  `next_follow_up` date DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`business_id`),
  KEY `idx_business_crm_agent` (`agent_id`),
  KEY `idx_business_crm_follow_up` (`next_follow_up`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `business_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `kind` varchar(10) NOT NULL DEFAULT 'note' COMMENT 'note, call, visit, email',
  `note` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_business_notes_business` (`business_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `platform_announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `body` text NOT NULL,
  `level` varchar(10) NOT NULL DEFAULT 'info' COMMENT 'info, success, warning, danger',
  `audience` varchar(20) NOT NULL DEFAULT 'all' COMMENT 'all, trial, paid, expiring, or package:<id>',
  `starts_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ends_at` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_announcements_live` (`active`, `starts_at`, `ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `businesses`
  ADD COLUMN IF NOT EXISTS `status` varchar(10) NOT NULL DEFAULT 'active' COMMENT 'active, suspended (blocked in the app)',
  ADD COLUMN IF NOT EXISTS `suspended_reason` varchar(255) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS `suspended_at` datetime DEFAULT NULL;

-- the built-in roles (change them in the portal; the Owner role stays as it is)
INSERT IGNORE INTO admin_roles (id, name, description, permissions, is_owner) VALUES
(1, 'Owner', 'Everything, including staff and roles', '["*"]', 1),
(2, 'Administrator', 'Runs the platform: businesses, billing, packages, settings, staff', '["dashboard.view","analytics.view","businesses.view","businesses.view_all","businesses.edit","businesses.suspend","businesses.assign","businesses.notes","businesses.export","users.manage","billing.view","billing.approve","billing.record","packages.manage","online.view","sessions.end","messages.manage","translations.manage","settings.manage","staff.manage","audit.view"]', 0),
(3, 'Finance', 'Payments, subscriptions, packages and revenue', '["dashboard.view","analytics.view","businesses.view","businesses.view_all","businesses.export","billing.view","billing.approve","billing.record","packages.manage","audit.view"]', 0),
(4, 'Support', 'Helps businesses: their users, sessions and announcements', '["dashboard.view","businesses.view","businesses.view_all","businesses.edit","businesses.notes","users.manage","billing.view","online.view","sessions.end","messages.manage","translations.manage"]', 0),
(5, 'Sales agent', 'Their own businesses: follow-ups, notes, payments to record', '["dashboard.view","businesses.view","businesses.notes","billing.view","billing.record"]', 0),
(6, 'Viewer', 'Sees the dashboard, analytics and businesses', '["dashboard.view","analytics.view","businesses.view","businesses.view_all","billing.view"]', 0);

-- texts the app shows for a suspended business (English, Amharic)
INSERT INTO languages (word, english, amharic)
SELECT * FROM (SELECT 'business_suspended' AS word, 'This business is suspended' AS english, 'ይህ ንግድ ታግዷል' AS amharic) t
WHERE NOT EXISTS (SELECT 1 FROM languages l WHERE l.word = 'business_suspended');
INSERT INTO languages (word, english, amharic)
SELECT * FROM (SELECT 'business_suspended_help' AS word, 'The DerZ team has paused this business. Your data is kept. Please contact us to continue.' AS english,
    'የደርዝ ቡድን ይህን ንግድ ለጊዜው አቁሞታል። መረጃዎ ተጠብቋል። ለመቀጠል እባክዎ ያግኙን።' AS amharic) t
WHERE NOT EXISTS (SELECT 1 FROM languages l WHERE l.word = 'business_suspended_help');
UPDATE languages SET english = 'This business is suspended', amharic = 'ይህ ንግድ ታግዷል' WHERE word = 'business_suspended';
UPDATE languages SET english = 'The DerZ team has paused this business. Your data is kept. Please contact us to continue.',
    amharic = 'የደርዝ ቡድን ይህን ንግድ ለጊዜው አቁሞታል። መረጃዎ ተጠብቋል። ለመቀጠል እባክዎ ያግኙን።' WHERE word = 'business_suspended_help';
