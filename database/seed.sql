-- SafeCedi seed data
--
-- OWNER: M2 (Data & Backend Core). The 25 starter rules and the allowlist.
-- The labelled corpus is NOT here: it lives in database/corpus.csv.
--
-- Load after the schema:  mysql -u USER -p DBNAME < database/seed.sql

SET NAMES utf8mb4;

-- 25 starter rules.
--
-- Weights are additive. The bands are decided by M3 from the corpus run,
-- not guessed here, so these weights are a starting point to be tuned in
-- Phase 2 against real precision and recall.
--
-- A negative weight pulls a score down. The allowlist rule is the only
-- one with a negative weight, and that is deliberate: a known good host
-- should be able to rescue a message that other rules were suspicious of.

-- Language, ten rules. These are the ten M3 implements in Phase 1.

INSERT INTO rules(rule_code, family, pattern, pattern_type, weight, description) VALUES
('LANG_URGENCY','language','within (24|48) hours|immediately|expires today|act now','regex',12.00,'Urgency window pressuring a fast decision'),
('LANG_AUTHORITY','language','mtn|vodafone|airteltigo|telecel|ecg|customer (care|service)','regex',10.00,'Impersonating a network, utility or support desk'),
('LANG_PIN','language','your pin|send.{0,10}pin|enter.{0,10}pin|share.{0,10}code','regex',30.00,'Asking for a PIN or one time code'),
('LANG_REVERSAL','language','mistakenly sent|wrongly sent|sent it to you by mistake|send it back','regex',25.00,'The mistaken transfer reversal story'),
('LANG_PRIZE','language','you have won|congratulations|claim your (prize|reward)|lucky winner','regex',18.00,'Prize framing'),
('LANG_SIMBLOCK','language','sim.{0,10}(will be )?(blocked|deactivated|suspended)','regex',20.00,'Threat to block the SIM'),
('LANG_SECRECY','language','do not tell|dont tell anyone|keep this (private|confidential)','regex',15.00,'Asking the reader to keep it secret'),
('LANG_PAYREQ','language','send (ghs|gh|cedis)|transfer the money|pay the (fee|charge)','regex',14.00,'Direct request for money'),
('LANG_CLICKBAIT','language','click (here|this link|below)|follow this link','regex',8.00,'Pushing the reader onto a link'),
('LANG_THREAT','language','legal action|account will be (closed|locked)|final warning','regex',16.00,'Threat of consequences');


-- Sender, four rules.

INSERT INTO rules(rule_code, family, pattern, pattern_type, weight, description) VALUES
('SEND_PERSONAL','sender','^(\\+?233|0)[0-9]{9}$','regex',18.00,'A personal number presenting itself as a shortcode'),
('SEND_REPORTED','sender','in_reports','flag',22.00,'The number already appears in the reports table'),
('SEND_FOREIGN','sender','^\\+(?!233)[0-9]{6,15}$','regex',12.00,'International number for a supposedly local service'),
('SEND_SHORTCODE_CLAIM','sender','short ?code|official number','regex',10.00,'Claiming to be an official shortcode');


-- Link, seven rules.

INSERT INTO rules(rule_code, family, pattern, pattern_type, weight, description) VALUES
('LINK_SHORTENER','link','bit\\.ly|tinyurl|t\\.co|cutt\\.ly|rb\\.gy|is\\.gd','regex',14.00,'URL shortener hiding the real destination'),
('LINK_IP_HOST','link','^https?://[0-9]{1,3}(\\.[0-9]{1,3}){3}','regex',28.00,'Host is a bare IP address rather than a name'),
('LINK_PUNYCODE','link','xn--','keyword',26.00,'Punycode host, often a lookalike of a real brand'),
('LINK_SUBDOMAINS','link','excessive_subdomains','flag',12.00,'More subdomain levels than a real service would use'),
('LINK_RISKY_TLD','link','\\.(tk|ml|ga|cf|gq|xyz|top|click)$','regex',16.00,'Top level domain commonly used for throwaway hosts'),
('LINK_CRED_PATH','link','/(login|signin|verify|secure|account|update)','regex',15.00,'Path shaped like a credential capture page'),
('LINK_LOOKALIKE','link','lookalike_host','flag',30.00,'Host within a short edit distance of a known brand');


-- Reputation, four rules.

INSERT INTO rules(rule_code, family, pattern, pattern_type, weight, description) VALUES
('REP_SAFEBROWSING','reputation','safe_browsing_flagged','flag',35.00,'Google Safe Browsing flagged this host'),
('REP_CROWD','reputation','crowd_reports','flag',20.00,'Reported by more than one trusted reporter'),
('REP_UNREACHABLE','reputation','host_unreachable','flag',5.00,'The host could not be reached, so it is unclear not safe'),
('REP_ALLOWLIST','reputation','on_allowlist','flag',-40.00,'Host is on the allowlist with a written reason');


-- Allowlist.
--
-- Every entry needs a written reason. These are the hosts our own corpus
-- of legitimate messages points at.

INSERT INTO allowlist(host, reason) VALUES
('mtn.com.gh','MTN Ghana official domain, verified against their published site'),
('telecel.com.gh','Telecel Ghana official domain'),
('ecg.com.gh','Electricity Company of Ghana official domain'),
('gcb.com.gh','GCB Bank official domain'),
('ghanapost.com.gh','Ghana Post official domain');


-- The 60 labelled messages are in database/corpus.csv, so the team can add
-- real ones without editing SQL. M5's harness reads the CSV directly.
--
-- WARNING: those 60 rows are placeholders written from the scam patterns in
-- the build plan. The plan requires real messages collected from the team's
-- own phones. Replace them before any precision or recall figure is quoted.