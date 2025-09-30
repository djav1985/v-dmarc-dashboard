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
