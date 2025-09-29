-- DMARC Dashboard Schema
-- This file contains the database schema for the DMARC dashboard application

-- Brands/Organizations table for grouping domains
CREATE TABLE brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    logo_url VARCHAR(500),
    color_scheme VARCHAR(7) DEFAULT '#007bff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name)
);

-- Domains table for tracking managed domains
CREATE TABLE domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL,
    brand_id INT,
    dmarc_policy ENUM('none', 'quarantine', 'reject') DEFAULT 'none',
    dmarc_record TEXT,
    spf_record TEXT,
    dkim_selectors JSON,
    mta_sts_enabled BOOLEAN DEFAULT FALSE,
    bimi_enabled BOOLEAN DEFAULT FALSE,
    dnssec_enabled BOOLEAN DEFAULT FALSE,
    retention_days INT DEFAULT 365,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_checked TIMESTAMP NULL,
    UNIQUE KEY unique_domain (domain),
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
    INDEX idx_domain_active (domain, is_active),
    INDEX idx_brand_domains (brand_id),
    INDEX idx_last_checked (last_checked)
);

-- DMARC aggregate reports (RUA) storage
CREATE TABLE dmarc_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    report_id VARCHAR(255) NOT NULL,
    org_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    extra_contact_info TEXT,
    report_begin TIMESTAMP NOT NULL,
    report_end TIMESTAMP NOT NULL,
    policy_domain VARCHAR(255) NOT NULL,
    policy_adkim ENUM('r', 's') DEFAULT 'r',
    policy_aspf ENUM('r', 's') DEFAULT 'r',
    policy_p ENUM('none', 'quarantine', 'reject') NOT NULL,
    policy_sp ENUM('none', 'quarantine', 'reject'),
    policy_pct INT DEFAULT 100,
    raw_xml LONGTEXT,
    processed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    UNIQUE KEY unique_report (domain_id, report_id, org_name),
    INDEX idx_report_period (report_begin, report_end),
    INDEX idx_policy_domain (policy_domain),
    INDEX idx_processed (processed),
    INDEX idx_created_date (created_at)
);

-- DMARC report records (individual records within a report)
CREATE TABLE dmarc_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    source_ip VARCHAR(45) NOT NULL,
    count INT NOT NULL DEFAULT 1,
    disposition ENUM('none', 'quarantine', 'reject') NOT NULL,
    dkim_result ENUM('pass', 'fail', 'neutral', 'temperror', 'permerror') DEFAULT 'neutral',
    dkim_domain VARCHAR(255),
    spf_result ENUM('pass', 'fail', 'neutral', 'softfail', 'temperror', 'permerror') DEFAULT 'neutral',
    spf_domain VARCHAR(255),
    dmarc_result ENUM('pass', 'fail') NOT NULL,
    header_from VARCHAR(255) NOT NULL,
    envelope_from VARCHAR(255),
    envelope_to VARCHAR(255),
    subject_sample TEXT,
    auth_results TEXT,
    pct INT DEFAULT 100,
    reason_type ENUM('forwarded', 'sampled_out', 'trusted_forwarder', 'mailing_list', 'local_policy', 'other'),
    reason_comment TEXT,
    identifiers JSON,
    geo_country VARCHAR(2),
    geo_region VARCHAR(255),
    geo_city VARCHAR(255),
    asn_number INT,
    asn_org VARCHAR(255),
    is_whitelisted BOOLEAN DEFAULT FALSE,
    reputation_score INT,
    threat_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    notes TEXT,
    tags JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES dmarc_reports(id) ON DELETE CASCADE,
    INDEX idx_source_ip (source_ip),
    INDEX idx_dmarc_result (dmarc_result),
    INDEX idx_dkim_spf_results (dkim_result, spf_result),
    INDEX idx_disposition (disposition),
    INDEX idx_header_from (header_from),
    INDEX idx_geo_location (geo_country, geo_region),
    INDEX idx_asn (asn_number),
    INDEX idx_threat_level (threat_level),
    INDEX idx_report_created (report_id, created_at)
);

-- Forensic/Failure reports (RUF) storage
CREATE TABLE dmarc_forensic_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    source_ip VARCHAR(45) NOT NULL,
    arrival_date TIMESTAMP NOT NULL,
    message_id VARCHAR(255),
    subject VARCHAR(500),
    header_from VARCHAR(255) NOT NULL,
    envelope_from VARCHAR(255),
    envelope_to VARCHAR(255),
    dkim_signature TEXT,
    dkim_result ENUM('pass', 'fail', 'neutral', 'temperror', 'permerror') DEFAULT 'neutral',
    spf_result ENUM('pass', 'fail', 'neutral', 'softfail', 'temperror', 'permerror') DEFAULT 'neutral',
    dmarc_result ENUM('pass', 'fail') NOT NULL,
    authentication_results TEXT,
    original_headers TEXT,
    original_body LONGTEXT,
    reported_domain VARCHAR(255),
    sample_headers_only BOOLEAN DEFAULT FALSE,
    feedback_type ENUM('abuse', 'fraud', 'other') DEFAULT 'abuse',
    raw_email LONGTEXT,
    processed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    INDEX idx_source_ip (source_ip),
    INDEX idx_arrival_date (arrival_date),
    INDEX idx_dmarc_result (dmarc_result),
    INDEX idx_header_from (header_from),
    INDEX idx_processed (processed)
);

-- SMTP TLS reports storage
CREATE TABLE smtp_tls_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    report_id VARCHAR(255) NOT NULL,
    organization_name VARCHAR(255) NOT NULL,
    date_range_begin TIMESTAMP NOT NULL,
    date_range_end TIMESTAMP NOT NULL,
    contact_info VARCHAR(255),
    report_data JSON,
    total_successful_sessions INT DEFAULT 0,
    total_failure_sessions INT DEFAULT 0,
    raw_json LONGTEXT,
    processed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tls_report (domain_id, report_id, organization_name),
    INDEX idx_report_period (date_range_begin, date_range_end),
    INDEX idx_processed (processed)
);

-- SMTP TLS policies and results
CREATE TABLE smtp_tls_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    policy_type ENUM('sts', 'tlsa', 'no-policy-found') NOT NULL,
    policy_string TEXT,
    policy_domain VARCHAR(255) NOT NULL,
    mx_host VARCHAR(255) NOT NULL,
    successful_session_count INT DEFAULT 0,
    failure_session_count INT DEFAULT 0,
    failure_reasons JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES smtp_tls_reports(id) ON DELETE CASCADE,
    INDEX idx_policy_domain (policy_domain),
    INDEX idx_mx_host (mx_host),
    INDEX idx_policy_type (policy_type)
);

-- User roles and permissions
CREATE TABLE user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_name (name)
);

-- User assignments to brands
CREATE TABLE user_brand_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    brand_id INT NOT NULL,
    role_id INT NOT NULL,
    granted_by VARCHAR(255),
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (username) REFERENCES users(username) ON DELETE CASCADE,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES user_roles(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_user_brand (username, brand_id),
    INDEX idx_username_active (username, is_active),
    INDEX idx_brand_users (brand_id),
    INDEX idx_expires_at (expires_at)
);

-- Alert rules configuration
CREATE TABLE alert_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT,
    brand_id INT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    rule_type ENUM('threshold', 'anomaly', 'failure', 'policy_violation') NOT NULL,
    conditions JSON NOT NULL,
    actions JSON NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    cooldown_minutes INT DEFAULT 60,
    last_triggered TIMESTAMP NULL,
    created_by VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(username) ON DELETE RESTRICT,
    INDEX idx_domain_rules (domain_id, is_active),
    INDEX idx_brand_rules (brand_id, is_active),
    INDEX idx_rule_type (rule_type),
    INDEX idx_last_triggered (last_triggered)
);

-- Alert instances and notifications
CREATE TABLE alert_instances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    status ENUM('active', 'acknowledged', 'resolved', 'suppressed') DEFAULT 'active',
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged_at TIMESTAMP NULL,
    acknowledged_by VARCHAR(255) NULL,
    resolved_at TIMESTAMP NULL,
    resolved_by VARCHAR(255) NULL,
    related_data JSON,
    notification_sent BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (rule_id) REFERENCES alert_rules(id) ON DELETE CASCADE,
    FOREIGN KEY (acknowledged_by) REFERENCES users(username) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(username) ON DELETE SET NULL,
    INDEX idx_rule_status (rule_id, status),
    INDEX idx_triggered_at (triggered_at),
    INDEX idx_severity_status (severity, status)
);

-- Audit logging for security and compliance
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255),
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50),
    resource_id VARCHAR(255),
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (username) REFERENCES users(username) ON DELETE SET NULL,
    INDEX idx_username_timestamp (username, timestamp),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_timestamp (timestamp)
);

-- Domain health scores and metrics
CREATE TABLE domain_health_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    date DATE NOT NULL,
    dmarc_compliance_score INT DEFAULT 0,
    spf_alignment_score INT DEFAULT 0,
    dkim_alignment_score INT DEFAULT 0,
    volume_score INT DEFAULT 0,
    reputation_score INT DEFAULT 0,
    threat_score INT DEFAULT 0,
    overall_score INT DEFAULT 0,
    total_messages INT DEFAULT 0,
    passed_messages INT DEFAULT 0,
    failed_messages INT DEFAULT 0,
    metrics JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    UNIQUE KEY unique_domain_date (domain_id, date),
    INDEX idx_date_score (date, overall_score),
    INDEX idx_domain_date (domain_id, date)
);

-- Ingestion sources configuration
CREATE TABLE ingestion_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('imap', 'microsoft_graph', 'gmail_api', 'manual_upload', 'webhook') NOT NULL,
    config JSON NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_sync TIMESTAMP NULL,
    sync_frequency_minutes INT DEFAULT 60,
    error_count INT DEFAULT 0,
    last_error TEXT,
    created_by VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(username) ON DELETE RESTRICT,
    INDEX idx_type_active (type, is_active),
    INDEX idx_last_sync (last_sync),
    INDEX idx_created_by (created_by)
);

-- Insert default roles
INSERT INTO user_roles (name, description, permissions) VALUES 
('admin', 'Full system administrator', '["*"]'),
('domain_manager', 'Manage domains and reports', '["domains.*", "reports.*", "alerts.*"]'),
('analyst', 'View reports and analytics', '["reports.view", "analytics.view", "domains.view"]'),
('viewer', 'Read-only access', '["reports.view", "domains.view"]');

-- Insert sample brand
INSERT INTO brands (name, description) VALUES 
('Default Organization', 'Default brand for uncategorized domains');