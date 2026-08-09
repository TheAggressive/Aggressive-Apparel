#!/usr/bin/env bash
# Fail the build when the theme loads an asset from a third-party host.
#
# A remote stylesheet, script or font is a hard dependency on someone else's
# uptime: an offline or firewalled site hangs on it behind a DNS timeout, and
# every page load discloses the visitor's IP and user-agent to that host. This
# theme deliberately bundles no fonts and loads none remotely — Space Grotesk
# and Bebas Neue are declared in theme.json without a fontFace precisely so the
# browser falls back instead of fetching.
#
# A Google Fonts stylesheet reached the badge admin screens once and no gate
# noticed, because every other check looks at syntax rather than at what the
# rendered page will request.
#
# Scope: enqueue/register calls in PHP, and url()/@import in CSS. An SVG xmlns
# is not matched — a namespace URI is an identifier, never fetched.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

status=0

report() {
	printf '  %s\n' "$1"
	status=1
}

echo "Checking for externally-hosted assets..."

while IFS= read -r hit; do
	[ -z "${hit}" ] && continue
	report "${hit}"
done < <(
	grep -rnE "wp_(enqueue|register)_(style|script)[^;]*https?://" \
		--include='*.php' includes/ src/ 2>/dev/null | grep -v '^\s*\*' || true
)

while IFS= read -r hit; do
	[ -z "${hit}" ] && continue
	report "${hit}"
done < <(
	grep -rnE "url\(\s*['\"]?https?://" --include='*.css' src/ 2>/dev/null || true
)

while IFS= read -r hit; do
	[ -z "${hit}" ] && continue
	report "${hit}"
done < <(
	grep -rnE "@import\s+['\"]https?://" --include='*.css' src/ 2>/dev/null || true
)

if [ "${status}" -ne 0 ]; then
	cat <<'EOF'

Externally-hosted assets found (listed above).

Self-host the asset under src/ so it ships with the theme, or drop it and let
the browser fall back. If a URL above is genuinely never fetched (an SVG xmlns,
a documentation link), move it out of the enqueue call or the url() token.
EOF
	exit 1
fi

echo "External-asset check passed (no third-party hosts)."
