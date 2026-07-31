#!/usr/bin/env bash

# Reset only the isolated CI parity databases and reapply their pinned config.
# This never addresses the development wp-env home that contains local uploads
# and database content.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
install_path="$(bash "${SCRIPT_DIR}/wp-env.sh" install-path)"

if [[ -f "${install_path}/docker-compose.yml" ]]; then
	bash "${SCRIPT_DIR}/wp-env.sh" start
	bash "${SCRIPT_DIR}/wp-env.sh" clean all --no-scripts
else
	bash "${SCRIPT_DIR}/wp-env.sh" start
fi

bash "${SCRIPT_DIR}/wp-env.sh" start --update
bash "${SCRIPT_DIR}/wp-env.sh" run cli wp --info
