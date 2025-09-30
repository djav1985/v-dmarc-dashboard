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

### Changed
- Updated documentation to explain the dual Composer environments and new directory structure.
- Updated the installer to read database credentials from configuration constants and validate missing values before running.
- Updated the dashboard view to read the username from the session with HTML escaping and a neutral fallback label.
- Enforced RBAC permission checks on privileged controllers and filtered domain/group/report queries to the current user's accessible assignments.
- Enhanced the report detail view and GeoIP service to surface RDAP contacts, DNSBL status, and reputation signals for each source IP.
