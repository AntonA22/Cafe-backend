# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Install all dependencies and set up environment
composer setup

# Run development environment (PHP server + queue + logs + Vite)
composer dev

# Run tests (clears config cache first)
composer test

# Run a single test file or method
php artisan test --filter TestClassName
php artisan test tests/Feature/SomeTest.php

# Code style (Laravel Pint)
./vendor/bin/pint

# Database migrations
php artisan migrate
php artisan migrate:fresh --seed
```

## Architecture

**Laravel 12 REST API** for a dessert delivery cafe. Authentication via Laravel Sanctum (Bearer tokens). Admin access gated by `is_staff` boolean on the `users` table, enforced by `AdminMiddleware`.

### Route structure (`routes/api.php`)

- **Public:** product listing/search, auth register/login
- **Auth (`auth:sanctum`):** profile, cart, addresses, orders
- **Admin (`auth:sanctum` + `AdminMiddleware`):** product CRUD, order management

### Key flows

**Cart:** Lazy-created per user (`firstOrCreate`). Prices are frozen at time of item insertion. Cart is cleared atomically when order is created (database transaction).

**Orders:** UUID primary keys. Created in a transaction that snapshots cart items into `order_items`, calculates totals, and clears the cart.

**Product photos:** Stored as a JSON array in `desserts.photos` column. Uploaded via a dedicated `POST /admin/products/{id}/photo` endpoint.

### Response format

Controllers return `JsonResource` classes from `app/Http/Resources/`. Request validation lives in `app/Http/Requests/`.

### Testing

PHPUnit with two suites: `Unit` and `Feature`. Tests run against an in-memory SQLite database (configured in `phpunit.xml`).
