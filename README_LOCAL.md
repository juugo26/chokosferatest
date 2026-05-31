# Local PHP Dev Setup

This project requires a PHP-capable server for the PHP auth and order endpoints (`register.php`, `login.php`, `orders.php`). VS Code Live Server cannot execute PHP files, so use one of these options:

## Option 1: PHP built-in server
1. Open the workspace folder in VS Code.
2. Open the command palette and run `Tasks: Run Task`.
3. Select `Run PHP built-in server`.
4. Open the browser to:
   - `http://127.0.0.1:8000/chokosfera.html`

## Option 2: PHP installed manually or via XAMPP/WAMP
1. Place this project inside your PHP server web root.
2. Start Apache/PHP.
3. Open the browser to the project URL (for example `http://localhost/chokosfera.html`).

## Important
- Do not open the page with `file://` or through static-only Live Server.
- The page must be served from a PHP-capable host so `register.php`, `login.php`, and `orders.php` can run.

## If `php` is not found
- Install PHP for Windows, or
- Use XAMPP/WAMP and the Apache web root instead.
