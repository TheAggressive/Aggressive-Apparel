#!/usr/bin/env bash
# Design system checks — hex colors, editor chrome literals, and BEM class naming.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Every check below reads from `rg` through process substitution or an `if`.
# When ripgrep is absent those produce no output and no match, the loop bodies
# never run, EXIT stays 0, and the script prints "checks passed" having checked
# nothing. It did exactly that on developer machines AND on GitHub runners —
# neither has ripgrep — so these rules had never once been enforced.
#
# Rather than require a binary that is missing everywhere it matters, translate
# to grep, which is present on both. Fail loudly if even that is unavailable.
if ! command -v rg >/dev/null 2>&1; then
	if ! command -v grep >/dev/null 2>&1; then
		echo "Neither ripgrep nor grep is available; cannot verify the design system." >&2
		exit 1
	fi

	# Accepts the rg flags this script uses and maps them onto grep. grep needs
	# -r explicitly (rg recurses by default) and -E for the extended syntax the
	# patterns assume, unless -P was requested for the PCRE lookbehind.
	rg() {
		local grep_args=(-r) pattern='' paths=() pcre=0

		while (($#)); do
			case "$1" in
				-n) grep_args+=(-n) ;;
				-o) grep_args+=(-o) ;;
				-P)
					grep_args+=(-P)
					pcre=1
					;;
				--no-filename) grep_args+=(-h) ;;
				-*) ;;
				*)
					if [[ -z "${pattern}" ]]; then
						pattern="$1"
					else
						paths+=("$1")
					fi
					;;
			esac
			shift
		done

		((pcre)) || grep_args+=(-E)

		grep "${grep_args[@]}" -- "${pattern}" "${paths[@]}"
	}
fi

BEM_PATTERN='^(aggressive-apparel-[a-z0-9-]+(__[a-z0-9-]+)?(--[a-z0-9-]+)?|aa-[a-z0-9-]+(__[a-z0-9-]+)?(--[a-z0-9-]+)?)$'
HEX_PATTERN='(?<!&)#[0-9a-fA-F]{3,8}'
EDITOR_CHROME_PATTERN="(#757575|rgb\\(117,\\s*117,\\s*117\\)|color: '#(666|999|1e1e1e|cc1818|0066cc|0c4a6e)'|backgroundColor: '#(f0f0f0|f0f8ff|f0f9ff|f5f5f5|f8f9fa|f9f9f9)'|border(Top)?: '1px solid #ddd'|borderRadius: '(4px|8px)')"
REGISTERED_STYLE_PATTERN='^(is-style-(badge|badge-muted|bordered|caption|commerce-cards|commerce-grid|commerce-price|cta|cta-ghost|cta-outline-on-dark|cta-small|cta-small-outline-on-dark|default|eyebrow|ghost|legal|logos-only|meta|outline|outline-on-dark|price|product-frame|small|subtle|surface-card|text|wide))$'
RAW_CTA_PATTERN='padding-(top|right|bottom|left):(1\.2rem|3rem)|font-size:1\.1rem'
INLINE_BUTTON_STYLE_PATTERN='wp-block-button__link[^>]*style='
BUTTON_RECIPE_ATTRIBUTE_PATTERN='<!-- wp:button [^>]*(typography|padding)'
STACKED_BUTTON_STYLE_PATTERN='<!-- wp:button [^>]*className":"[^"]*is-style-[^"]+ is-style-'
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
		src/utils/adaptive-color-picker.tsx \
		src/blocks-interactivity/navigation/edit.tsx \
		src/blocks-interactivity/parallax/edit.tsx \
		src/blocks-interactivity/parallax/components/DirectionPicker.tsx \
		src/blocks-interactivity/parallax/components/EffectPresets.tsx \
		src/blocks-interactivity/parallax/components/EffectTimingControls.tsx \
		src/blocks-interactivity/parallax/components/EffectsControls.tsx \
		src/blocks-interactivity/animate-on-scroll/edit.tsx
)

echo "Checking registered block style names in patterns..."

while IFS= read -r style_name; do
	[[ -z "$style_name" ]] && continue
	if [[ ! "$style_name" =~ $REGISTERED_STYLE_PATTERN ]]; then
		echo "  FAIL: unregistered block style in patterns: $style_name"
		EXIT=1
	fi
done < <(
	rg -o --no-filename 'is-style-[a-z0-9-]+' patterns | sort -u
)

echo "Checking WooCommerce product collection pattern styles..."

while IFS= read -r line; do
	[[ -z "$line" ]] && continue
	if [[ "$line" != *"is-style-commerce-grid"* ]]; then
		echo "  FAIL: WooCommerce product collection missing is-style-commerce-grid"
		echo "  $line"
		EXIT=1
	fi
done < <(
	rg -n 'wp:woocommerce/product-collection \{' patterns
)

echo "Checking high-risk inline CTA recipes in patterns..."

if rg -n "$RAW_CTA_PATTERN" patterns >/dev/null 2>&1; then
	echo "  FAIL: raw CTA sizing found in patterns"
	rg -n "$RAW_CTA_PATTERN" patterns || true
	EXIT=1
fi

echo "Checking button patterns use centralized recipes..."

if rg -n "$INLINE_BUTTON_STYLE_PATTERN" patterns >/dev/null 2>&1; then
	echo "  FAIL: inline button link style found in patterns"
	rg -n "$INLINE_BUTTON_STYLE_PATTERN" patterns || true
	EXIT=1
fi

if rg -n "$BUTTON_RECIPE_ATTRIBUTE_PATTERN" patterns >/dev/null 2>&1; then
	echo "  FAIL: button typography or padding recipe found in pattern attributes"
	rg -n "$BUTTON_RECIPE_ATTRIBUTE_PATTERN" patterns || true
	EXIT=1
fi

if rg -n "$STACKED_BUTTON_STYLE_PATTERN" patterns >/dev/null 2>&1; then
	echo "  FAIL: stacked button block styles found in patterns"
	rg -n "$STACKED_BUTTON_STYLE_PATTERN" patterns || true
	EXIT=1
fi

echo "Checking runtime controls use shared interaction primitives..."

while IFS='|' read -r file control primitive; do
	[[ -z "$file" ]] && continue

	while IFS= read -r line; do
		[[ -z "$line" ]] && continue
		if [[ "$line" != *"$primitive"* ]]; then
			echo "  FAIL: .$control must compose .$primitive in $file"
			echo "  $line"
			EXIT=1
		fi
	done < <(rg -n -P "class=\"[^\"]*$control(?:\\s|\"|$)" "$file" || true)
done <<'CONTROL_CONTRACTS'
includes/Core/class-search-modal.php|aa-search__close|aa-icon-button
includes/WooCommerce/class-product-filters.php|aa-product-filters__close|aa-icon-button
includes/WooCommerce/class-quick-view.php|aggressive-apparel-quick-view__close|aa-icon-button
includes/WooCommerce/class-size-guide.php|aggressive-apparel-size-guide__close|aa-icon-button
includes/WooCommerce/class-sticky-add-to-cart.php|aa-sticky-cart__drawer-close|aa-icon-button
src/blocks/dark-mode-toggle/render.php|dark-mode-toggle__button|aa-icon-button
src/blocks-interactivity/ticker/render.php|ticker__pause|aa-icon-button
includes/Core/class-search-modal.php|aa-search__tab|aa-choice-pill
includes/WooCommerce/class-product-filter-renderer.php|aa-product-filters__size-chip|aa-choice-pill
includes/WooCommerce/class-quick-view.php|aggressive-apparel-quick-view__attribute-option|aa-choice-pill
includes/WooCommerce/class-sticky-add-to-cart.php|aa-sticky-cart__drawer-option|aa-choice-pill
CONTROL_CONTRACTS

echo "Checking BEM class names (aggressive-apparel-* and aa-*)..."

while IFS= read -r class; do
	[[ -z "$class" ]] && continue
	if [[ ! "$class" =~ $BEM_PATTERN ]]; then
		echo "  FAIL: non-BEM class: .$class"
		EXIT=1
	fi
done < <(
	# Extract with [A-Za-z] even though BEM_PATTERN only accepts lowercase. A
	# lowercase-only extraction cannot see `.aa-productCard` at all: the regex
	# simply stops at the capital, so nothing is captured and nothing is tested
	# — the check reported success on precisely the names it exists to reject.
	# Extract broadly, judge narrowly, so case violations reach the pattern.
	rg -o --no-filename '\.(aggressive-apparel-[A-Za-z0-9_-]+|aa-[A-Za-z0-9_-]+)' \
		src/styles/components \
		src/styles/woocommerce \
		src/blocks \
		src/blocks-interactivity \
		| sed 's/^\.//' \
		| sort -u
)

echo "Checking for body-level :has() selectors..."

# body:has() registers a document-wide :has invalidation: EVERY childList
# mutation anywhere (including textContent writes) forces a full-document
# style re-scan (~50ms+). Mirror the state as a body class/attribute from
# JS instead. Scoped subjects (.some-component:has(...)) are fine.
while IFS= read -r -d '' file; do
	if rg -n '(^|[^a-zA-Z0-9_-])body(\.[a-zA-Z0-9_-]+)*:has\(' "$file" >/dev/null 2>&1; then
		echo "  FAIL: body-level :has() found in $file (mirror state as a body class from JS instead)"
		rg -n '(^|[^a-zA-Z0-9_-])body(\.[a-zA-Z0-9_-]+)*:has\(' "$file" || true
		EXIT=1
	fi
done < <(
	find src/styles src/blocks src/blocks-interactivity -name '*.css' -print0
)

if [[ "$EXIT" -eq 0 ]]; then
	echo "Design system CSS checks passed."
fi

exit "$EXIT"
