#!/usr/bin/env bash
# Design system checks — hex colors, editor chrome literals, and BEM class naming.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BEM_PATTERN='^(aggressive-apparel-[a-z0-9-]+(__[a-z0-9-]+)?(--[a-z0-9-]+)?|aa-[a-z0-9-]+(__[a-z0-9-]+)?(--[a-z0-9-]+)?)$'
HEX_PATTERN='(?<!&)#[0-9a-fA-F]{3,8}'
EDITOR_CHROME_PATTERN="(#757575|rgb\\(117,\\s*117,\\s*117\\)|color: '#(666|999|1e1e1e|cc1818|0066cc|0c4a6e)'|backgroundColor: '#(f0f0f0|f0f8ff|f0f9ff|f5f5f5|f8f9fa|f9f9f9)'|border(Top)?: '1px solid #ddd'|borderRadius: '(4px|8px)')"
EXIT=0

echo "Checking for hardcoded hex colors in feature CSS..."

while IFS= read -r -d '' file; do
	if rg -n -P "$HEX_PATTERN" "$file" >/dev/null 2>&1; then
		echo "  FAIL: hex color found in $file"
		rg -n -P "$HEX_PATTERN" "$file" || true
		EXIT=1
	fi
done < <(
	find src/styles/woocommerce src/styles/components -name '*.css' -print0
	find src/blocks \( -name 'style.css' -o -name 'editor.css' \) -print0
	find src/blocks-interactivity \( -name 'style.css' -o -name 'editor.css' \) -print0
	find patterns -name '*.php' -print0
)

echo "Checking for hardcoded editor UI chrome..."

while IFS= read -r -d '' file; do
	if rg -n "$EDITOR_CHROME_PATTERN" "$file" >/dev/null 2>&1; then
		echo "  FAIL: editor UI chrome literal found in $file"
		rg -n "$EDITOR_CHROME_PATTERN" "$file" || true
		EXIT=1
	fi
done < <(
	printf '%s\0' \
		src/scripts/editor/adaptive-colors.tsx \
		src/blocks-interactivity/navigation/edit.tsx \
		src/blocks-interactivity/parallax/edit.tsx \
		src/blocks-interactivity/parallax/components/DirectionPicker.tsx \
		src/blocks-interactivity/parallax/components/EffectPresets.tsx \
		src/blocks-interactivity/parallax/components/EffectTimingControls.tsx \
		src/blocks-interactivity/parallax/components/EffectsControls.tsx \
		src/blocks-interactivity/animate-on-scroll/edit.tsx
)

echo "Checking BEM class names (aggressive-apparel-* and aa-*)..."

while IFS= read -r class; do
	[[ -z "$class" ]] && continue
	if [[ ! "$class" =~ $BEM_PATTERN ]]; then
		echo "  FAIL: non-BEM class: .$class"
		EXIT=1
	fi
done < <(
	rg -o --no-filename '\.(aggressive-apparel-[a-z0-9_-]+|aa-[a-z0-9_-]+)' \
		src/styles/components \
		src/styles/woocommerce \
		src/blocks \
		src/blocks-interactivity \
		| sed 's/^\.//' \
		| sort -u
)

if [[ "$EXIT" -eq 0 ]]; then
	echo "Design system CSS checks passed."
fi

exit "$EXIT"
