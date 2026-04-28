#!/usr/bin/env bash
set -euo pipefail

# Run Drupal Kernel tests for ai_provider_openrouter inside DDEV with a DB.
# Usage:
#   ddev exec bash web/modules/contrib/ai_provider_openrouter/scripts/run-kernel-tests.sh
# Optional env:
#   TESTSUITE (default: kernel)
#   GROUP (default: ai_provider_openrouter)

: "${TESTSUITE:=kernel}"
: "${GROUP:=ai_provider_openrouter}"

export SIMPLETEST_DB='mysql://db:db@db/db'

cd /var/www/html
./vendor/bin/phpunit -c web/core --testsuite "${TESTSUITE}" --group "${GROUP}"
