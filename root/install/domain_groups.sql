-- Add domain groups table to SQLite schema
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

-- Insert some sample domain groups
INSERT INTO domain_groups (name, description) VALUES
('Corporate', 'Main corporate email domains'),
('Marketing', 'Marketing and promotional domains'),
('Development', 'Development and testing domains');

-- Assign domains to groups
INSERT INTO domain_group_assignments (domain_id, group_id) VALUES
(1, 1), -- example.com -> Corporate
(2, 3), -- testdomain.org -> Development  
(3, 2); -- mydomain.net -> Marketing