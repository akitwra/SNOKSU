#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
# TestCase always uses snokgu_test, independent of Docker's DB_DATABASE.
docker compose exec -T app php artisan test "$@"
