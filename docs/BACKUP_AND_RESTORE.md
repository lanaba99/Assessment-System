# Backup and Restore

**No backup mechanism exists in this codebase, and none is claimed to.** The audit found dead
scaffolding for one — `backup_jobs`/`backup_schedules` tables and `BackupLog`/`BackupSchedule`
models exist (`app/Domains/Central/Models/BackupLog.php`, `BackupSchedule.php`) but are referenced
nowhere outside their own files: a feature that was planned and never built. No backup package is
in `composer.json`, no `config/backup.php` exists, no artisan command runs a backup.

This document is **operational documentation only** — exact, correct, copy-pasteable commands —
not a new dependency or a new artisan command, per the explicit instruction not to claim backups
exist until an actual provider or scheduled job is configured. Adding a new backup package
(e.g. `spatie/laravel-backup`) is a reasonable next step but is a real dependency/scope decision
for you to make, not something to add silently inside a security-audit stage.

## What needs backing up

1. **The landlord/central MySQL database** — tenant registry, central admin users, central audit
   trail. Losing this loses the ability to resolve *any* tenant.
2. **Every tenant's own MySQL database** (`tenant_<uuid>`) — per stancl/tenancy's multi-database
   isolation, each tenant is a completely separate database that needs its own backup coverage,
   not just "the database."
3. **Certificate/file storage** (`storage/app/` — see [SECURITY_BASELINE.md](SECURITY_BASELINE.md)
   for how this is tenant-remapped under `storage/tenant_<uuid>/app/`) — generated certificate
   PDFs currently have no backup or regeneration path if lost; they're generated once at
   publish time (`CertificateGenerationService`) and never regenerated automatically.

## MySQL backup commands

**Landlord database:**
```bash
mysqldump --single-transaction --quick --routines --triggers \
  -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  | gzip > "landlord-$(date +%Y%m%d-%H%M%S).sql.gz"
```
`--single-transaction` avoids locking InnoDB tables for the duration of the dump — safe to run
against a live database without blocking writes.

**Every tenant database — the strategy, not a guessed exact loop.** Tenant database names follow
`tenant_<uuid>` (`config/tenancy.php` `prefix: 'tenant_'`). A real backup job should enumerate
tenants via `php artisan tenants:list` (or query the landlord `tenants` table directly) and
`mysqldump` each one:
```bash
php artisan tenants:list --plain | while read -r line; do
  tenant_id=$(echo "$line" | grep -oE '[0-9a-f-]{36}')
  [ -z "$tenant_id" ] && continue
  mysqldump --single-transaction --quick \
    -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "tenant_${tenant_id}" \
    | gzip > "tenant_${tenant_id}-$(date +%Y%m%d-%H%M%S).sql.gz"
done
```
Run this as a scheduled job outside the application (cron, a CI/CD scheduled pipeline, or your
managed MySQL provider's own automated backup/snapshot feature — most managed providers, RDS
included, offer point-in-time recovery that covers this more robustly than a cron+mysqldump
script and should be preferred where available).

## Certificate/file storage backup

```bash
# From the app container/host, per tenant (or all of storage/ if simpler):
tar czf "storage-backup-$(date +%Y%m%d-%H%M%S).tar.gz" storage/app/
```
Sync the resulting archive off-host (see "Off-site storage" below). If you use the `s3` disk
(`config/filesystems.php` — configured but not confirmed in active use for certificates, which
currently use `local`) instead of local storage for certificates, your cloud storage provider's
own versioning/replication (S3 versioning + cross-region replication, etc.) can substitute for a
custom backup script entirely — evaluate switching certificate storage to `s3` as part of a real
production backup strategy, since it removes an entire class of "did the backup script actually
run" risk.

## Encryption requirements

Both the MySQL dumps and the storage archive above contain candidate PII (names, emails) and
exam/certification records. At minimum:
```bash
gpg --symmetric --cipher-algo AES256 --output backup.sql.gz.gpg backup.sql.gz
```
or rely on your off-site storage provider's server-side encryption (S3 SSE, GCS default
encryption, etc.) — either is acceptable, but backups should never sit unencrypted in long-term
storage. Manage the encryption key/passphrase through your platform's secrets manager, never
committed to this repository.

## Retention policy

No retention policy is defined anywhere in this codebase (the dead `BackupSchedule.retention_days`
column notwithstanding — it's never read). Recommend, as a starting point subject to your actual
compliance requirements: daily backups retained 30 days, weekly retained 90 days, monthly retained
1 year — adjust to whatever data-retention obligations apply to the exam/certification records
this system holds (this is a compliance question this repository has no authority to answer for
you).

## Off-site storage requirements

Backups stored on the same host/volume as the database they back up don't protect against
host/region loss. Sync to a separate provider/region:
```bash
# Example using rclone or aws-cli, whichever your platform already has —
# neither is assumed to be installed here.
aws s3 cp backup.sql.gz.gpg s3://your-backup-bucket/mysql/ --storage-class STANDARD_IA
```

## Safe restore-test procedure (non-production data only)

**Never run this against a real tenant or the real landlord database.** Test restores against a
disposable database — the Docs Sandbox Tenant's own database (Stage 1) is a good, already-fake,
disposable target:
```bash
# 1. Take a fresh sandbox tenant (safe — sandbox:setup only ever touches the
#    sandbox tenant, never a real one — see ENVIRONMENT_SETUP.md).
php artisan sandbox:reset

# 2. Back it up using the commands above, against the sandbox tenant's
#    database only (find its exact tenant_<uuid> name via `tenants:list`
#    filtered to SCRIBE_TENANT_ID).

# 3. Deliberately corrupt/drop it to prove the restore actually recovers data:
php artisan tenants:migrate-fresh --tenants=<sandbox-tenant-id> --force

# 4. Restore from the backup taken in step 2:
gunzip < tenant_<sandbox-id>-<timestamp>.sql.gz | mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "tenant_<sandbox-id>"

# 5. Re-run sandbox:setup's own verification (or manually confirm expected
#    seeded rows exist) to prove the restore actually worked, not just that
#    the mysql command exited 0.
```

## Verification / checksum procedure

```bash
sha256sum backup.sql.gz > backup.sql.gz.sha256
# ...after transferring off-site:
sha256sum -c backup.sql.gz.sha256
```
Run this as part of any backup pipeline so a silently-truncated or corrupted transfer is caught
before you need the backup, not during an actual incident.

## What this document does NOT do

It does not run any backup, does not connect to any database, and does not claim a backup exists.
Until a real scheduled job (cron, CI/CD pipeline, or a managed-provider automated backup feature)
is actually configured and running against your real infrastructure, **no backup of this
application's data exists** — this document is the procedure for creating one, not proof one is
already in place.
