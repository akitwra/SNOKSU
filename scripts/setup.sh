#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export DOCKER_CONFIG="${DOCKER_CONFIG:-/tmp/snokgu-docker-$(id -u)}"
# Use the injected HTTPS proxy and the host's trusted CA bundle when provided.
# No proxy credentials or CA contents are stored in this repository.
build_args=()
if [[ -n "${HTTPS_PROXY:-}" ]]; then
  build_args+=(--network host --build-arg HTTP_PROXY --build-arg HTTPS_PROXY --build-arg "http_proxy=${HTTP_PROXY:-}" --build-arg "https_proxy=${HTTPS_PROXY}")
  proxy_host=$(python3 -c 'import os,urllib.parse; print(urllib.parse.urlsplit(os.environ["HTTPS_PROXY"]).hostname)')
  proxy_ip=$(getent ahostsv4 "$proxy_host" | awk 'NR==1 {print $1; exit}')
  [[ -z "$proxy_ip" ]] || build_args+=(--add-host "$proxy_host:$proxy_ip")
fi
if [[ -n "${SSL_CERT_FILE:-}" && -r "$SSL_CERT_FILE" ]]; then
  build_args+=(--secret "id=cloud_ca,src=$SSL_CERT_FILE")
fi
docker build "${build_args[@]}" -t snokgu-app -f docker/Dockerfile .
if [[ ! -f .env ]]; then cp .env.example .env; fi
chmod 644 docker/init-test-db.sql
docker compose up -d --wait db
# This also repairs an old volume whose init script was never run.
if [[ $(docker compose exec -T db psql -U snokgu -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='snokgu_test'") != 1 ]]; then
  docker compose exec -T db psql -U snokgu -d postgres -c 'CREATE DATABASE snokgu_test OWNER snokgu;'
fi
docker run --rm --network host --user "$(id -u):$(id -g)" \
  -e HTTP_PROXY -e HTTPS_PROXY -e NO_PROXY -e COMPOSER_HOME=/tmp/composer \
  -v "$PWD:/app" -w /app snokgu-app composer install --prefer-dist --no-interaction
if ! docker compose run --rm --no-deps app php -r 'exit(preg_match("/^APP_KEY=base64:.+/m",file_get_contents(".env")) ? 0 : 1);'; then
  docker compose run --rm --no-deps app php artisan key:generate --force
fi
docker compose run --rm app php artisan migrate --force
# DemoSeeder is idempotent and refuses production environments.
docker compose run --rm app php artisan db:seed --force
./scripts/start.sh
