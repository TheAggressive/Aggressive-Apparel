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

# wp-env decides whether to pass docker's -T (disable TTY) from
# `process.stdout.isTTY` — not stdin. A git hook inherits the terminal on stdout
# but receives git's ref list on stdin, so wp-env asks docker for a TTY it
# cannot attach and every `wp-env run` dies with:
#
#   cannot attach stdin to a TTY-enabled container because stdin is not a terminal
#
# It never reproduces in CI or from a piped shell, because there stdout is not a
# terminal either and wp-env adds -T by itself. So when stdout is a terminal but
# stdin is not, route stdout through a pipe to trigger that same behaviour.
# Piping (rather than reattaching stdin to /dev/tty) also works where there is
# no controlling terminal at all, such as a GUI git client.
if [ -t 1 ] && [ ! -t 0 ]; then
	set -o pipefail
	"${WP_ENV_EXECUTABLE}" "$@" | cat
	exit $?
fi

exec "${WP_ENV_EXECUTABLE}" "$@"
