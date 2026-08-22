# Tajheez

Tajheez is a Laravel 13 + Inertia.js (Vue 3) application. Its main feature is the **Material Planning** module — an event-scoped workflow for raising material requests, routing them through approvals, tracking change orders, and managing the underlying catalog/supplier data — sitting alongside existing EMS (Events/Venues/Functional Areas) and Security (Users/Roles/Permissions) administration.

## Tech stack

- **Backend**: PHP 8.3+, Laravel 13, MySQL
- **Frontend**: Vue 3 (`<script setup>`), Inertia.js 2, Vite, Tailwind CSS
- **Auth**: Laravel Breeze + Microsoft OAuth (`laravel/socialite` + `socialiteproviders/microsoft`), one-time-passwords (`spatie/laravel-one-time-passwords`)
- **Authorization**: `spatie/laravel-permission` (roles/permissions)
- **Misc**: `maatwebsite/excel` (exports), `tightenco/ziggy` (route() helper on the frontend)

## Getting started

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Set your DB credentials in `.env` (MySQL), then:

```bash
php artisan migrate
npm run dev      # Vite dev server
php artisan serve
```

Or run everything together:

```bash
composer run dev
```

## Project structure

```
app/Http/Controllers/
  MaterialPlanning/        # Material Planning module controllers (requests, catalog, suppliers, domains, areas, spaces, item groups/subgroups, service options, change orders)
  Ems/                      # Events, Venues, Functional Areas
  Auth/                     # Breeze + Microsoft OAuth + OTP

app/Models/
  MaterialPlanning/         # Domain models for the module above
  Ems/                       # Event, Venue, FunctionalArea
  GeneralSettings/Setting.php

resources/js/Pages/
  MaterialPlanning/
    Index.vue                # SPA shell — swaps "views" via a client-side ref, not separate routes
    views/                    # Dashboard, Requests, Approvals, ChangeOrders, Catalog, NewRequest, Detail, Settings, App Setups views...
    components/                # Reusable pieces (ConfirmModal, RefreshButton, DateField, ...)
  Auth/, Security/, Events/, Venues/, Fa/, Users/
```

See [CLAUDE.md](CLAUDE.md) for the architectural conventions and known gotchas behind the Material Planning module specifically.

## Database

Migrations live in `database/migrations/`. Older migration batches and full schema snapshots taken before large reorganizations are kept under `database/migrations_backup_*/` and `database/backups/` respectively — see [CHANGELOG.md](CHANGELOG.md) for when/why those were created.

## Contributing

This is an internal project — no external contribution process. Follow the conventions documented in [CLAUDE.md](CLAUDE.md) when working in this codebase with an AI coding agent.
