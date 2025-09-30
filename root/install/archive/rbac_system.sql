-- Role-Based Access Control (RBAC) System for DMARC Dashboard
-- This extends the existing users table and adds comprehensive permission management

-- Add role column to existing users table
ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'viewer';
ALTER TABLE users ADD COLUMN first_name VARCHAR(100);
ALTER TABLE users ADD COLUMN last_name VARCHAR(100);
ALTER TABLE users ADD COLUMN email VARCHAR(255);
ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT TRUE;
ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Roles: 'app_admin', 'domain_admin', 'group_admin', 'viewer'
UPDATE users SET role = 'app_admin' WHERE admin = TRUE;

-- User domain/group assignments for access control
CREATE TABLE user_domain_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    domain_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(username) ON DELETE CASCADE,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(username) ON DELETE SET NULL,
    UNIQUE(user_id, domain_id)
);

CREATE TABLE user_group_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    group_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(username) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES domain_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(username) ON DELETE SET NULL,
    UNIQUE(user_id, group_id)
);

-- Audit logging system
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(255),
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50),
    resource_id VARCHAR(100),
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(username) ON DELETE SET NULL,
    INDEX idx_user_action (user_id, action),
    INDEX idx_timestamp (timestamp),
    INDEX idx_resource (resource_type, resource_id)
);

-- Branding and theme configuration
CREATE TABLE app_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'string',
    description TEXT,
    updated_by VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) UNIQUE NOT NULL,
    country_code VARCHAR(2),
    country_name VARCHAR(100),
    region VARCHAR(100),
    city VARCHAR(100),
    timezone VARCHAR(50),
    isp VARCHAR(255),
    organization VARCHAR(255),
    asn VARCHAR(20),
    asn_org VARCHAR(255),
    threat_score INT DEFAULT 0,
    threat_categories JSON,
    is_malicious BOOLEAN DEFAULT FALSE,
    is_tor BOOLEAN DEFAULT FALSE,
    is_proxy BOOLEAN DEFAULT FALSE,
    rdap_registry VARCHAR(32),
    rdap_network_range VARCHAR(100),
    rdap_network_start VARCHAR(45),
    rdap_network_end VARCHAR(45),
    rdap_contacts JSON,
    rdap_raw JSON,
    rdap_checked_at TIMESTAMP NULL,
    dnsbl_listed BOOLEAN DEFAULT FALSE,
    dnsbl_sources JSON,
    dnsbl_last_checked TIMESTAMP NULL,
    reputation_score INT,
    reputation_context JSON,
    reputation_last_checked TIMESTAMP NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address),
    INDEX idx_country (country_code),
    INDEX idx_threat (threat_score, is_malicious),
    INDEX idx_rdap_registry (rdap_registry),
    INDEX idx_dnsbl_status (dnsbl_listed, dnsbl_last_checked),
    INDEX idx_reputation_score (reputation_score)
);

-- Session management for enhanced security
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(username) ON DELETE CASCADE,
    INDEX idx_user_session (user_id, is_active),
    INDEX idx_expires (expires_at)
);
