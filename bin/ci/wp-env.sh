#!/usr/bin/env bash

# Run the required-release wp-env from its committed configuration. Its
# storage, ports, database, and wp-content are isolated from local development.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
WP_ENV_EXECUTABLE="${REPO_ROOT}/node_modules/.bin/wp-env"
PARITY_HOME="${AA_CI_WP_ENV_HOME:-${REPO_ROOT}/.wp-env-ci}"

if [[ "${PARITY_HOME}" != /* ]]; then
	echo "AA_CI_WP_ENV_HOME must be an absolute path." >&2
	exit 2
fi

case "${PARITY_HOME}" in
	/ | "${REPO_ROOT}" | "${HOME:-/nonexistent}")
		echo "AA_CI_WP_ENV_HOME must be a dedicated CI parity directory." >&2
		exit 2
		;;
esac

if [[ ! -x "${WP_ENV_EXECUTABLE}" ]]; then
	echo "wp-env is not installed. Run: pnpm install --frozen-lockfile" >&2
	exit 1
fi

export WP_ENV_HOME="${PARITY_HOME}"
export WP_ENV_SKIP_BETA_UPDATE=1
export CI=true

cd "${SCRIPT_DIR}"
exec "${WP_ENV_EXECUTABLE}" "$@"
