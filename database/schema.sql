-- SafeCedi schema
--
-- OWNER: M2 (Data & Backend Core). M2 adds the other eleven tables here
-- (submissions, extracted_urls, url_resolutions, verdicts, verdict_signals,
-- rules, reports, report_votes, allowlist, reputation_cache, feedback).
--
-- M1 added `users` on Wed 18 Nov because auth had to ship in Phase 01.
-- M2: review it, keep the column names auth.php relies on
-- (id, email, display_name, password_hash, role, is_active,
--  failed_login_count, locked_until, last_login_at, created_at).
--
-- Rebuild from empty:   mysql -u USER -p DBNAME < database/schema.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    -- Stored lower-cased by the app; 254 is the longest valid email address.
    email               VARCHAR(254)     NOT NULL,
    display_name        VARCHAR(60)      NOT NULL,
    -- password_hash() output. 255 leaves room for future algorithms (Argon2 etc).
    password_hash       VARCHAR(255)     NOT NULL,
    -- Anonymous visitors have no row at all; they never need one.
    role                ENUM('reporter', 'moderator') NOT NULL DEFAULT 'reporter',
    is_active           TINYINT(1)       NOT NULL DEFAULT 1,
    -- Brute-force throttle: 5 misses -> locked_until = NOW() + 15 minutes.
    failed_login_count  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until        DATETIME         NULL,
    last_login_at       DATETIME         NULL,
    created_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Login looks users up by email, and this also makes duplicates impossible
    -- even when two sign-ups race each other.
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
