# Assessment System Backend

This repository serves as the central API and Grading Engine for the Assessment System. It is built using **Laravel** and runs on **Docker (via Laravel Sail)** to ensure a consistent, reproducible environment across the team.

---

## Prerequisites

Before you start, ensure you have the following installed on your machine:

* **[Docker Desktop](https://www.docker.com/products/docker-desktop/)**: Ensure it is running before launching the application.
* **Git**: To clone the repository.

---

## Getting Started

### 1. Clone the repository

```bash
git clone <your-repo-url>
cd assessment-system

```

### 2. Configure Environment

Copy the example environment file to create your local configuration:

```bash
cp .env.example .env

```

*(No changes are typically required for local development unless you need to override default database ports).* 
DO NOT CHANGE ANYTHING

### 3. Install PHP Dependencies

You do not need to install PHP or Composer locally. Use the official Laravel Sail image to install the required packages:

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php83-composer:latest \
    composer install --ignore-platform-reqs

```

### 4. Start the Application

Spin up the Docker containers in the background:

```bash
./vendor/bin/sail up -d

```

*(The first run may take a few minutes as it downloads the database and PHP images).*

---

## Database Setup (Multi-Tenancy)

Because this system uses a **multi-tenant architecture**, you must initialize both the system-level (Landlord) database and the individual tenant databases. **You must run these in order.**

### Part 1: Landlord Setup (System Base)

These commands set up the shared infrastructure, tenant registry, and administrative tables.

```bash
# Run migrations for the Landlord
./vendor/bin/sail artisan migrate --path=database/migrations/landlord

# Seed the Landlord database
./vendor/bin/sail artisan db:seed --class=LandlordSeeder

```

### Part 2: Tenant Setup

Once the Landlord is initialized, you must apply the schemas to the tenant databases.

```bash
# Run migrations for all tenants
./vendor/bin/sail artisan tenants:migrate

# Seed the tenant databases with initial data
./vendor/bin/sail artisan tenants:seed --class=TenantMasterSeeder

```

---

## Common Commands (Cheat Sheet)

| Task | Command |
| --- | --- |
| **Start Containers** | `./vendor/bin/sail up -d` |
| **Stop Containers** | `./vendor/bin/sail down` |
| **List All Tenants** | `./vendor/bin/sail artisan tenants:list` |
| **Run Tests** | `./vendor/bin/sail artisan test` |
| **Clear Cache** | `./vendor/bin/sail artisan optimize:clear` |
| **View Logs** | `./vendor/bin/sail logs -f` |

---

## API Access

* **Base URL:** `http://localhost/api/v1`
* **Credentials:** Default test credentials can be found in `database/seeders/TenantMasterSeeder.php`.

---

## Troubleshooting

* **"Connection Refused" (MySQL):**
Ensure Docker Desktop is fully running. If it is, run `./vendor/bin/sail down` followed by `./vendor/bin/sail up -d` to refresh the network stack.
* **"Table Not Found" Errors:**
This usually means the Tenant migrations were not run, or they were run *before* the Landlord migrations. Run the migration commands listed in the "Database Setup" section above in order.
* **Port Conflicts:**
If you have another service running on port 80 or 3306, you will need to edit the `docker-compose.yml` file to map to different host ports (e.g., change `80:80` to `8080:80`).

---

## Need Help?

If you are blocked, reach out.
