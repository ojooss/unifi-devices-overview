#!/usr/bin/env bash
set -e

ROOT="$(git rev-parse --show-toplevel)"

echo "==> Building tester image..."
docker build --target tester -t unifi-overview-test "$ROOT"

echo "==> Running phpcs + phpstan + phpunit..."
docker run --rm unifi-overview-test

echo "==> Running Rector dry-run..."
docker run --rm \
    -v "$ROOT/rector.php:/var/www/html/rector.php" \
    -v "$ROOT/src:/var/www/html/src" \
    -v "$ROOT/tests:/var/www/html/tests" \
    unifi-overview-test vendor/bin/rector process --dry-run

echo ""
echo "All gates passed."
