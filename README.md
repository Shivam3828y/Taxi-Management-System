# Taxi Management System

A PHP/MySQL taxi administration and driver portal.

## Replit setup

1. Create a MySQL-compatible database (for example, Replit SQL or an external managed MySQL service).
2. Add these values as Replit Secrets. Do not commit them to the repository:

   - `DB_HOST`
   - `DB_PORT` (usually `3306`)
   - `DB_NAME` (usually `taxi_management`)
   - `DB_USER`
   - `DB_PASSWORD`
   - `APP_DEBUG=0`
   - `SESSION_COOKIE_SECURE=1` when served over HTTPS

3. Initialize the schema once from a trusted shell:

   ```bash
   mysql --protocol=tcp -h "$DB_HOST" -P "${DB_PORT:-3306}" \
     -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < database.sql
   ```

   The schema uses `CREATE TABLE IF NOT EXISTS` and does not delete or reset existing records.
4. Start the application with `bash start.sh`. Replit supplies `PORT`; the server binds to `0.0.0.0`.
5. Create the first admin record using a password generated with PHP's `password_hash()`; no default production account is seeded.

## Local checks

```bash
php -l config.php
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

The application requires PHP with the `mysqli` extension and a reachable MySQL-compatible database. `php-error.log` is ignored by Git and should not be exposed publicly.
