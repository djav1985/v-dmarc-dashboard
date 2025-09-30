# Copilot Coding Agent Instructions for V DMARC Dashboard

## Architecture Overview
- **MVC-like structure**: All main code is under `root/`. Controllers (`app/Controllers/`), Models (`app/Models/`), Views (`app/Views/`), and Core logic (`app/Core/`).
- **Routing**: Managed by `app/Core/Router.php` (FastRoute). Register routes in the constructor.
- **Database**: Use `app/Core/DatabaseManager.php` (Doctrine DBAL, singleton pattern).
- **Config**: Centralized in `root/config.php` (constants for DB, SMTP, session, etc.).
- **Entry point**: `root/public/index.php` (session, error handling, request dispatch).
- **Error Handling**: Use `app/Core/ErrorManager.php` for all error management.
- **Session**: Managed via `SessionManager` singleton.
- **UI**: SPECTRE.CSS is required for all UI components. Do not override or replace.

## Developer Workflows
- **Install dependencies**:
  - PHP: `composer install` in both repo root and `root/`
  - Node: `npm install` in repo root (for linting)
- **Run app**: `cd root && php -S localhost:8000 -t public`
- **Run scheduled tasks**: `php cron.php daily` or `php cron.php hourly` from `root/`
- **Database setup**: Run `root/install/install.php` to install tables from `install.sql` (uses `config.php` credentials)
- **Linting**:
  - PHP: `phpcs` (configured via `phpcs.xml`)
  - JS/CSS: `npm run lint`
- **Testing**: Place all tests in `unit/`. Use PHPUnit.

## Project-Specific Conventions
- **Controllers**: Extend `App\Core\Controller`. Handle requests and submissions.
- **Models**: Use static methods for DB access (e.g., `User::getUserInfo($username)`).
- **Views**: PHP templates in `app/Views/`, partials in `app/Views/partials/`.
- **Error Handling**: Centralized via `ErrorManager`.
- **Session**: Use `SessionManager` singleton.
- **Email**: Use PHPMailer via `app/Core/Mailer.php`. Templates in `app/Templates/`.
- **Validation**: Use `respect/validation`.
- **Notifications**: Use `app/Helpers/MessageHelper.php` for toast messages.
- **Branding**: Managed via `app/Core/BrandingManager.php`.
- **RBAC**: Use `app/Core/RBACManager.php` for permissions.
- **Do not refactor or replace core files in `app/Core/` unless requested.**
- **LoginController**: Only incremental changes allowed.

## Integration Points
- **External Libraries**: Doctrine DBAL, FastRoute, PHPMailer, respect/validation.
- **Autoloading**: Composer autoload from `vendor/autoload.php`.
- **Config**: All environment config via `config.php` constants.

## Examples
- **Route Registration**:
  ```php
  $r->addRoute('GET', '/login', [\App\Controllers\LoginController::class, 'handleRequest']);
  $r->addRoute('POST', '/login', [\App\Controllers\LoginController::class, 'handleSubmission']);
  ```
- **Database Query**:
  ```php
  $db = DatabaseManager::getInstance();
  $db->query('SELECT * FROM users WHERE username = :username');
  $db->bind(':username', $username);
  $user = $db->single();
  ```

## Key Files & Directories
- `root/app/Core/Router.php` (routing)
- `root/app/Core/DatabaseManager.php` (DB access)
- `root/app/Core/ErrorManager.php` (error handling)
- `root/app/Core/SessionManager.php` (session)
- `root/app/Core/Mailer.php` (email)
- `root/app/Core/BrandingManager.php` (branding)
- `root/app/Core/RBACManager.php` (permissions)
- `root/config.php` (config)
- `root/public/index.php` (entry point)
- `unit/` (tests)
- `AGENTS.md` (contributor standards)
- `README.md` (usage, setup)

---

For unclear or missing conventions, ask the user for clarification or examples before making major changes.
