#!/usr/bin/env bash
# Script to set up a demo SQLite database for the DMARC Dashboard

cd "$(dirname "$0")"

# Remove existing demo database if it exists
if [ -f "demo.db" ]; then
    echo "Removing existing demo database..."
    rm demo.db
fi

# Create the database using SQLite
echo "Creating SQLite database..."
sqlite3 demo.db < install.sql

# Add some sample data for demonstration
echo "Adding sample data..."
sqlite3 demo.db << 'EOF'
-- Insert sample domains
INSERT INTO domains (domain) VALUES 
('example.com'),
('testdomain.org'),
('mydomain.net');

-- Insert sample DMARC aggregate reports
INSERT INTO dmarc_aggregate_reports 
(domain_id, org_name, email, report_id, date_range_begin, date_range_end, received_at) VALUES
(1, 'Google Inc.', 'noreply-dmarc-support@google.com', 'google.com!example.com!1696032000!1696118399', 1696032000, 1696118399, '2025-09-28 10:00:00'),
(1, 'Microsoft Corporation', 'dmarc-noreply@microsoft.com', 'outlook.com!example.com!1696032000!1696118399', 1696032000, 1696118399, '2025-09-28 11:30:00'),
(2, 'Yahoo Inc.', 'dmarc_reports@yahoo-inc.com', 'yahoo.com!testdomain.org!1695945600!1696031999', 1695945600, 1696031999, '2025-09-27 09:15:00'),
(3, 'Cloudflare', 'dmarc@cloudflare.com', 'cloudflare.com!mydomain.net!1696118400!1696204799', 1696118400, 1696204799, '2025-09-29 14:45:00');

-- Insert sample DMARC aggregate records
INSERT INTO dmarc_aggregate_records 
(report_id, source_ip, count, disposition, dkim_result, spf_result, header_from, envelope_from) VALUES
-- Google report for example.com
(1, '209.85.220.41', 1150, 'none', 'pass', 'pass', 'example.com', 'example.com'),
(1, '74.125.200.26', 84, 'quarantine', 'fail', 'pass', 'example.com', 'spammer.com'),
(1, '172.217.164.69', 12, 'reject', 'fail', 'fail', 'example.com', 'malicious.com'),

-- Microsoft report for example.com  
(2, '40.107.7.123', 567, 'none', 'pass', 'pass', 'example.com', 'example.com'),
(2, '52.96.0.0', 23, 'quarantine', 'fail', 'pass', 'example.com', 'suspicious.net'),
(2, '13.107.42.14', 5, 'reject', 'fail', 'fail', 'example.com', 'phishing.org'),

-- Yahoo report for testdomain.org
(3, '98.138.219.231', 890, 'none', 'pass', 'pass', 'testdomain.org', 'testdomain.org'),
(3, '66.196.118.37', 67, 'quarantine', 'fail', 'pass', 'testdomain.org', 'fake-sender.com'),
(3, '74.6.143.25', 18, 'reject', 'fail', 'fail', 'testdomain.org', 'evil.example'),

-- Cloudflare report for mydomain.net
(4, '173.245.48.1', 1234, 'none', 'pass', 'pass', 'mydomain.net', 'mydomain.net'),
(4, '104.16.132.229', 45, 'quarantine', 'fail', 'pass', 'mydomain.net', 'spoof.test'),
(4, '172.67.74.226', 12, 'reject', 'fail', 'fail', 'mydomain.net', 'malware.site');

EOF

echo "Demo database created successfully at root/demo.db"
echo "You can now start the application and test the reports functionality."
