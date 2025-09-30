-- Alerting system tables for Feature 7
CREATE TABLE alert_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    rule_type TEXT NOT NULL, -- 'threshold', 'anomaly', 'failure_rate', 'volume_spike'
    metric TEXT NOT NULL, -- 'dmarc_failures', 'spf_failures', 'dkim_failures', 'volume', 'new_ips'
    threshold_value REAL NOT NULL,
    threshold_operator TEXT NOT NULL, -- '>', '<', '>=', '<=', '=='
    time_window INTEGER NOT NULL, -- minutes
    domain_filter TEXT, -- specific domain (empty for all)
    group_filter INTEGER, -- domain group filter
    severity TEXT NOT NULL DEFAULT 'medium', -- 'low', 'medium', 'high', 'critical'
    enabled INTEGER DEFAULT 1,
    notification_channels TEXT NOT NULL, -- JSON array: ["email", "webhook", "slack"]
    notification_recipients TEXT NOT NULL, -- JSON array of recipients
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
    status TEXT DEFAULT 'open', -- 'open', 'acknowledged', 'resolved'
    message TEXT NOT NULL,
    details TEXT, -- JSON with additional context
    acknowledged_by TEXT,
    acknowledged_at DATETIME,
    resolved_at DATETIME,
    FOREIGN KEY (rule_id) REFERENCES alert_rules(id) ON DELETE CASCADE
);

CREATE TABLE alert_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    incident_id INTEGER NOT NULL,
    channel TEXT NOT NULL, -- 'email', 'webhook', 'slack'
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
    headers TEXT, -- JSON object for custom headers
    enabled INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample alert rules
INSERT INTO alert_rules (name, description, rule_type, metric, threshold_value, threshold_operator, time_window, severity, notification_channels, notification_recipients) VALUES
('High DMARC Failure Rate', 'Alert when DMARC failure rate exceeds 10% in 1 hour', 'threshold', 'dmarc_failure_rate', 10.0, '>', 60, 'high', '["email", "webhook"]', '["security@example.com", "admin@example.com"]'),
('Volume Spike Detection', 'Alert when message volume increases by 200% in 30 minutes', 'threshold', 'volume_increase', 200.0, '>', 30, 'medium', '["email"]', '["ops@example.com"]'),
('New Suspicious IPs', 'Alert when more than 5 new IPs with failures appear in 15 minutes', 'threshold', 'new_failure_ips', 5, '>', 15, 'critical', '["email", "webhook"]', '["security@example.com"]'),
('SPF Failure Spike', 'Alert when SPF failures exceed 50 in 1 hour', 'threshold', 'spf_failures', 50, '>', 60, 'medium', '["email"]', '["admin@example.com"]');

-- Insert sample webhook endpoints
INSERT INTO webhook_endpoints (name, url, secret_key) VALUES
('Security Team Slack', 'https://hooks.slack.com/services/example/security', 'sk_security_webhook_123'),
('Operations Discord', 'https://discord.com/api/webhooks/example/ops', 'discord_webhook_456'),
('External SIEM', 'https://siem.company.com/api/alerts', 'siem_api_key_789');