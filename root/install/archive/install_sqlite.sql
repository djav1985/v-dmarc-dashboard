-- SQLite version of the DMARC Dashboard schema
-- Converting from MySQL to SQLite syntax

CREATE TABLE users (
    username TEXT PRIMARY KEY,
    password TEXT NOT NULL,
    admin INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL
);

CREATE TABLE ip_blacklist (
    ip_address TEXT PRIMARY KEY,
    login_attempts INTEGER NOT NULL,
    blacklisted INTEGER NOT NULL,
    timestamp INTEGER NOT NULL
);

-- DMARC Dashboard specific tables
CREATE TABLE domains (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain TEXT UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_domain ON domains(domain);

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

-- Data retention settings
CREATE TABLE retention_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_name TEXT UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default retention settings (keep reports for 90 days)
INSERT INTO retention_settings (setting_name, setting_value) VALUES
('aggregate_reports_retention_days', '90'),
('forensic_reports_retention_days', '90'),
('tls_reports_retention_days', '90');

-- Insert default admin user (password: admin)
INSERT INTO users (username, password, admin) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);