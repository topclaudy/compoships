#!/bin/bash
#
# Runs the CI test matrix locally using Docker.
# Mirrors .github/workflows/run-tests.yml exactly:
#   laravel: [13.*, 12.*]
#   php:     [8.5, 8.4, 8.3, 8.2]
#   exclude: laravel 13.* + php 8.2
#
# Usage:   ./run-matrix-tests.sh [filter]
# Example: ./run-matrix-tests.sh "12.*"   (only Laravel 12 combos)
# Example: ./run-matrix-tests.sh "PHP8.4" (only PHP 8.4 combos)
#

set -euo pipefail

FILTER="${1:-}"
PASSED=0
FAILED=0
SKIPPED=0
RESULTS=()

run_test() {
    local php_version="$1"
    local laravel_version="$2"
    local label="L${laravel_version} - PHP${php_version}"

    if [[ -n "$FILTER" && "$label" != *"$FILTER"* ]]; then
        SKIPPED=$((SKIPPED + 1))
        return
    fi

    echo ""
    echo "=========================================="
    echo "  ${label}"
    echo "=========================================="

    if docker run --rm \
        -v "$(pwd):/app" \
        -w /app \
        --tmpfs /app/vendor:exec \
        "php:${php_version}-cli" \
        bash -c "
            apt-get update -qq && apt-get install -yqq git unzip libsqlite3-dev > /dev/null 2>&1 && \
            docker-php-ext-install pdo_sqlite > /dev/null 2>&1 && \
            cp -r /app/. /tmp/workdir && cd /tmp/workdir && \
            curl -sS https://getcomposer.org/installer | php -- --quiet && \
            php composer.phar require 'illuminate/database:${laravel_version}' --no-interaction --no-update && \
            php composer.phar update --no-interaction --prefer-dist --no-progress && \
            php vendor/bin/phpunit
        " 2>&1; then
        PASSED=$((PASSED + 1))
        RESULTS+=("PASS  ${label}")
    else
        FAILED=$((FAILED + 1))
        RESULTS+=("FAIL  ${label}")
    fi
}

echo "Starting CI matrix test run..."
echo "Filter: ${FILTER:-<none>}"
echo ""

# Mirrors .github/workflows/run-tests.yml
for laravel in "13.*" "12.*"; do
    for php in "8.5" "8.4" "8.3" "8.2"; do
        # Workflow exclusion: Laravel 13 does not support PHP 8.2
        [[ "$laravel" == "13.*" && "$php" == "8.2" ]] && continue
        run_test "$php" "$laravel"
    done
done

# Summary
echo ""
echo "=========================================="
echo "  RESULTS SUMMARY"
echo "=========================================="
for r in "${RESULTS[@]}"; do
    echo "  $r"
done
echo ""
echo "  Passed:  ${PASSED}"
echo "  Failed:  ${FAILED}"
echo "  Skipped: ${SKIPPED}"
echo "=========================================="

if [[ "$FAILED" -gt 0 ]]; then
    exit 1
fi
