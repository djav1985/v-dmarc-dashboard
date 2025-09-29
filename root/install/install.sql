CREATE TABLE users (
    username VARCHAR(255) PRIMARY KEY,
    password VARCHAR(255) NOT NULL,
    admin BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

CREATE TABLE ip_blacklist (
    ip_address VARCHAR(45) PRIMARY KEY,
    login_attempts INT NOT NULL,
    blacklisted BOOLEAN NOT NULL,
    timestamp INT NOT NULL
);

-- DMARC Dashboard specific tables
CREATE TABLE domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_domain (domain)
);

CREATE TABLE dmarc_aggregate_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    org_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    extra_contact_info TEXT,
    report_id VARCHAR(255) NOT NULL,
    date_range_begin INT NOT NULL,
    date_range_end INT NOT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    raw_xml LONGTEXT,
    processed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    INDEX idx_report_id (report_id),
    INDEX idx_date_range (date_range_begin, date_range_end),
    INDEX idx_domain_date (domain_id, date_range_begin)
);

CREATE TABLE dmarc_aggregate_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    source_ip VARCHAR(45) NOT NULL,
    count INT NOT NULL DEFAULT 1,
    disposition VARCHAR(20) NOT NULL,
    dkim_result VARCHAR(20),
    spf_result VARCHAR(20),
    header_from VARCHAR(255),
    envelope_from VARCHAR(255),
    envelope_to VARCHAR(255),
    FOREIGN KEY (report_id) REFERENCES dmarc_aggregate_reports(id) ON DELETE CASCADE,
    INDEX idx_source_ip (source_ip),
    INDEX idx_disposition (disposition)
);

CREATE TABLE dmarc_forensic_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    arrival_date INT NOT NULL,
    source_ip VARCHAR(45) NOT NULL,
    authentication_results TEXT,
    original_envelope_id VARCHAR(255),
    dkim_domain VARCHAR(255),
    dkim_selector VARCHAR(255),
    dkim_result VARCHAR(20),
    spf_domain VARCHAR(255),
    spf_result VARCHAR(20),
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    raw_message LONGTEXT,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    INDEX idx_source_ip (source_ip),
    INDEX idx_arrival_date (arrival_date)
);

CREATE TABLE smtp_tls_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    org_name VARCHAR(255) NOT NULL,
    contact_info VARCHAR(255),
    report_id VARCHAR(255) NOT NULL,
    date_range_begin INT NOT NULL,
    date_range_end INT NOT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    raw_json LONGTEXT,
    processed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    INDEX idx_report_id (report_id),
    INDEX idx_date_range (date_range_begin, date_range_end)
);

CREATE TABLE smtp_tls_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tls_report_id INT NOT NULL,
    policy_type VARCHAR(20) NOT NULL,
    policy_string TEXT,
    policy_domain VARCHAR(255),
    mx_host VARCHAR(255),
    successful_session_count INT DEFAULT 0,
    failure_session_count INT DEFAULT 0,
    FOREIGN KEY (tls_report_id) REFERENCES smtp_tls_reports(id) ON DELETE CASCADE
);

-- Data retention settings
CREATE TABLE retention_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(100) UNIQUE NOT NULL,
    setting_value VARCHAR(500) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default retention settings (keep reports for 90 days)
INSERT INTO retention_settings (setting_name, setting_value) VALUES
('aggregate_reports_retention_days', '90'),
('forensic_reports_retention_days', '90'),
('tls_reports_retention_days', '90');

-- Insert default admin user (password: admin)
INSERT INTO users (username, password, admin) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', TRUE);
