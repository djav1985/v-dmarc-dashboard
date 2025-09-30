PRAGMA foreign_keys = ON;

CREATE TABLE users (
    username TEXT PRIMARY KEY,
    password TEXT NOT NULL,
    admin INTEGER NOT NULL DEFAULT 0,
    role TEXT DEFAULT 'viewer',
    first_name TEXT,
    last_name TEXT,
    email TEXT,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ip_blacklist (
    ip_address TEXT PRIMARY KEY,
    login_attempts INTEGER NOT NULL,
    blacklisted INTEGER NOT NULL,
    timestamp INTEGER NOT NULL
);

CREATE TABLE domains (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain TEXT NOT NULL UNIQUE,
    ownership_contact TEXT,
    enforcement_level TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_domain ON domains(domain);

CREATE TABLE domain_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE domain_group_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain_id INTEGER NOT NULL,
    group_id INTEGER NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES domain_groups(id) ON DELETE CASCADE,
    UNIQUE(domain_id, group_id)
);

CREATE TABLE dmarc_aggregate_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain_id INTEGER NOT NULL,
    org_name TEXT NOT NULL,
    email TEXT NOT NULL,
    extra_contact_info TEXT,
    report_id TEXT NOT NULL,
    date_range_begin INTEGER NOT NULL,
    date_range_end INTEGER NOT NULL,
    received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    raw_xml TEXT,
    processed INTEGER DEFAULT 0,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

CREATE INDEX idx_report_id ON dmarc_aggregate_reports(report_id);
CREATE INDEX idx_date_range ON dmarc_aggregate_reports(date_range_begin, date_range_end);
CREATE INDEX idx_domain_date ON dmarc_aggregate_reports(domain_id, date_range_begin);

CREATE TABLE dmarc_aggregate_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL,
    source_ip TEXT NOT NULL,
    count INTEGER NOT NULL DEFAULT 1,
    disposition TEXT NOT NULL,
    dkim_result TEXT,
    spf_result TEXT,
    header_from TEXT,
    envelope_from TEXT,
    envelope_to TEXT,
    FOREIGN KEY (report_id) REFERENCES dmarc_aggregate_reports(id) ON DELETE CASCADE
);

CREATE INDEX idx_source_ip ON dmarc_aggregate_records(source_ip);
CREATE INDEX idx_disposition ON dmarc_aggregate_records(disposition);

CREATE TABLE dmarc_forensic_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain_id INTEGER NOT NULL,
    arrival_date INTEGER NOT NULL,
    source_ip TEXT NOT NULL,
    authentication_results TEXT,
    original_envelope_id TEXT,
    dkim_domain TEXT,
    dkim_selector TEXT,
    dkim_result TEXT,
    spf_domain TEXT,
    spf_result TEXT,
    received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    raw_message TEXT,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

CREATE INDEX idx_forensic_source_ip ON dmarc_forensic_reports(source_ip);
CREATE INDEX idx_arrival_date ON dmarc_forensic_reports(arrival_date);

CREATE TABLE password_reset_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    email TEXT NOT NULL,
    selector TEXT NOT NULL,
    token_hash TEXT NOT NULL,
    expires_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    FOREIGN KEY (username) REFERENCES users(username) ON DELETE CASCADE
);

CREATE INDEX idx_password_reset_selector ON password_reset_tokens(selector);
CREATE INDEX idx_password_reset_expiry ON password_reset_tokens(expires_at);

CREATE TABLE smtp_tls_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain_id INTEGER NOT NULL,
    org_name TEXT NOT NULL,
    contact_info TEXT,
    report_id TEXT NOT NULL,
    date_range_begin INTEGER NOT NULL,
    date_range_end INTEGER NOT NULL,
    received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    raw_json TEXT,
    processed INTEGER DEFAULT 0,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

CREATE INDEX idx_tls_report_id ON smtp_tls_reports(report_id);
CREATE INDEX idx_tls_date_range ON smtp_tls_reports(date_range_begin, date_range_end);

CREATE TABLE smtp_tls_policies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tls_report_id INTEGER NOT NULL,
    policy_type TEXT NOT NULL,
    policy_string TEXT,
    policy_domain TEXT,
    mx_host TEXT,
    successful_session_count INTEGER DEFAULT 0,
    failure_session_count INTEGER DEFAULT 0,
    FOREIGN KEY (tls_report_id) REFERENCES smtp_tls_reports(id) ON DELETE CASCADE
);

CREATE TABLE retention_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_name TEXT UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE saved_report_filters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id TEXT NOT NULL,
    name TEXT NOT NULL,
    filters TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, name)
);

CREATE INDEX idx_saved_filters_user ON saved_report_filters(user_id);

CREATE TABLE alert_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    rule_type TEXT NOT NULL,
    metric TEXT NOT NULL,
    threshold_value REAL NOT NULL,
    threshold_operator TEXT NOT NULL,
    time_window INTEGER NOT NULL,
    domain_filter TEXT,
    group_filter INTEGER,
    severity TEXT NOT NULL DEFAULT 'medium',
    enabled INTEGER DEFAULT 1,
    notification_channels TEXT NOT NULL,
    notification_recipients TEXT NOT NULL,
    webhook_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_filter) REFERENCES domain_groups(id) ON DELETE SET NULL
);

CREATE TABLE alert_incidents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rule_id INTEGER NOT NULL,
    triggered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    metric_value REAL NOT NULL,
    threshold_value REAL NOT NULL,
    status TEXT DEFAULT 'open',
    message TEXT NOT NULL,
    details TEXT,
    acknowledged_by TEXT,
    acknowledged_at DATETIME,
    resolved_at DATETIME,
    FOREIGN KEY (rule_id) REFERENCES alert_rules(id) ON DELETE CASCADE
);

CREATE TABLE alert_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    incident_id INTEGER NOT NULL,
    channel TEXT NOT NULL,
    recipient TEXT NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    success INTEGER DEFAULT 1,
    error_message TEXT,
    FOREIGN KEY (incident_id) REFERENCES alert_incidents(id) ON DELETE CASCADE
);

CREATE TABLE webhook_endpoints (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    secret_key TEXT,
    headers TEXT,
    enabled INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE email_digest_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    frequency TEXT NOT NULL,
    recipients TEXT NOT NULL,
    domain_filter TEXT,
    group_filter INTEGER,
    enabled INTEGER DEFAULT 1,
    last_sent DATETIME,
    next_scheduled DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_filter) REFERENCES domain_groups(id) ON DELETE SET NULL
);

CREATE TABLE email_digest_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    schedule_id INTEGER NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    recipients TEXT NOT NULL,
    subject TEXT NOT NULL,
    report_period_start DATETIME NOT NULL,
    report_period_end DATETIME NOT NULL,
    success INTEGER DEFAULT 1,
    error_message TEXT,
    FOREIGN KEY (schedule_id) REFERENCES email_digest_schedules(id) ON DELETE CASCADE
);

CREATE TABLE pdf_report_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    template_type TEXT NOT NULL,
    sections TEXT NOT NULL,
    styling TEXT,
    enabled INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE pdf_report_generations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    title TEXT NOT NULL,
    date_range_start DATE NOT NULL,
    date_range_end DATE NOT NULL,
    domain_filter TEXT,
    group_filter INTEGER,
    parameters TEXT,
    file_path TEXT,
    file_size INTEGER,
    generated_by TEXT,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    download_count INTEGER DEFAULT 0,
    FOREIGN KEY (template_id) REFERENCES pdf_report_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (group_filter) REFERENCES domain_groups(id) ON DELETE SET NULL
);

CREATE TABLE pdf_report_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    template_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    frequency TEXT NOT NULL,
    recipients TEXT NOT NULL,
    domain_filter TEXT,
    group_filter INTEGER,
    parameters TEXT DEFAULT '{}',
    enabled INTEGER DEFAULT 1,
    next_run_at DATETIME,
    last_run_at DATETIME,
    last_status TEXT,
    last_error TEXT,
    created_by TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_generation_id INTEGER,
    FOREIGN KEY (template_id) REFERENCES pdf_report_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (group_filter) REFERENCES domain_groups(id) ON DELETE SET NULL,
    FOREIGN KEY (last_generation_id) REFERENCES pdf_report_generations(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(username) ON DELETE SET NULL
);

CREATE TABLE policy_simulations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    domain_id INTEGER NOT NULL,
    current_policy TEXT NOT NULL,
    simulated_policy TEXT NOT NULL,
    simulation_period_start DATE NOT NULL,
    simulation_period_end DATE NOT NULL,
    results TEXT,
    recommendations TEXT,
    created_by TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

CREATE TABLE user_domain_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id TEXT NOT NULL,
    domain_id INTEGER NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    assigned_by TEXT,
    FOREIGN KEY (user_id) REFERENCES users(username) ON DELETE CASCADE,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(username) ON DELETE SET NULL,
    UNIQUE(user_id, domain_id)
);

CREATE TABLE user_group_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id TEXT NOT NULL,
    group_id INTEGER NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    assigned_by TEXT,
    FOREIGN KEY (user_id) REFERENCES users(username) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES domain_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(username) ON DELETE SET NULL,
    UNIQUE(user_id, group_id)
);

CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id TEXT,
    action TEXT NOT NULL,
    resource_type TEXT,
    resource_id TEXT,
    details JSON,
    ip_address TEXT,
    user_agent TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(username) ON DELETE SET NULL
);

CREATE INDEX idx_user_action ON audit_logs(user_id, action);
CREATE INDEX idx_timestamp ON audit_logs(timestamp);
CREATE INDEX idx_resource ON audit_logs(resource_type, resource_id);

CREATE TABLE app_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key TEXT UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type TEXT DEFAULT 'string',
    description TEXT,
    updated_by TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(username) ON DELETE SET NULL
);

CREATE TABLE ip_intelligence (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address TEXT UNIQUE NOT NULL,
    country_code TEXT,
    country_name TEXT,
    region TEXT,
    city TEXT,
    timezone TEXT,
    isp TEXT,
    organization TEXT,
    asn TEXT,
    asn_org TEXT,
    threat_score INTEGER DEFAULT 0,
    threat_categories JSON,
    is_malicious INTEGER DEFAULT 0,
    is_tor INTEGER DEFAULT 0,
    is_proxy INTEGER DEFAULT 0,
    rdap_registry TEXT,
    rdap_network_range TEXT,
    rdap_network_start TEXT,
    rdap_network_end TEXT,
    rdap_contacts JSON,
    rdap_raw JSON,
    rdap_checked_at DATETIME,
    dnsbl_listed INTEGER DEFAULT 0,
    dnsbl_sources JSON,
    dnsbl_last_checked DATETIME,
    reputation_score INTEGER,
    reputation_context JSON,
    reputation_last_checked DATETIME,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_ip_intelligence_ip ON ip_intelligence(ip_address);
CREATE INDEX idx_ip_intelligence_country ON ip_intelligence(country_code);
CREATE INDEX idx_ip_intelligence_threat ON ip_intelligence(threat_score, is_malicious);
CREATE INDEX idx_ip_intelligence_registry ON ip_intelligence(rdap_registry);
CREATE INDEX idx_ip_intelligence_dnsbl ON ip_intelligence(dnsbl_listed, dnsbl_last_checked);
CREATE INDEX idx_ip_intelligence_reputation ON ip_intelligence(reputation_score);

CREATE TABLE user_sessions (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    ip_address TEXT,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_active INTEGER DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(username) ON DELETE CASCADE
);

CREATE INDEX idx_user_session ON user_sessions(user_id, is_active);
CREATE INDEX idx_session_expires ON user_sessions(expires_at);

INSERT INTO users (username, password, admin, role, is_active)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'app_admin', 1);

INSERT INTO retention_settings (setting_name, setting_value) VALUES
('aggregate_reports_retention_days', '90'),
('forensic_reports_retention_days', '90'),
('tls_reports_retention_days', '90');

INSERT INTO alert_rules (
    name, description, rule_type, metric, threshold_value,
    threshold_operator, time_window, severity, notification_channels,
    notification_recipients
) VALUES
('High DMARC Failure Rate', 'Alert when DMARC failure rate exceeds 10% in 1 hour', 'threshold', 'dmarc_failure_rate', 10.0, '>', 60, 'high', '["email", "webhook"]', '["security@example.com", "admin@example.com"]'),
('Volume Spike Detection', 'Alert when message volume increases by 200% in 30 minutes', 'threshold', 'volume_increase', 200.0, '>', 30, 'medium', '["email"]', '["ops@example.com"]'),
('New Suspicious IPs', 'Alert when more than 5 new IPs with failures appear in 15 minutes', 'threshold', 'new_failure_ips', 5, '>', 15, 'critical', '["email", "webhook"]', '["security@example.com"]'),
('SPF Failure Spike', 'Alert when SPF failures exceed 50 in 1 hour', 'threshold', 'spf_failures', 50, '>', 60, 'medium', '["email"]', '["admin@example.com"]');

INSERT INTO webhook_endpoints (name, url, secret_key) VALUES
('Security Team Slack', 'https://hooks.slack.com/services/example/security', 'sk_security_webhook_123'),
('Operations Discord', 'https://discord.com/api/webhooks/example/ops', 'discord_webhook_456'),
('External SIEM', 'https://siem.company.com/api/alerts', 'siem_api_key_789');

INSERT INTO email_digest_schedules (name, frequency, recipients, domain_filter, enabled) VALUES
('Daily Security Report', 'daily', '["admin@example.com", "security@example.com"]', '', 1),
('Weekly Executive Summary', 'weekly', '["ceo@example.com", "cto@example.com"]', '', 1),
('Monthly Compliance Report', 'monthly', '["compliance@example.com"]', 'example.com', 1);

INSERT INTO pdf_report_templates (name, description, template_type, sections) VALUES
('Executive Summary Report', 'High-level overview for executive leadership', 'executive', '["summary", "domain_health", "top_threats", "compliance_status", "recommendations"]'),
('Technical Analysis Report', 'Detailed technical analysis for IT teams', 'technical', '["summary", "detailed_analytics", "ip_analysis", "authentication_breakdown", "forensic_reports", "policy_analysis"]'),
('Compliance Report', 'Regulatory compliance focused report', 'compliance', '["summary", "compliance_metrics", "policy_adherence", "audit_trail", "recommendations"]'),
('Domain Health Report', 'Domain-specific health and performance report', 'custom', '["domain_overview", "volume_trends", "authentication_rates", "threat_analysis", "policy_effectiveness"]');

INSERT INTO app_settings (setting_key, setting_value, setting_type, description) VALUES
('app_name', 'DMARC Dashboard', 'string', 'Application name displayed in header and title'),
('app_logo_url', '', 'string', 'URL or path to custom logo image'),
('primary_color', '#5755d9', 'string', 'Primary brand color (CSS hex color)'),
('secondary_color', '#f1f3f4', 'string', 'Secondary brand color'),
('company_name', '', 'string', 'Company name for branding'),
('footer_text', '', 'string', 'Custom footer text'),
('theme_mode', 'light', 'string', 'Theme mode: light or dark'),
('enable_custom_css', '0', 'boolean', 'Allow custom CSS injection'),
('custom_css', '', 'text', 'Custom CSS styles');
