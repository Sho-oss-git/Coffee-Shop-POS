# Coffee Shop POS

A point-of-sale and inventory management system for coffee shops, built with Laravel, Inertia.js, Vue, and Tailwind CSS.

## Requirements

- PHP 8.2 or newer
- Composer 2
- Node.js 22 or newer
- npm
- SQLite with the PHP SQLite extension enabled

## Installation

Clone the repository and enter the project directory:

```bash
git clone https://github.com/Sho-oss-git/Coffee-Shop-POS.git
cd Coffee-Shop-POS
```

Install the PHP and JavaScript dependencies:

```bash
composer install
npm install
```

Create the environment file and application key.

PowerShell:

```powershell
Copy-Item .env.example .env
New-Item -ItemType File -Path database/database.sqlite -Force
php artisan key:generate
```

macOS/Linux:

```bash
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
```

The default configuration uses SQLite. Run the migrations and seed the database:

```bash
php artisan migrate --seed
php artisan storage:link
php artisan ziggy:generate
```

## Running Locally

Start the application, queue worker, and Vite development server together:

```bash
composer run dev
```

Open [http://localhost:8000](http://localhost:8000) in a browser.

To run the services separately, use these commands in separate terminals:

```bash
php artisan serve
php artisan queue:listen --tries=1 --timeout=0
npm run dev
```

## Testing and Quality Checks

Run the Pest test suite:

```bash
php artisan test
```

Run the frontend formatter and linter:

```bash
npm run format:check
npm run lint
```

Build production frontend assets:

```bash
npm run build
```

## Environment Configuration

Do not commit `.env` or other secrets. Use `.env.example` as the template for local configuration. The default environment is configured for local development with SQLite, database-backed sessions, cache, and queues.

## Useful Commands

```bash
php artisan migrate:fresh --seed  # Reset and reseed the local database
php artisan route:list             # List registered routes
php artisan optimize:clear         # Clear cached configuration and framework files

```