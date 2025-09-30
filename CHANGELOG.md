# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]
### Added
- Introduced a repository root layout with isolated `root/`, `docker/`, and `unit/` directories.
- Added development tooling configuration for PHP_CodeSniffer, ESLint, and Stylelint at the repository root.
- Created an automated PR lint-and-fix workflow.
- Documented the database installer workflow in the README.
- Added regression tests covering RBAC permission enforcement and scoped domain/report access.
- Added RDAP ownership metadata, Spamhaus DNSBL insights, and SANS ISC reputation scoring to the `ip_intelligence` cache with a forward migration script.
- Implemented a reusable DNS-over-HTTPS client and accompanying tests to back DMARC/SPF/DKIM validation and Spamhaus lookups.
- Implemented Spectre-based alert rule management, incident tracking, and rule creation views with CSRF-aware forms.
- Added reusable HTML email templates for alert incidents, digest schedules, and ad-hoc report sends.
- Built a digest scheduling controller/UI with supporting tests covering automated dispatch and mailer mocking.
- Added database compatibility regression tests covering digest timestamps, analytics bucketing, transactional deletion, and IMAP attachment cleanup.
- Added unit coverage confirming PDF analytics respect domain-group filters across all sections.

### Changed
- Updated documentation to explain the dual Composer environments and new directory structure.
- Updated the installer to read database credentials from configuration constants and validate missing values before running.
- Updated the dashboard view to read the username from the session with HTML escaping and a neutral fallback label.
- Enforced RBAC permission checks on privileged controllers and filtered domain/group/report queries to the current user's accessible assignments.
- Enhanced the report detail view and GeoIP service to surface RDAP contacts, DNSBL status, and reputation signals for each source IP.
- Updated alert processing to reuse a dedicated service, expanded cron automation with alert and digest jobs, and enabled email delivery through templated mailer calls.
- Stopped tracking the generated SQLite demo database in version control and documented how to recreate it from the consolidated installer.
- Updated digest schedule persistence, analytics date expressions, and user deletion handling for cross-database compatibility and safer transaction rollbacks.
- Ensured IMAP attachment processing always cleans up temporary files and logs the failing path for diagnostics.
- Standardized alert metrics, incident acknowledgement, and GeoIP cache cleanup to bind ISO timestamps for cross-database compatibility and added regression coverage for non-SQLite drivers.
- Updated analytics summary, trend, compliance, health, and threat helpers plus PDF report generation to honour optional domain-group filters.

### Fixed
- Hardened DMARC ingestion to parse forensic single-part payloads and detect gzip/ZIP attachments by signature so reports without filename extensions are still processed.
- Corrected alert metric scheduling to respect the configured application timezone when calculating window boundaries.
