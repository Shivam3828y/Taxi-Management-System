#!/usr/bin/env bash
set -euo pipefail

PORT="${PORT:-3000}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

if [[ "${RUN_DB_INIT:-0}" == "1" ]]; then
  : "${DB_HOST:?DB_HOST is required when RUN_DB_INIT=1}"
  : "${DB_USER:?DB_USER is required when RUN_DB_INIT=1}"
  : "${DB_PASSWORD:?DB_PASSWORD is required when RUN_DB_INIT=1}"
  mysql --protocol=tcp -h "${DB_HOST}" -P "${DB_PORT:-3306}" -u "${DB_USER}" -p"${DB_PASSWORD}" < database.sql
fi

exec php -S "0.0.0.0:${PORT}" -t "$ROOT"
