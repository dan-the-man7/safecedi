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
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- Stored lower-cased by the app; 254 is the longest valid email address.
    email VARCHAR(254) NOT NULL,
    display_name VARCHAR(60) NOT NULL,
    -- password_hash() output. 255 leaves room for future algorithms (Argon2 etc).
    password_hash VARCHAR(255) NOT NULL,
    -- Anonymous visitors have no row at all; they never need one.
    role ENUM('reporter', 'moderator') NOT NULL DEFAULT 'reporter',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    -- Brute-force throttle: 5 misses -> locked_until = NOW() + 15 minutes.
    failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Login looks users up by email, and this also makes duplicates impossible
    -- even when two sign-ups race each other.
    --
    -- M2, 28 Nov: prefixed to 191 characters. MySQL 5.6 caps an index key at
    -- 767 bytes and VARCHAR(254) in utf8mb4 is 1016, so the table would not
    -- create at all on an older server. 191 x 4 = 764, just under. No real
    -- email address collides in its first 191 characters. M1, revert if you
    -- would rather set ROW_FORMAT=DYNAMIC instead.
    UNIQUE KEY uq_users_email (email(191)),
    KEY idx_users_role (role)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------
-- M2 from here down. Tables are in dependency order so the file runs top to
-- bottom on an empty database without a foreign key failing.
-- ---------------------------------------------------------------------------
-- One pasted message. Deliberately thin: everything else hangs off it.
CREATE TABLE IF NOT EXISTS submissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- NULL because the plan allows submitting without an account. Deleting a
    -- user must not destroy the evidence they contributed, hence SET NULL.
    user_id INT UNSIGNED NULL,
    raw_text TEXT NOT NULL,
    -- The sender as the victim reported it, which may be a number or a name.
    sender_number VARCHAR(32) NULL,
    -- Hashed, never stored in the clear. It is only ever needed to rate limit
    -- and to spot one person flooding the form.
    ip_hash CHAR(64) NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Required by the build plan. The scam wall orders by this and the trend
    -- queries filter on it, so without the index both table-scan.
    KEY idx_submissions_submitted_at (submitted_at),
    CONSTRAINT fk_submissions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Every URL found inside one submission, after normalising.
CREATE TABLE IF NOT EXISTS extracted_urls (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id INT UNSIGNED NOT NULL,
    -- Exactly as it appeared, so the evidence panel can quote the original.
    raw_url VARCHAR(2048) NOT NULL,
    -- After punycode, case and obfuscated-dot handling, which is M3's job.
    normalised_url VARCHAR(2048) NOT NULL,
    -- Split out because nearly every lookup is by host, not by whole URL.
    host VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Required by the build plan. "Have we seen this host before" runs on
    -- every submission and against the allowlist and reputation cache.
    --
    -- Prefixed to 191 characters for the same reason as users.email: MySQL
    -- 5.6 caps an index key at 767 bytes and VARCHAR(255) in utf8mb4 is 1020.
    -- A hostname is never close to 191 characters in practice, so nothing is
    -- lost. Every host and value index below is prefixed the same way.
    KEY idx_extracted_urls_host (host(191)),
    -- A submission's URLs are meaningless on their own, so they go with it.
    CONSTRAINT fk_extracted_urls_submission FOREIGN KEY (submission_id) REFERENCES submissions (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- One row per redirect hop. This is the audit trail for the SSRF guard: it
-- proves the address was re-checked at every hop, not only the first.
CREATE TABLE IF NOT EXISTS url_resolutions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    url_id INT UNSIGNED NOT NULL,
    -- Capped at five hops by the guard, so a TINYINT is plenty.
    hop_number TINYINT UNSIGNED NOT NULL,
    hop_url VARCHAR(2048) NOT NULL,
    hop_host VARCHAR(255) NOT NULL,
    -- 45 characters covers an IPv6 address in full.
    resolved_ip VARCHAR(45) NULL,
    status_code SMALLINT UNSIGNED NULL,
    -- Set when the guard refused the hop, with the reason in plain words, so
    -- the refusal can be shown on screen and read back in the viva.
    refused TINYINT(1) NOT NULL DEFAULT 0,
    refused_reason VARCHAR(120) NULL,
    resolved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_url_resolutions_hop_host (hop_host(191)),
    CONSTRAINT fk_url_resolutions_url FOREIGN KEY (url_id) REFERENCES extracted_urls (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- The rule engine lives in the database rather than in the code, so a weight
-- can be tuned or a rule switched off without a deploy.
CREATE TABLE IF NOT EXISTS rules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- Stable short code. Signals reference the rule by id, but the code is
    -- what appears in the test plan and the rule-health dashboard.
    rule_code VARCHAR(32) NOT NULL,
    family ENUM('language', 'sender', 'link', 'reputation') NOT NULL,
    pattern VARCHAR(255) NOT NULL,
    -- 'flag' means the engine decides it in code (allowlist hit, Safe Browsing
    -- result) rather than by matching the pattern against text.
    pattern_type ENUM('keyword', 'regex', 'flag') NOT NULL,
    -- Signed, because the allowlist rule carries a negative weight and has to
    -- be able to pull a score back down.
    weight DECIMAL(5, 2) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    description VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rules_rule_code (rule_code),
    KEY idx_rules_family (family)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- The score and band for one submission.
CREATE TABLE IF NOT EXISTS verdicts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id INT UNSIGNED NOT NULL,
    score DECIMAL(5, 2) NOT NULL,
    -- Four bands, never a bare yes or no. "Unclear" is a real answer.
    band ENUM(
        'looks_safe',
        'unclear',
        'suspicious',
        'dangerous'
    ) NOT NULL,
    -- Stored so an old verdict can be told apart from a new one after the
    -- rules change, which is what makes the re-score path honest.
    engine_version VARCHAR(16) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_verdicts_band (band),
    CONSTRAINT fk_verdicts_submission FOREIGN KEY (submission_id) REFERENCES submissions (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Which rule fired, what it was worth, and the exact text it matched.
--
-- This is the table the whole product rests on. The evidence panel the user
-- reads, the rule-health dashboard and the ability to re-score an old
-- submission all come out of here, which is why the matched text is stored
-- and not just the score.
CREATE TABLE IF NOT EXISTS verdict_signals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    verdict_id INT UNSIGNED NOT NULL,
    rule_id INT UNSIGNED NOT NULL,
    -- The weight as applied at the time, not read live from rules, so an old
    -- verdict still explains itself after a weight is retuned.
    weight_contributed DECIMAL(5, 2) NOT NULL,
    matched_text VARCHAR(255) NULL,
    PRIMARY KEY (id),
    -- The dashboard counts fires per rule, so this index carries that query.
    KEY idx_verdict_signals_rule (rule_id),
    CONSTRAINT fk_verdict_signals_verdict FOREIGN KEY (verdict_id) REFERENCES verdicts (id) ON DELETE CASCADE,
    -- No CASCADE here on purpose: a rule must not be deletable while verdicts
    -- still cite it, or the evidence would quietly lose its reasons.
    CONSTRAINT fk_verdict_signals_rule FOREIGN KEY (rule_id) REFERENCES rules (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Crowd reports of a number or a host.
CREATE TABLE IF NOT EXISTS reports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    report_type ENUM('number', 'host') NOT NULL,
    reported_value VARCHAR(255) NOT NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- The engine asks "is this number already reported" on every submission.
    KEY idx_reports_value (reported_value(191)),
    CONSTRAINT fk_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Corroboration, which is what reporter trust is derived from.
CREATE TABLE IF NOT EXISTS report_votes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    agrees TINYINT(1) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- One vote each. Enforced here rather than in PHP, so two clicks landing
    -- at the same moment cannot both get through.
    UNIQUE KEY uq_report_votes_one_each (report_id, user_id),
    CONSTRAINT fk_report_votes_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
    CONSTRAINT fk_report_votes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Known good hosts. Every entry carries a written reason, because an
-- allowlist nobody can explain is how a real scam gets waved through.
CREATE TABLE IF NOT EXISTS allowlist (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    host VARCHAR(255) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    added_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_allowlist_host (host(191)),
    CONSTRAINT fk_allowlist_user FOREIGN KEY (added_by) REFERENCES users (id) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Safe Browsing answers, cached with a time to live so the engine still
-- scores when the API is slow or down.
CREATE TABLE IF NOT EXISTS reputation_cache (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    host VARCHAR(255) NOT NULL,
    source VARCHAR(32) NOT NULL,
    is_flagged TINYINT(1) NOT NULL,
    raw_response TEXT NULL,
    fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    -- One cached answer per host per source, so a repeated lookup updates
    -- rather than piling up duplicates.
    UNIQUE KEY uq_reputation_cache_host_source (host(191), source),
    -- Expiry sweeps read this.
    KEY idx_reputation_cache_expires (expires_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- The "this was wrong" loop. This is what feeds the rule-health dashboard
-- and lets a bad rule be demoted in public rather than quietly.
CREATE TABLE IF NOT EXISTS feedback (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    verdict_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    was_wrong TINYINT(1) NOT NULL,
    comment VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_feedback_verdict FOREIGN KEY (verdict_id) REFERENCES verdicts (id) ON DELETE CASCADE,
    CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- The labelled corpus lives in database/corpus.csv, not in a table.
-- If M5 needs it queryable in SQL, raise it before adding a twelfth table.