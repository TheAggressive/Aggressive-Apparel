#!/usr/bin/env bash
#
# Forward-compatibility PHP lane.
#
# Runs the canonical PHP lane (bin/ci/php.sh) against a PHP version NEWER than
# the supported floor, to surface deprecations before a host upgrade does.
#
# This exists because development and the release gate deliberately run the same
# PHP version — see bin/ci/contracts.mjs. That parity is what stops an API
# missing on the floor from passing locally and failing in Actions, but it also
# means nothing exercises newer PHP. Forward coverage belongs here, on a
# schedule, rather than in a development environment that disagrees with the
# gate.
#
# Usage: bin/ci/php-forward.sh <php-version>
#   e.g. bin/ci/php-forward.sh 8.4
#
# Uses its own wp-env home and ports so it never disturbs the parity
# environment that `pnpm qa` depends on.
set -euo pipefail

PHP_VERSION="${1:?Usage: php-forward.sh <php-version> (e.g. 8.4)}"

if [[ ! "${PHP_VERSION}" =~ ^8\.[0-9]+$ ]]; then
	echo "Expected a PHP minor version such as 8.4, got '${PHP_VERSION}'." >&2
	exit 2
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

echo "=== PHP ${PHP_VERSION} forward-compatibility run ==="

# wp-env reads these overrides ahead of bin/ci/.wp-env.json, so the committed
# parity configuration stays the single source of truth for everything else.
export WP_ENV_PHP_VERSION="${PHP_VERSION}"
export AA_CI_WP_ENV_HOME="${REPO_ROOT}/.wp-env-ci-forward"
export WP_ENV_PORT=9940
export WP_ENV_TESTS_PORT=9941

exec bash "${SCRIPT_DIR}/php.sh"
