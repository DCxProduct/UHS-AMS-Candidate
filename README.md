# Project Setup

## Requirements

* PHP
* Composer
* Node.js & NPM
* Laravel
* PostgreSQL/MySQL

## Installation

Clone the repository and run the following commands:

```bash
composer install

npm install

npm run build
```

## Environment Configuration

Configure your `.env` file and update the database credentials.

```bash
cp .env.example .env
```

Then generate the application key:

```bash
php artisan key:generate
```

## Database Migration

Run the migration command:

```bash
php artisan migrate:fresh --seed
```

## Run the Project

Start the Laravel development server:

```bash
php artisan serve
```

## Queue Worker (Notifications)

To process queued notifications, emails, and background jobs, run:

```bash
php artisan queue:work
```

> Make sure the queue connection is configured correctly in your `.env` file before starting the queue worker.
