-- PDF report system tables for Feature 8
CREATE TABLE pdf_report_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    template_type TEXT NOT NULL, -- 'executive', 'technical', 'compliance', 'custom'
    sections TEXT NOT NULL, -- JSON array of sections to include
    styling TEXT, -- JSON object for custom styling
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
    parameters TEXT, -- JSON object with generation parameters
    file_size INTEGER,
    generated_by TEXT,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    download_count INTEGER DEFAULT 0,
    FOREIGN KEY (template_id) REFERENCES pdf_report_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (group_filter) REFERENCES domain_groups(id) ON DELETE SET NULL
);

CREATE TABLE policy_simulations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    domain_id INTEGER NOT NULL,
    current_policy TEXT NOT NULL, -- JSON object with current DMARC policy
    simulated_policy TEXT NOT NULL, -- JSON object with proposed policy
    simulation_period_start DATE NOT NULL,
    simulation_period_end DATE NOT NULL,
    results TEXT, -- JSON object with simulation results
    recommendations TEXT, -- JSON array of recommendations
    created_by TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

-- Insert sample PDF report templates
INSERT INTO pdf_report_templates (name, description, template_type, sections) VALUES
('Executive Summary Report', 'High-level overview for executive leadership', 'executive', 
 '["summary", "domain_health", "top_threats", "compliance_status", "recommendations"]'),
('Technical Analysis Report', 'Detailed technical analysis for IT teams', 'technical', 
 '["summary", "detailed_analytics", "ip_analysis", "authentication_breakdown", "forensic_reports", "policy_analysis"]'),
('Compliance Report', 'Regulatory compliance focused report', 'compliance', 
 '["summary", "compliance_metrics", "policy_adherence", "audit_trail", "recommendations"]'),
('Domain Health Report', 'Domain-specific health and performance report', 'custom', 
 '["domain_overview", "volume_trends", "authentication_rates", "threat_analysis", "policy_effectiveness"]');

-- Insert sample policy simulations
INSERT INTO policy_simulations (name, description, domain_id, current_policy, simulated_policy, simulation_period_start, simulation_period_end, results, recommendations) VALUES
('example.com Policy Hardening', 'Simulate effect of changing policy from p=none to p=quarantine', 1,
 '{"p": "none", "rua": "mailto:dmarc@example.com", "ruf": "mailto:dmarc@example.com", "sp": "none"}',
 '{"p": "quarantine", "rua": "mailto:dmarc@example.com", "ruf": "mailto:dmarc@example.com", "sp": "quarantine", "pct": "10"}',
 '2023-09-01', '2023-09-30',
 '{"would_quarantine": 156, "would_reject": 0, "legitimate_affected": 12, "spam_blocked": 144, "effectiveness": 92.3}',
 '["Start with pct=10 to minimize impact", "Monitor legitimate mail carefully", "Gradually increase to pct=100", "Consider p=reject after 90 days"]'),
('testdomain.org SPF Alignment', 'Test impact of strict SPF alignment', 2,
 '{"p": "quarantine", "aspf": "r", "adkim": "r"}',
 '{"p": "quarantine", "aspf": "s", "adkim": "r"}',
 '2023-09-01', '2023-09-30',
 '{"spf_failures_increase": 23, "legitimate_affected": 8, "security_improvement": 15.2}',
 '["Review SPF record completeness", "Update third-party services SPF", "Implement gradual rollout"]');