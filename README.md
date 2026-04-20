# Presensi PKU Sampangan

Backend API untuk sistem presensi PKU Sampangan, dibangun menggunakan Laravel 12

## Features

-   **Authentication System**: Laravel Sanctum

## Tech Stack

-   **Backend**: Laravel 12

## Getting Started

### Installation

1. Clone the repository:

```bash
git clone <repository-url>
cd presensi-pku-sampangan
```

2. Install dependencies:

```bash
composer install
```

3. Copy environment file:

```bash
cp .env-example .env
```

4. Set up environtment variables

```bash
APP_NAME=PresensiPKU
APP_ENV=local
APP_KEY=
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=db_presensi_pku_sampangan
DB_USERNAME=postgres
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost
SESSION_DOMAIN=localhost
```

5. Generate application key

```bash
php artisan key:generate
```

6. Run Migrations

```bash
php artisan migrate
```

7. Run Seeders

```bash
php artisan db:seed
```

8. Start development server

```bash
php artisan serve
```
