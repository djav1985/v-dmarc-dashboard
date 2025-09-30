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

## Access Control and Data Scoping

Role-based permissions are enforced at the controller entry points through `App\Core\RBACManager::requirePermission()`. Upload, IMAP ingestion, alert administration, domain group management, analytics, report browsing, and the reports management tools now check for their respective capabilities (`upload_reports`, `manage_alerts`, `manage_groups`, `view_analytics`, `view_reports`, etc.) before executing any request or submission logic. Users who lack the required permission receive an HTTP 403 response that explains which capability is missing.

Domain and report data returned to the UI is also filtered through RBAC-aware helpers. The `Domain`, `DomainGroup`, and `DmarcReport` models rely on `RBACManager::getAccessibleDomains()` / `getAccessibleGroups()` (and related access checks) so scoped administrators only see the domains and groups assigned to them. Queries that back dropdowns, analytics summaries, or report detail pages automatically exclude unassigned entities, preventing limited-scope accounts from accessing or enumerating unrelated data.

## Testing

Execute the automated test suite from the repository root:

```bash
./vendor/bin/phpunit --configuration phpunit.xml.dist
```

The `unit/RBACPermissionTest.php` script verifies that permission checks deny unauthorised requests and that scoped users cannot retrieve domains, groups, or DMARC reports outside their assignment lists.
