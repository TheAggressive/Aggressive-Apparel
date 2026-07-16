#!/usr/bin/env bash
# Forward all args to PHPUnit inside wp-env (pnpm test:any -- …).
set -euo pipefail
export XDEBUG_MODE=off
exec ./vendor/bin/phpunit "$@"
