-- Role-Based Access Control (RBAC) System for DMARC Dashboard - SQLite Version

-- Add role columns to existing users table
ALTER TABLE users ADD COLUMN role TEXT;
ALTER TABLE users ADD COLUMN first_name TEXT;
ALTER TABLE users ADD COLUMN last_name TEXT;
ALTER TABLE users ADD COLUMN email TEXT;
ALTER TABLE users ADD COLUMN is_active INTEGER;
ALTER TABLE users ADD COLUMN updated_at DATETIME;

-- Update existing admin user and set defaults
UPDATE users SET role = 'app_admin', is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE admin = 1;
UPDATE users SET role = 'viewer' WHERE role IS NULL;
UPDATE users SET is_active = 1 WHERE is_active IS NULL;

-- User domain/group assignments for access control
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

-- Audit logging system
CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id TEXT,
    action TEXT NOT NULL,
    resource_type TEXT,
    resource_id TEXT,
    details TEXT, -- JSON stored as text in SQLite
    ip_address TEXT,
    user_agent TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(username) ON DELETE SET NULL
);

CREATE INDEX idx_audit_user_action ON audit_logs(user_id, action);
CREATE INDEX idx_audit_timestamp ON audit_logs(timestamp);
CREATE INDEX idx_audit_resource ON audit_logs(resource_type, resource_id);

-- Branding and theme configuration
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

-- Insert default branding settings
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

-- GeoIP and threat intelligence cache
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
    threat_categories TEXT, -- JSON stored as text
    is_malicious INTEGER DEFAULT 0,
    is_tor INTEGER DEFAULT 0,
    is_proxy INTEGER DEFAULT 0,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_ip_intelligence_ip ON ip_intelligence(ip_address);
CREATE INDEX idx_ip_intelligence_country ON ip_intelligence(country_code);
CREATE INDEX idx_ip_intelligence_threat ON ip_intelligence(threat_score, is_malicious);

-- Session management for enhanced security
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

CREATE INDEX idx_user_sessions_user ON user_sessions(user_id, is_active);
CREATE INDEX idx_user_sessions_expires ON user_sessions(expires_at);