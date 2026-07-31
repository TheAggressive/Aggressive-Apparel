#!/usr/bin/env bash

# Canonical required-release browser lane with clean, isolated WordPress state.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

cleanup() {
	if ! bash "${SCRIPT_DIR}/stop-wp-env.sh"; then
		echo "Warning: CI parity containers could not be stopped." >&2
	fi
}
trap cleanup EXIT

bash "${SCRIPT_DIR}/reset-wp-env.sh"

cd "${REPO_ROOT}"
CI=1 \
	WP_BASE_URL=http://localhost:9930 \
	WP_ENV_CONFIG_DIR=bin/ci \
	playwright test
