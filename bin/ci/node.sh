#!/usr/bin/env bash

# Provision the exact Actions Node runtime in the ignored repository cache and
# use it to launch a pnpm command. CI already has the pinned runtime, so this is
# a no-op there; local machines do not need a global Node version switch.

set -euo pipefail

readonly NODE_VERSION='24.18.0'
readonly NODE_SHA256='55aa7153f9d88f28d765fcdad5ae6945b5c0f98a36881703817e4c450fa76742'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
CACHE_ROOT="${REPO_ROOT}/.cache/ci"
ARCHIVE_NAME="node-v${NODE_VERSION}-linux-x64.tar.xz"
INSTALL_ROOT="${CACHE_ROOT}/node-v${NODE_VERSION}-linux-x64"
NODE_BINARY="${INSTALL_ROOT}/bin/node"

if [[ "$(uname -s)" != "Linux" || "$(uname -m)" != "x86_64" ]]; then
	echo "Automated CI parity currently supports Linux x86_64 only." >&2
	echo "Use Node ${NODE_VERSION} from .node-version on another platform." >&2
	exit 2
fi

if [[ ! -x "${NODE_BINARY}" ]]; then
	mkdir -p "${CACHE_ROOT}"
	archive_path="${CACHE_ROOT}/${ARCHIVE_NAME}"

	curl \
		--fail \
		--silent \
		--show-error \
		--location \
		--retry 3 \
		--retry-all-errors \
		--output "${archive_path}" \
		"https://nodejs.org/dist/v${NODE_VERSION}/${ARCHIVE_NAME}"

	actual_sha256="$(sha256sum "${archive_path}" | awk '{print $1}')"
	if [[ "${actual_sha256}" != "${NODE_SHA256}" ]]; then
		echo "Node ${NODE_VERSION} checksum verification failed." >&2
		echo "Expected: ${NODE_SHA256}" >&2
		echo "Actual:   ${actual_sha256}" >&2
		exit 1
	fi

	tar -xJf "${archive_path}" -C "${CACHE_ROOT}"
fi

if [[ "$("${NODE_BINARY}" --version)" != "v${NODE_VERSION}" ]]; then
	echo "Cached Node runtime does not match v${NODE_VERSION}." >&2
	exit 1
fi

export PATH="${INSTALL_ROOT}/bin:${PATH}"
cd "${REPO_ROOT}"
exec pnpm "$@"
