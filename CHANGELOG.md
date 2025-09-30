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
- Added Dompdf-backed PDF rendering with persistent storage, schedule management UI, notification templates, and end-to-end scheduler tests.
- Added domain ownership/enforcement metadata with accessor methods and a forward migration (`202407010001_add_domain_metadata.php`).
- Introduced saved report filters with dedicated persistence, controller routes, UI affordances, and regression coverage.
- Added CSV and XLSX report exports powered by the new `ReportExport` utility and accompanying unit tests.
- Added a retention settings controller/view guarded by the new `manage_retention` permission, plus tests for the update workflow.

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
- Extended the hourly cron job to execute scheduled PDF reports and log delivery outcomes alongside existing digest processing.
- Redesigned the reports dashboard with advanced filtering facets, saved-filter management, and export actions.
- Expanded `DmarcReport::getFilteredReports()` / `getFilteredReportsCount()` to honour ownership, policy, IP, and volume filters used by the new UI.

### Fixed
- Removed the leading whitespace from the public entry point so sessions can start and error responses can set headers without runtime warnings.
- Hardened DMARC ingestion to parse forensic single-part payloads and detect gzip/ZIP attachments by signature so reports without filename extensions are still processed.
- Corrected alert metric scheduling to respect the configured application timezone when calculating window boundaries.
- Fixed email digest aggregation to prevent duplicated volumes when domains belong to multiple groups.
- Resolved digest summaries, domain breakdowns, and threat rollups to skip domain-group joins unless filtering so multi-group domains do not inflate totals.
- Unified UPSERT handling through the DatabaseManager helper so blacklist bans and retention updates run on both SQLite and MySQL, with dedicated regression coverage.
- Ensured domain-group analytics retains empty-traffic groups and reports zeroed metrics alongside valid aggregations.
- Corrected domain health analytics and PDF exports to honour explicit domain filters alongside existing group scoping.
