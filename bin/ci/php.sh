#!/usr/bin/env bash

# Canonical PHP quality and test lane. Both local parity and Actions execute
# these commands inside the PHP 8.2 container from bin/ci/.wp-env.json.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
THEME_CWD="wp-content/themes/aggressive-apparel"

# Pinned, checksum-verified Composer. The container image's own Composer is
# whatever WordPress shipped with it, so without this the lockfile metadata and
# dependency resolution vary by who ran the lane.
bash "${SCRIPT_DIR}/install-composer.sh"

cleanup() {
	if ! bash "${SCRIPT_DIR}/stop-wp-env.sh"; then
		echo "Warning: CI parity containers could not be stopped." >&2
	fi
}
trap cleanup EXIT

AA_CI_XDEBUG_MODE=coverage bash "${SCRIPT_DIR}/reset-wp-env.sh"

# composer.json has no `version` field on purpose — the theme's version lives in
# style.css and is managed by semantic-release, so duplicating it would drift.
# Composer then prints "could not detect the root package version, defaulting to
# 1.0.0" on every single call. Nothing depends on the root version, but six
# copies of that notice per run reads like a problem. Telling Composer the real
# version up front removes it, and reading it from style.css keeps it accurate
# without a second source of truth.
# shellcheck source=../release/lib.sh
source "${REPO_ROOT}/bin/release/lib.sh"
COMPOSER_ROOT_VERSION="$(aa_release_style_version "${REPO_ROOT}/style.css")"

# PATH puts bin/ci first so `composer` resolves to the pinned PHAR shim.
ci_php() {
	bash "${SCRIPT_DIR}/wp-env.sh" run tests-cli \
		--env-cwd="${THEME_CWD}" \
		-- bash -c "COMPOSER_ROOT_VERSION=\"${COMPOSER_ROOT_VERSION}\" PATH=\"\$PWD/bin/ci:\$PATH\" $1"
}

ci_php 'XDEBUG_MODE=off composer validate --strict --no-interaction'
ci_php 'XDEBUG_MODE=off composer install --no-interaction --prefer-dist --no-progress'
ci_php 'XDEBUG_MODE=off find includes -name "*.php" -exec php -l {} \; >/dev/null && echo "PHP syntax valid"'
ci_php 'XDEBUG_MODE=off composer lint:php'
ci_php 'XDEBUG_MODE=off ./vendor/bin/phpstan analyse --memory-limit=2G --verbose'
ci_php 'XDEBUG_MODE=coverage ./vendor/bin/phpunit --testsuite=unit --coverage-clover=coverage-unit.xml.tmp && test -s coverage-unit.xml.tmp && mv coverage-unit.xml.tmp coverage-unit.xml'

# The integration suite asserts that compiled catalogs actually reach __(), so
# it needs those catalogs to exist. They are gitignored build output, and until
# now only bin/ci/package.sh produced them — which runs after this lane and in
# a different job. Compiling here is what lets the translation-loading test
# guard anything in CI rather than reporting a missing file.
#
# Runs on the host, not through ci_php: compile.sh needs WP-CLI's i18n package,
# which the wp-env cli container does not ship — the same split that keeps
# validate-po.sh on the host. The theme directory is bind-mounted, so catalogs
# written here are the ones the container reads a moment later.
bash "${REPO_ROOT}/bin/i18n/compile.sh"

ci_php 'XDEBUG_MODE=off ./vendor/bin/phpunit --testsuite=integration --verbose'
ci_php 'XDEBUG_MODE=off ./vendor/bin/phpunit --testsuite=security --verbose'
ci_php 'XDEBUG_MODE=off ./vendor/bin/phpunit --testsuite=accessibility --verbose'
ci_php 'XDEBUG_MODE=off ./vendor/bin/phpunit --testsuite=performance --verbose'

# Informational by design: composer.json declares no runtime dependencies (only
# a php constraint), so every advisory Composer can report here concerns build
# and test tooling that never ships inside the theme package. The shipping
# artifact's supply chain is asserted by bin/release/verify-package.sh instead.
if ! ci_php 'XDEBUG_MODE=off composer audit'; then
	echo "Composer reported a development-tool advisory (informational)." >&2
fi
