#!/usr/bin/env bash

# Canonical PHP quality and test lane. Both local parity and Actions execute
# these commands inside the PHP 8.2 container from bin/ci/.wp-env.json.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
THEME_CWD="wp-content/themes/aggressive-apparel"

cleanup() {
	if ! bash "${SCRIPT_DIR}/stop-wp-env.sh"; then
		echo "Warning: CI parity containers could not be stopped." >&2
	fi
}
trap cleanup EXIT

AA_CI_XDEBUG_MODE=coverage bash "${SCRIPT_DIR}/reset-wp-env.sh"

ci_php() {
	bash "${SCRIPT_DIR}/wp-env.sh" run tests-cli \
		--env-cwd="${THEME_CWD}" \
		-- bash -c "$1"
}

ci_php 'XDEBUG_MODE=off composer validate --strict --no-interaction'
ci_php 'XDEBUG_MODE=off composer install --no-interaction --prefer-dist --no-progress'
ci_php 'XDEBUG_MODE=off find includes -name "*.php" -exec php -l {} \; >/dev/null && echo "PHP syntax valid"'
ci_php 'XDEBUG_MODE=off composer lint:php'
ci_php 'XDEBUG_MODE=off ./vendor/bin/phpstan analyse --memory-limit=2G --verbose'
ci_php 'XDEBUG_MODE=coverage ./vendor/bin/phpunit --testsuite=unit --coverage-clover=coverage-unit.xml.tmp && test -s coverage-unit.xml.tmp && mv coverage-unit.xml.tmp coverage-unit.xml'
ci_php 'XDEBUG_MODE=off ./vendor/bin/phpunit --testsuite=integration --verbose'
ci_php 'XDEBUG_MODE=off ./vendor/bin/phpunit --testsuite=security --verbose'
ci_php 'XDEBUG_MODE=off ./vendor/bin/phpunit --testsuite=accessibility --verbose'
ci_php 'XDEBUG_MODE=off ./vendor/bin/phpunit --testsuite=performance --verbose'

if ! ci_php 'XDEBUG_MODE=off composer audit'; then
	echo "Composer reported a development-tool advisory (informational)." >&2
fi
