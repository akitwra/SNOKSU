#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export DOCKER_CONFIG="${DOCKER_CONFIG:-/tmp/snokgu-docker-$(id -u)}"
readiness_file=$(mktemp)
trap 'rm -f "$readiness_file"' EXIT
docker compose up -d --wait db app
for attempt in {1..30}; do
  if curl --fail --silent http://127.0.0.1:8000/up >/dev/null; then
    curl --fail --silent http://127.0.0.1:8000/research -o "$readiness_file"
    grep -q 'Найди вопрос' "$readiness_file"
    echo 'Laravel and PostgreSQL are running; research catalog responds.'
    exit 0
  fi
  sleep 1
done
docker compose logs --tail=30 app
exit 1
