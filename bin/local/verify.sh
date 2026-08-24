#!/usr/bin/env bash
# Docker-free local gate. Containerized release parity remains available as qa:ci.

set -euo pipefail

cd "$(dirname "$0")/../.."

bash bin/local/verify-fast.sh
pnpm run i18n:check
pnpm run test:integration
pnpm run test:security
pnpm run test:accessibility
pnpm run test:performance
pnpm run test:e2e

echo "Local QA passed without Docker."
echo "Run pnpm qa:ci only when containerized release parity is explicitly needed."
