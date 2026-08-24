#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root"

if [[ ! -f .env ]]; then
  echo "Missing .env in $root. Copy .env.production.example and fill in secrets." >&2
  exit 1
fi

git fetch origin
git checkout main
git pull --ff-only origin main

docker compose --env-file .env -f docker/compose.prod.yml up -d --build
