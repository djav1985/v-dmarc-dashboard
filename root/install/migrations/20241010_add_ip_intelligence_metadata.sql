-- Migration: Augment ip_intelligence cache with RDAP, DNSBL, and reputation metadata
-- Run the section that matches your database engine.

-- MySQL / MariaDB -----------------------------------------------------------
ALTER TABLE ip_intelligence
    ADD COLUMN rdap_registry VARCHAR(32) AFTER is_proxy,
    ADD COLUMN rdap_network_range VARCHAR(100) AFTER rdap_registry,
    ADD COLUMN rdap_network_start VARCHAR(45) AFTER rdap_network_range,
    ADD COLUMN rdap_network_end VARCHAR(45) AFTER rdap_network_start,
    ADD COLUMN rdap_contacts JSON AFTER rdap_network_end,
    ADD COLUMN rdap_raw JSON AFTER rdap_contacts,
    ADD COLUMN rdap_checked_at TIMESTAMP NULL AFTER rdap_raw,
    ADD COLUMN dnsbl_listed BOOLEAN DEFAULT FALSE AFTER rdap_checked_at,
    ADD COLUMN dnsbl_sources JSON AFTER dnsbl_listed,
    ADD COLUMN dnsbl_last_checked TIMESTAMP NULL AFTER dnsbl_sources,
    ADD COLUMN reputation_score INT AFTER dnsbl_last_checked,
    ADD COLUMN reputation_context JSON AFTER reputation_score,
    ADD COLUMN reputation_last_checked TIMESTAMP NULL AFTER reputation_context;

CREATE INDEX idx_ip_intelligence_registry ON ip_intelligence(rdap_registry);
CREATE INDEX idx_ip_intelligence_dnsbl ON ip_intelligence(dnsbl_listed, dnsbl_last_checked);
CREATE INDEX idx_ip_intelligence_reputation ON ip_intelligence(reputation_score);

-- SQLite --------------------------------------------------------------------
-- Each ADD COLUMN must be executed separately when using SQLite.
ALTER TABLE ip_intelligence ADD COLUMN rdap_registry TEXT;
ALTER TABLE ip_intelligence ADD COLUMN rdap_network_range TEXT;
ALTER TABLE ip_intelligence ADD COLUMN rdap_network_start TEXT;
ALTER TABLE ip_intelligence ADD COLUMN rdap_network_end TEXT;
ALTER TABLE ip_intelligence ADD COLUMN rdap_contacts TEXT;
ALTER TABLE ip_intelligence ADD COLUMN rdap_raw TEXT;
ALTER TABLE ip_intelligence ADD COLUMN rdap_checked_at DATETIME;
ALTER TABLE ip_intelligence ADD COLUMN dnsbl_listed INTEGER DEFAULT 0;
ALTER TABLE ip_intelligence ADD COLUMN dnsbl_sources TEXT;
ALTER TABLE ip_intelligence ADD COLUMN dnsbl_last_checked DATETIME;
ALTER TABLE ip_intelligence ADD COLUMN reputation_score INTEGER;
ALTER TABLE ip_intelligence ADD COLUMN reputation_context TEXT;
ALTER TABLE ip_intelligence ADD COLUMN reputation_last_checked DATETIME;

CREATE INDEX IF NOT EXISTS idx_ip_intelligence_registry ON ip_intelligence(rdap_registry);
CREATE INDEX IF NOT EXISTS idx_ip_intelligence_dnsbl ON ip_intelligence(dnsbl_listed, dnsbl_last_checked);
CREATE INDEX IF NOT EXISTS idx_ip_intelligence_reputation ON ip_intelligence(reputation_score);
