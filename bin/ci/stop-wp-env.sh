#!/usr/bin/env bash

# Stop the dedicated parity containers without deleting Docker-managed files.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
install_path="$(bash "${SCRIPT_DIR}/wp-env.sh" install-path)"

if [[ -f "${install_path}/docker-compose.yml" ]]; then
	bash "${SCRIPT_DIR}/wp-env.sh" stop
fi
