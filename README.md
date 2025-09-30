# v-dmarc-dashboard (WIP)

A minimal starter framework for building small web applications. All application source lives within the `root/` directory.

## Installation

Install the production dependencies from inside `root/`:

```bash
composer install --no-dev
```

During development you can still run the tooling-defined Composer install from the repository root (see the top-level `README.md`).

### Database Installation

After configuring the database constants in `root/config.php`, run the installer to create the required tables:

```bash
php root/install/install.php
```

The script will stop with a helpful message if any required credentials are missing, ensuring the connection details are correct before attempting to load `install/install.sql` into your database.

For demo purposes you can populate the bundled SQLite configuration by generating a fresh database from the consolidated schema and seed data:

```bash
bash root/setup_demo_db.sh
```

The script recreates `root/demo.db` on demand so the repository no longer needs to ship the binary database itself.

### Upgrading Existing Databases

Existing deployments should compare their database schema with the consolidated `root/install/install.sql` and apply any missing DDL statements (for example, the IP intelligence metadata columns) to stay aligned with the authoritative definition.

## Running the Application

Serve `public/index.php` from inside the `root/` directory with your preferred web server or via PHP's built-in server:

```bash
cd root
php -S localhost:8000 -t public
```

## Dashboard Greeting

The dashboard view greets the active user by reading `$_SESSION['username']`. The value is automatically escaped for HTML
output, and the template falls back to a neutral "User" label when the session key is missing or empty. Ensure your
authentication flow populates the session before rendering the dashboard so the greeting displays the expected name.

## Defining Routes

Routes are registered in `app/Core/Router.php`. The default setup includes examples:

```php
$r->addRoute('GET', '/', [HomeController::class, 'handleRequest']);
$r->addRoute('POST', '/home', [HomeController::class, 'handleSubmission']);
$r->addRoute('GET', '/login', [LoginController::class, 'handleRequest']);
```

Modify this file to add your own paths and controllers.

## Scheduled Tasks

`cron.php` is a simple command line runner for daily or hourly jobs. Execute it from the `root/` directory:

```bash
cd root
php cron.php daily
php cron.php hourly
```

Add your custom logic to the switch statement inside `cron.php`.

## Timezone Configuration

The application honours PHP's default timezone when binding timestamps for alert
queries, digest schedules, and other scheduled tasks. Configure
`date.timezone` in `php.ini` (or call `date_default_timezone_set()` during
bootstrap) to match the timezone used by your database. When the database stores
local timestamps—such as the `received_at` column for DMARC reports—ensuring the
runtime timezone matches avoids mismatches in alert windows and notification
scheduling.

## DMARC Report Ingestion

The IMAP ingestion service automatically recognises DMARC attachments by their binary signature, so gzip or ZIP payloads no longer require a meaningful filename or extension. Forensic feedback that arrives as a single-part message is parsed directly from the XML body, while empty bodies are skipped with a warning to aid troubleshooting. When operating your mailbox pipeline you can safely stream attachments to temporary files (for example via `tempnam()`), and the ingestion job will still decode and store the aggregate records.

## Access Control and Data Scoping

Role-based permissions are enforced at the controller entry points through `App\Core\RBACManager::requirePermission()`. Upload, IMAP ingestion, alert administration, domain group management, analytics, report browsing, and the reports management tools now check for their respective capabilities (`upload_reports`, `manage_alerts`, `manage_groups`, `view_analytics`, `view_reports`, etc.) before executing any request or submission logic. Users who lack the required permission receive an HTTP 403 response that explains which capability is missing.

Domain and report data returned to the UI is also filtered through RBAC-aware helpers. The `Domain`, `DomainGroup`, and `DmarcReport` models rely on `RBACManager::getAccessibleDomains()` / `getAccessibleGroups()` (and related access checks) so scoped administrators only see the domains and groups assigned to them. Queries that back dropdowns, analytics summaries, or report detail pages automatically exclude unassigned entities, preventing limited-scope accounts from accessing or enumerating unrelated data.

## Group-Scoped Analytics and PDF Reports

Analytics helpers now accept an optional domain group identifier so dashboards and PDF exports can be constrained to a curated set of domains. Pass the group ID to `Analytics::getSummaryStatistics()`, `Analytics::getTrendData()`, `Analytics::getComplianceData()`, `Analytics::getDomainHealthScores()`, and `Analytics::getTopThreats()` to automatically join `domain_group_assignments` and limit the result set. When building scheduled or ad-hoc PDFs, call `PdfReport::generateReportData()` with the same group ID—the summary, compliance, recommendations, and authentication breakdown sections all honour the filter so cross-tenant data never leaks into a scoped report.

## IP Ownership and Reputation Insights

The report detail view now surfaces rich ownership metadata alongside reputation indicators for every observed source IP. `GeoIPService` performs keyless RDAP lookups via the IANA registry, enriches ownership records with RIR contact details, and checks Spamhaus’ ZEN DNSBL using DNS-over-HTTPS queries (Google as primary, Cloudflare as fallback). Open threat intelligence from the SANS ISC JSON feed is merged into the cache to provide a lightweight reputation score and summary context.

All DNS lookups inside the application, including DMARC/SPF/DKIM validation, are routed through the reusable `App\Utilities\DnsOverHttpsClient` helper. Responses are cached in-memory by TTL to reduce outbound queries, and the validator still falls back to `dns_get_record()` when the DoH providers are unreachable. Developers can inject mock DoH clients in tests through `DnsValidator::setDnsClient()` and `GeoIPService::createWithDependencies()` for deterministic coverage.

## Testing

Execute the automated test suite from the repository root:

```bash
./vendor/bin/phpunit --configuration phpunit.xml.dist
```

The `unit/RBACPermissionTest.php` script verifies that permission checks deny unauthorised requests and that scoped users cannot retrieve domains, groups, or DMARC reports outside their assignment lists.
