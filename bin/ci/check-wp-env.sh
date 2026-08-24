#!/usr/bin/env bash
# Health check for the isolated CI wp-env. Local development uses Studio.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

bash "${SCRIPT_DIR}/wp-env.sh" run cli \
	--env-cwd=wp-content/themes/aggressive-apparel \
	-- bash bin/wp-env/check.sh --container
