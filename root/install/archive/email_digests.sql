-- Email digest system tables
CREATE TABLE email_digest_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    frequency TEXT NOT NULL, -- 'daily', 'weekly', 'monthly', 'custom'
    recipients TEXT NOT NULL, -- JSON array of email addresses
    domain_filter TEXT, -- Domain filter (empty for all)
    group_filter INTEGER, -- Domain group filter (NULL for all)
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

-- Insert sample digest schedules
INSERT INTO email_digest_schedules (name, frequency, recipients, domain_filter, enabled) VALUES
('Daily Security Report', 'daily', '["admin@example.com", "security@example.com"]', '', 1),
('Weekly Executive Summary', 'weekly', '["ceo@example.com", "cto@example.com"]', '', 1),
('Monthly Compliance Report', 'monthly', '["compliance@example.com"]', 'example.com', 1);