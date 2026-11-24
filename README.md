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

## Passed results and AMS accounts

The two confirm-passed buttons send only selected applications to AMS at
`POST /api/external/candidate/selection-results`. Both buttons transfer the result
and the candidate's login account. The combined button releases the configured
Candidate notification after AMS acknowledges both records. Confirm-only results
remain visible until the Candidate notification is delivered; the underlying
applications remain.

Set `AMS_ACCOUNT_ENCRYPTION_KEY` to the same independently generated secret as AMS
`CANDIDATE_ACCOUNT_ENCRYPTION_KEY`. This secret must differ from the shared API token.
Credentials contain the existing password hash inside authenticated encryption;
plaintext passwords are neither available nor transferred. AMS reuses an existing
student account only through its stored Candidate source account link. When a new
account's phone is already reserved by another AMS login, AMS leaves that account's
login phone empty and continues using the unique username/email. The Profile's
contact phone remains in the staged Profile payload. Existing AMS accounts are
never linked or modified based on matching phone numbers. Conflicting usernames
or emails, an unavailable linked account, or a result owned by another account
cause HTTP 409 and keep the application available for retry.

Before Student ID issuance, the Candidate identity and its username, email, phone,
password hash and remember token live only in AMS `provisional_laureate_accounts`.
Sync does not create a `users` row. The AMS and Student login providers authenticate
this independent provisional account with a namespaced identity, so enrollment and
password recovery remain available. Profile, entries, results and workflow history
use an explicit provisional owner rather than a borrowed user ID. Staff Student ID
completion creates the real user and transfers ownership and credentials in the
same transaction, then removes the provisional account. A failed completion leaves
the provisional login intact and commits no user. Normal Student registration
continues using its existing `users` flow.

## Node.js on the Production Server

Use Node.js **22.12.0 or newer in the Node.js 22 release line**, with npm.
The Dockerfiles in AMS and Candidate already install Node.js 22 and run
`npm ci` followed by `npm run build`.

### Option A: Deploy with Docker

Run these commands from this repository's directory on the server:

```bash
docker compose build app
docker compose up -d
docker compose exec app node --version
docker compose exec app npm --version
```

If the application service is named differently, replace `app` accordingly.
You do not need Node.js on the host when all Node commands run inside Docker.

If your Compose configuration bind-mounts the source directory over
`/var/www/html`, the host directory can hide assets built into the image.
Build the assets in the running container so they are written into the mounted
`public/build` directory. Also refresh dependencies when an existing
`node_modules` volume is reused after a lockfile change:

```bash
docker compose exec app npm ci
docker compose exec app npm run build
docker compose exec app php artisan optimize:clear
```

Verify the deployed build:

```bash
docker compose exec app sh -c 'test -s public/build/manifest.json && echo "Frontend build OK"'
```

### Option B: Deploy directly on an Ubuntu/Debian server

Install Node.js 22 with npm if it is not installed. This uses the same NodeSource
22 repository as this project's Dockerfile and requires sudo access:

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl
curl -fsSL https://deb.nodesource.com/setup_22.x -o /tmp/uhs-node22-setup.sh
sudo -E bash /tmp/uhs-node22-setup.sh
sudo apt-get install -y nodejs
node --version
npm --version
```

Then run from the application directory as the deployment user:

```bash
npm ci
npm run build
php artisan optimize:clear
test -s public/build/manifest.json && echo "Frontend build OK"
```

Install the full dependency set during the build: Vite and Tailwind are in
`devDependencies`. If the build environment sets `NODE_ENV=production`, use
`npm ci --include=dev` before `npm run build`. Keep `package-lock.json` deployed;
use `npm ci` for reproducible installs rather than updating dependencies on the
server. Repeat the install and build after deploying frontend or lockfile changes.

### What runs in production?

- Serve Laravel through Apache or Nginx/PHP-FPM (or the application Docker image).
- Deploy the entire `public/build` directory, including its manifest and hashed assets.
- Do not run `npm run dev` or expose Vite port 5173 in production.
- Do not deploy `public/hot`; remove a stale copy if Laravel tries to load a development server.
- No persistent Node server is needed for the compiled frontend.
- If assets are built in CI, deploy `public/build` with the matching application release;
  frontend-only serving then does not require Node.js on the server.
- Set `APP_ENV=production`, `APP_DEBUG=false`, and the correct HTTPS `APP_URL` in `.env`.

### PDF generation using Puppeteer / Browsershot

Server-side PDF generation is a separate Node.js runtime requirement. If those
features are used, keep Node.js, npm, Puppeteer and a compatible Chrome/Chromium
installation available to the PHP/queue user inside the same runtime environment.
Do not prune Puppeteer when it is listed in `devDependencies` but required for PDFs.

This repository's `.npmrc` sets `ignore-scripts=true`, so `npm ci` does **not**
automatically install Puppeteer's browser. Provision Chrome/Chromium and its Linux
system libraries separately. Ensure the PHP/queue user can execute the browser,
read the browser installation, and write to its cache/temp directories. A successful
Vite build alone does not verify PDF rendering; test a PDF export after deployment.

Candidate deployment note: `app/Services/DocumentRequestPdfService.php` currently specifies a macOS Chrome executable path. That PDF feature requires a Linux-compatible browser path in the application code before use on a Linux server; setting up Node.js alone does not fix this path.

## Enable the AMS → Candidate Student Account API

This integration runs when an AMS operator clicks **Received / Generate ID** in
`/admin/enrollment-reviews`. The student ID and account information are sent to
Candidate and appear in `/new-student-accounts`.

Node.js is used for frontend builds and some PDF features. The API itself runs
through Laravel/PHP; starting `npm run dev` does not enable this API.

### 1. Deploy both sides

Deploy the Candidate receiver and AMS sender together. Candidate needs its API
route, controller and `config/ams.php`. AMS needs the Generate ID action changes,
`CandidateStudentAccountSync`, its retry command, `config/candidate.php`, the
scheduled retry, and the delivery-table migration. Use a PHP version compatible
with each deployed Composer lockfile (the current Candidate lock requires PHP 8.4.1+).

Run `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`
in each application's deployment environment. If PDFs use Puppeteer, retain its
Node runtime dependencies as explained in the Node.js section.

### 2. Create one shared API token

Generate a random secret once:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Copy the resulting value into both applications' server `.env` files. Replace
`YOUR_SHARED_TOKEN` below; never commit the actual token or expose it in frontend code.

Candidate server `.env`:

```dotenv
AMS_STUDENT_ACCOUNTS_TOKEN=YOUR_SHARED_TOKEN
```

AMS server `.env`:

```dotenv
CANDIDATE_STUDENT_ACCOUNTS_URL=https://candidate.datacolabx.com/api/external/ams/new-student-accounts
CANDIDATE_STUDENT_ACCOUNTS_TOKEN=YOUR_SHARED_TOKEN
```

The two token values must match exactly. The URL must reach Candidate **from the
AMS server/container**. Do not use `127.0.0.1:8001` on separate production servers;
inside Docker, `127.0.0.1` refers to that container. For the existing host-based
local setup only, the URL is
`http://127.0.0.1:8001/api/external/ams/new-student-accounts`.

### 3. Apply migrations and refresh configuration on both servers

Run in each application directory:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

With Docker, prefix each command with `docker compose exec app`, for example:

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
```

Candidate must have `new_student_accounts`; AMS must have
`candidate_student_account_deliveries`. Do not use `migrate:fresh` on a server
containing existing data. Confirm that the Candidate operator has permission to
view the New Student Accounts resource; API authentication is separate from menu permissions.

### 4. Enable automatic retries on the AMS server

The first delivery is attempted immediately after the student ID transaction
commits. Failed deliveries remain pending. The existing Laravel scheduler runs
`candidate:sync-student-accounts` every minute.

Configure the AMS scheduler through the server's existing process supervisor or
cron. If no scheduler exists, add one cron entry, replacing the PHP executable
and application path with the real paths:

```cron
* * * * * cd /var/www/ams && /usr/bin/php artisan schedule:run >> /var/log/ams-scheduler.log 2>&1
```

Use a log destination writable by the cron user and configure log rotation. For
Docker, schedule `docker compose exec -T app php artisan schedule:run` from the AMS
Compose directory instead. Do not add a second scheduler if one already exists.
For local development, `php artisan schedule:work` runs the scheduler in the foreground.

Manual retry from AMS:

```bash
php artisan candidate:sync-student-accounts
```

The API uses a synchronous first attempt plus scheduled retries; it does not
require a new Node server or a dedicated queue worker for this integration.

### 5. Verify the connection

On Candidate, confirm that the POST endpoint is registered:

```bash
php artisan route:list --path=api/external/ams/new-student-accounts
```

From the AMS server, test access without creating a student record. Set
`AMS_CANDIDATE_TOKEN` in the shell to the shared secret before running this command:

```bash
curl -i --request POST \
  'https://candidate.datacolabx.com/api/external/ams/new-student-accounts' \
  --header "Authorization: Bearer ${AMS_CANDIDATE_TOKEN}" \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{}'
```

Expected result: **422**, because the connection and token are accepted but the
student fields are missing. Do not put the literal token into committed scripts.
Then test a controlled enrollment through AMS: click Received / Generate ID and
confirm the same student ID appears in Candidate's New Student Accounts list.

Payload fields: `student_id`, `first_name_latin`, `last_name_latin`, `email`, `phone`.
ID and both Latin names are required. Email and phone may be null. AMS supports
separate name fields and the full `name_latin` field (family name followed by given
name). An incomplete name must be corrected before confirming the enrollment.

Candidate Lists keeps canonical candidate accounts in `users`. Candidates sign in
with phone or email. An authenticated AMS delivery matches a single candidate by
normalized email or phone, copies its profile and existing password hash into
`new_student_accounts`, and physically removes the `users` row in one transaction.
New Student Accounts then supports phone, email and Student ID sign-in. No second
profile or password is retained in `users`.

New Student Accounts retain their Student ID, login contacts and history.
The Current tab shows active and inactive accounts that have not been archived.
Archive Account disables Candidate login and moves the account to the Archived
Accounts tab. Restore Account returns an archived account to Current and enables
login using its original Student ID and password. Archive and Restore require
their own role permissions; ordinary activation cannot bypass an archive.
These actions do not change AMS account state or earlier application/payment
history. Inactive and archived email/phone identifiers remain reserved, so the
student reuses the existing account rather than registering again. Password
reset changes credentials but does not restore or reactivate an archived account.
The archive migration adds a nullable timestamp and a list index without changing
existing account states. Lists use bounded pagination; archival retains database
records rather than freeing their storage.

The `account_identities` directory stores only a stable ID and its account store;
`account_users` is a read-only view over the two stores. Existing application,
payment, permission, notification and session ownership keeps the same logical
ID, even when the student record has a different physical primary key. Candidate
classification moves with the account. Ambiguous contacts, staff accounts and
conflicting Student IDs return HTTP 409 without a partial transfer. Empty AMS
contacts do not erase an existing login identifier, and retries do not reactivate
an inactive account. Creating an unrelated New Student Account manually does not
transfer a candidate; its password must be set through password reset.

After deploying the canonical-storage migration, the auth provider must use
`App\Models\AccountUser` (the default). Remove an old `AUTH_MODEL` override or set
`AUTH_MODEL=App\Models\AccountUser`, then run `php artisan config:clear` and restart
long-running workers. Canonicalize legacy candidate mirrors with a preview first:

```bash
php artisan accounts:canonicalize-candidates
php artisan accounts:canonicalize-candidates --apply
```

For an AMS account delivered before this behavior was deployed, preview its
reconciliation, then apply the reviewed Student ID:

```bash
php artisan ams:reconcile-student-account STUDENT_ID
php artisan ams:reconcile-student-account STUDENT_ID --apply
```

Both previews roll back their transactions. Reconciliation preserves the existing
student record, moves its matching candidate credentials and removes the old
`users` row while retaining login and application history.

### Responses and troubleshooting

| Response / symptom | Meaning and action |
| --- | --- |
| 201 | New Candidate student account created. |
| 200 | Existing student ID updated; retries do not create duplicates. |
| 401 | Check that both shared token values match and refresh cached configuration. |
| 404 | Check URL, deployed receiver files, API routing and route cache. |
| 409 | Email or student ID conflicts with an existing account; review the conflicting record. |
| 422 | Check required student ID and Latin names, email format and field lengths. |
| 429 | Rate limit reached; leave delivery pending for retry. |
| 500 | Inspect Candidate `storage/logs/laravel.log`, dependencies and migrations. |
| 503 | Candidate has no configured integration token. |
| Timeout / connection failure | Check DNS, HTTPS certificate, firewall and connectivity from AMS to Candidate. |
| Received action does not finish | Check AMS validation messages, Latin name fields and `storage/logs/laravel.log`. |

Inspect AMS `candidate_student_account_deliveries.last_error` and `delivered_at`
for delivery status. Initial pending deliveries retry their stored payload; correct
source data and restage the delivery through the application when the payload is
wrong. Existing historical enrollments are not automatically backfilled.

## Candidate profile import for the Student portal

The selection-results request now includes a versioned `profile` object containing
whitelisted personal/contact/family/education/work-history fields and administrative
location codes. Deploy the AMS receiver before the Candidate sender. Candidate
only hides a confirmed result after AMS acknowledges `profile_synced` and a
`profile_entry_id`, as well as the existing result/account acknowledgement.

Before a real Student ID is issued, AMS stores the profile in
`provisional_candidate_profiles`. Selection results are saved immediately in
`upload_excel_candidates`, including results for provisional accounts. Actual login
credentials and identity remain in `provisional_laureate_accounts` until Student ID
completion; there is no corresponding `users` row before that completion.

For the current Candidate payload, AMS saves the login account, provisional
listing, Profile and selection result in one database transaction. A write failure
rolls back that sync, including a newly created login account. Candidate accepts
success only after AMS acknowledges the account, result and Profile together;
failed requests remain available for Resend.

The existing staff-controlled Student ID issuance transaction imports the staged
profile into `custom_form_entries` (form slug `profile`). After verifying the final
profile and real Student ID, it deletes the temporary profile and that user's
Candidate selection-result rows from `upload_excel_candidates`. A failure rolls
back the profile transfer, result cleanup and account promotion. The final profile
stays in `custom_form_entries`; credentials and the Student ID remain on the same
account. Accounts already promoted to students sync their profiles directly to the
main table. A new application for an enrolled user still creates a pending selection
result until that application's Student ID completion step runs.

AMS retains `candidate_selection_result_receipts` containing only application/user
references, the original result ID and completion metadata. It does not retain the
selection-result body there. Completed application retries return the historical
`selection_result_id` with `selection_result_completed: true` and do not recreate
the deleted `upload_excel_candidates` row. Candidate accepts this receipt and reuses
its acknowledgement, so Resend does not duplicate the result or notification.

API responses retain the existing acknowledgement keys and add `profile_storage`
(`provisional` or `main`) and `selection_result_storage` (`main`). The returned record
IDs identify the indicated storage; a completed result ID is a historical receipt
reference rather than an existing row. Candidate preserves these independent annotations,
continues accepting legacy provisional-result acknowledgements, and treats older
acknowledgements without storage metadata as main storage.
`profile_synced: true` confirms durable receipt even while the profile is waiting
for a Student ID.

The Student portal must read the same database as AMS, as in the current local
setup. Separate Student databases require another integration. Existing profiles
already in the main table are not automatically relocated by these migrations.

Ensure the `profile` form, `custom_form_fields`, and `custom_form_entries` exist
before enabling the sender. Apply
`2026_11_23_000001_create_provisional_candidate_sync_tables` followed by
`2026_11_23_000002_move_provisional_candidate_selection_results_to_main` and
`2026_11_23_000003_create_candidate_selection_result_receipts_table` and
`2026_11_23_000004_stage_candidate_credentials_in_provisional_accounts` and
`2026_11_23_000005_use_independent_provisional_candidate_identities` on AMS.
Deploy the staged-credential authentication code to both AMS and the shared-database
Student app before these migrations. Refresh cached authentication config in both
apps when deploying it. Migration 000005 moves existing pending identity data and
all supported ownership references into provisional storage before removing their
old user rows. It aborts on unknown references rather than allowing cascaded data
loss. Issued students and manual accounts keep their existing storage. Already
issued application acknowledgements remain historical receipts; new provisional
`account_id` values use `provisional:<id>`. The legacy user ID and namespaced session
alias resolve to the final user after successful Student ID issuance.
The forward migration transfers any existing staged results to
`upload_excel_candidates` before removing the obsolete results staging table. Existing
Candidate acknowledgement receipts remain valid for retry deduplication, including
historical provisional-result references. Use the existing selection-results API
token and independent account encryption key. The AMS-to-Candidate
new-student-account token is a different flow.

For previously promoted Candidate accounts, preview eligible result cleanup with
`php artisan candidate:cleanup-promoted-results --user=USER_ID`, replacing `USER_ID`
with the AMS user ID. Review the preview, then add `--apply` to perform the cleanup.
Only Candidate-linked rows with verified Student ID completion and a final profile
are eligible; unrelated/manual selection results are preserved.

Repeated requests reuse the user's latest profile, fill missing fields and preserve
existing student edits, completed/approved status, IDs, passwords and academic data.
A complete imported profile has status `completed`; incomplete profiles remain
`draft` with their available values prefilled. The Student portal still requires
missing mandatory fields to be completed. Imports bypass form-submission observers,
so profile import does not send notifications or generate a new enrollment review.

Files/photos are not copied by this profile-data extension. Files already present
in Student remain untouched; missing uploads and any Student-only required fields
must be supplied there. Existing historical results are not automatically resent.
Changes remain local until explicitly committed/pushed and deployed.
### Dynamic Custom Form profile fields

Profile sync now derives its allowed fields and repeater children from the Custom
Form whose slug is `profile` in each application. There is no PHP field-name list
to extend when adding a normal input. Create the matching field **name** in both
Candidate and AMS/Student; changing only a translated label does not change mapping.
The Student app continues reading the same AMS profile entry from the shared database.

Sections/groups are traversed, repeater child names are validated recursively, and
required fields/conditional `visible_when` rules determine whether the imported
profile is completed or remains draft. Dates, numbers, booleans, multi-selects and
configured choice keys are validated from the destination schema. New required
Student-only inputs remain for the student to fill; completed/approved profiles
and existing edited values are preserved on retry.

Top-level geographic fields use `geo_location_type` and
`geo_location_value_column` in their Custom Form options to translate source IDs
to administrative codes and then to destination IDs. Geographic selectors nested
inside repeaters are not transferred; they remain for completion in Student.
File/image/signature inputs, hidden/display-only components and account-control
names such as passwords, roles, account status and student IDs are excluded even
if configured in a form. Unknown destination field names are rejected with 422.
Deploy matching form definitions before enabling newly added source fields.
Profile compatibility notes: section/group names may be Khmer; their child input
names remain the API keys. The AMS receiver combines split Khmer/Latin name parts
when the destination form stores a full name, converts Khmer digits for numeric
inputs, and applies destination `integration_value_aliases` options. The default
education alias `high_school_diploma` → `diploma` is configured under
`services.candidate_integration.profile_value_aliases`. Unknown/protected input
names still fail validation rather than being discarded. Candidate notifications
show selected, successfully sent, and failed counts separately, with validation
field names when AMS returns HTTP 422.
