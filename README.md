# Project Setup

## Requirements

- Docker Engine
- Docker Compose v2
- Git
- A PostgreSQL or MySQL database
- Node.js and npm only if frontend assets are built outside Docker

PHP and Composer do not need to be installed on the host if they are included in your application Docker image.

## Installation

Clone the repository and enter the project directory:

```bash
git clone <YOUR_REPOSITORY_URL>
cd <YOUR_PROJECT_DIRECTORY>
```

## Environment Configuration

Create your environment file:

```bash
cp .env.example .env
```

Edit `.env` and configure your application settings and database credentials.

For example, when PostgreSQL runs in another server:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

DB_CONNECTION=pgsql
DB_HOST=YOUR_DATABASE_SERVER_IP
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

If the database runs as a service in the same Docker Compose project, use its service name for `DB_HOST` instead of `localhost`.

Keep production secrets out of Git.

## Build and Start Docker

Validate your Compose configuration:

```bash
docker compose config
```

Build the application image:

```bash
docker compose build
```

Start the services:

```bash
docker compose up -d
```

Check the running containers:

```bash
docker compose ps
```

View application logs:

```bash
docker compose logs -f
```

## Application Key

For a new installation, generate the Laravel application key if `APP_KEY` is empty:

```bash
docker compose exec app php artisan key:generate
```

Replace `app` with the Laravel service name defined in your `docker-compose.yml`.

Keep the generated key persistent across deployments.

## Frontend Assets

If your Dockerfile builds the frontend assets, no additional host commands are necessary.

Otherwise, build them in the appropriate build environment:

```bash
npm ci
npm run build
```

Ensure the compiled assets are included in the application image or deployed to the correct public directory.

## Database Migration and Seeding

For a **new development or test database**, run:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

**Warning:** `migrate:fresh` drops all existing tables before recreating them. Never run it against a production database containing data you need to preserve.

For normal production deployment, use:

```bash
docker compose exec app php artisan migrate --force
```

## Run the Project

Start the application:

```bash
docker compose up -d
```

Check the published port:

```bash
docker compose ps
```

Access the application using the URL configured for your published application port or reverse proxy.

For example, if Compose maps host port `8080` to the application container's HTTP port:

`http://YOUR_SERVER_IP:8080`

## Queue Worker (Notifications)

To process queued notifications, emails, and background jobs, run:

```bash
docker compose exec app php artisan queue:work
```

For production, run the queue worker as a dedicated Compose service or under a process supervisor so it continues running and restarts after failures.

After deploying updated code, restart long-running workers as needed:

```bash
docker compose exec app php artisan queue:restart
```

Ensure the queue connection is configured correctly in `.env`, and that the queue backend is reachable from the application container.

## Clear Laravel Caches

After deployment, clear cached configuration and application state:

```bash
docker compose exec app php artisan optimize:clear
```

## Update the Application

To deploy new code:

```bash
git pull --ff-only origin main
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
docker compose ps
```

Back up production data before migrations and verify the Compose configuration before deploying.

## Stop the Project

Stop the running containers:

```bash
docker compose stop
```

To stop and remove containers and the Compose network:

```bash
docker compose down
```

Avoid `docker compose down -v` unless you intentionally want to delete persistent volumes.

## Troubleshooting

View application logs:

```bash
docker compose logs --tail=100 app
```

Check container status:

```bash
docker compose ps
```

Enter the application container:

```bash
docker compose exec app sh
```

If your Laravel service is not named `app`, replace `app` in these commands with the actual service name from `docker-compose.yml`.
