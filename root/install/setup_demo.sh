#!/bin/bash

# Quick demo database setup script for DMARC Dashboard
echo "Setting up demo database..."

# Create SQLite database for demo
sqlite3 /tmp/dmarc_demo.db <<EOF
CREATE TABLE users (
    username VARCHAR(255) PRIMARY KEY,
    password VARCHAR(255) NOT NULL,
    admin BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

CREATE TABLE domains (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE dmarc_aggregate_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain_id INTEGER NOT NULL,
    org_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    report_id VARCHAR(255) NOT NULL,
    date_range_begin INTEGER NOT NULL,
    date_range_end INTEGER NOT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    raw_xml TEXT,
    processed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

CREATE TABLE dmarc_aggregate_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL,
    source_ip VARCHAR(45) NOT NULL,
    count INTEGER NOT NULL DEFAULT 1,
    disposition VARCHAR(20) NOT NULL,
    dkim_result VARCHAR(20),
    spf_result VARCHAR(20),
    header_from VARCHAR(255),
    FOREIGN KEY (report_id) REFERENCES dmarc_aggregate_reports(id) ON DELETE CASCADE
);

-- Insert demo admin user (password: admin)
INSERT INTO users (username, password, admin) VALUES
('admin', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Insert demo data
INSERT INTO domains (domain) VALUES ('example.com'), ('testdomain.org');

INSERT INTO dmarc_aggregate_reports (domain_id, org_name, email, report_id, date_range_begin, date_range_end) VALUES
(1, 'Google Inc.', 'noreply-dmarc-support@google.com', 'google.com!example.com!1609459200!1609545600', 1609459200, 1609545600),
(1, 'Yahoo! Inc.', 'dmarc@yahoo.com', 'yahoo.com!example.com!1609459200!1609545600', 1609459200, 1609545600),
(2, 'Microsoft Corporation', 'dmarc-report@microsoft.com', 'outlook.com!testdomain.org!1609459200!1609545600', 1609459200, 1609545600);

INSERT INTO dmarc_aggregate_records (report_id, source_ip, count, disposition, dkim_result, spf_result, header_from) VALUES
(1, '192.168.1.100', 5, 'none', 'pass', 'pass', 'example.com'),
(1, '10.0.0.1', 2, 'quarantine', 'fail', 'pass', 'example.com'),
(2, '203.0.113.1', 8, 'none', 'pass', 'pass', 'example.com'),
(2, '198.51.100.1', 1, 'reject', 'fail', 'fail', 'spoof.example.com'),
(3, '172.16.0.1', 12, 'none', 'pass', 'pass', 'testdomain.org');
EOF

echo "Demo database created at /tmp/dmarc_demo.db"
echo "You can now test the dashboard with:"
echo "- Username: admin"
echo "- Password: admin"