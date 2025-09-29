# Copilot Coding Agent Instructions for V PHP Framework

## Project Overview
- **V PHP Framework** is a minimal PHP web application starter. All main source code is under `root/`.
- The architecture is MVC-like: `app/Controllers/`, `app/Models/`, `app/Views/`, with core logic in `app/Core/`.
- Routing is handled by `app/Core/Router.php` using FastRoute. Routes are registered in the constructor.
- Database access is via `app/Core/DatabaseManager.php`, which wraps Doctrine DBAL and uses singleton access.
- Configuration is in `root/config.php` (constants for DB, SMTP, session, etc.).
- Entry point is `root/public/index.php`, which initializes session, error handling, and dispatches requests.

## Developer Workflows
- **Install dependencies:**
  - PHP: `composer install` in both repo root and `root/`.
  - Node (for linting): `npm install` in repo root.
- **Run app:**
  - `cd root && php -S localhost:8000 -t public`
- **Run scheduled tasks:**
  - `php cron.php daily` or `php cron.php hourly` from `root/`.
- **Database setup:**
  - Use `root/install/install.php` to install tables from `install.sql` using credentials from `config.php`.
- **Linting:**
  - PHP: `phpcs` (configured via `phpcs.xml`)
  - JS/CSS: `npm run lint`

## Conventions & Patterns
- **Controllers**: Extend `App\Core\Controller`, handle requests and submissions.
- **Models**: Static methods for DB access, e.g. `User::getUserInfo($username)`.
- **Views**: PHP templates in `app/Views/`, partials in `app/Views/partials/`.
- **Error Handling**: Centralized via `ErrorManager` (see `app/Core/ErrorManager.php`).
- **Session**: Managed by `SessionManager` singleton.
- **Testing**: Place tests in `unit/`.
- **Code Quality**: Follow AGENTS.md for commit, PR, and test standards.

## Integration Points
- **External Libraries**: Doctrine DBAL, FastRoute, PHPMailer.
- **Autoloading**: Composer autoload from `vendor/autoload.php`.
- **Config**: All environment config via `config.php` constants.

## Examples
- **Route Registration**:
  ```php
  $r->addRoute('GET', '/login', [LoginController::class, 'handleRequest']);
  ```
- **Database Query**:
  ```php
  $db = DatabaseManager::getInstance();
  $db->query('SELECT * FROM users WHERE username = :username');
  $db->bind(':username', $username);
  $user = $db->single();
  ```

## Key Files
- `root/app/Core/Router.php` (routing)
- `root/app/Core/DatabaseManager.php` (DB access)
- `root/config.php` (config)
- `root/public/index.php` (entry point)
- `AGENTS.md` (contributor standards)
- `README.md` (usage, setup)

---

For unclear or missing conventions, ask the user for clarification or examples before proceeding with major changes.
