# TRETECH Backend

Laravel 13 backend for TRETECH inventory lifecycle operations (stock-in, consignment, returns, reconciliation, disposal, reporting, usage summary, ERP push, and audit logging).

## Documentation

Complete backend documentation is available at:

- `doc/BACKEND_DOCUMENTATION.md`

Supporting docs:

- `doc/POSTMAN_API_TESTING_GUIDE.md`
- `doc/API_RESPONSE_STANDARD.md`
- `doc/EXCEPTION_HANDLING.md`
- `doc/ROLES_AND_PERMISSIONS.md`
- `doc/PERMISSIONS_IMPLEMENTATION_GUIDE.md`

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
composer run dev
```

## Run Tests

```bash
composer test
```
