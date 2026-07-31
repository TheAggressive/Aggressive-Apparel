#!/usr/bin/env bash
#
# Single source of truth for what ships inside the distributable theme ZIP.
#
# The package is built from an ALLOWLIST, not by deleting development files out
# of a checkout. A blocklist fails open: every new top-level path in the
# repository ships by default, and one typo'd `rm` silently drops a required
# file while CI stays green. An allowlist fails closed — a new shipping path has
# to be added here deliberately, and bin/release/verify-package.sh re-asserts
# the built artifact against the same lists.
#
# Sourced by bin/release/package.sh and bin/release/verify-package.sh. Both read
# these arrays, so the build and its verification can never disagree.

AA_THEME_SLUG='aggressive-apparel'

# Top-level repository paths copied into the package. Anything not listed here
# does not ship. Keep alphabetised within each group.
AA_PACKAGE_INCLUDE=(
	'CHANGELOG.md'
	'README.md'
	'functions.php'
	'index.php'
	'screenshot.png'
	'style.css'
	'theme.json'
	'build'
	'includes'
	'languages'
	'parts'
	'patterns'
	'styles'
	'templates'
)

# Paths that MUST exist inside the built ZIP, relative to the theme directory.
# These are load-bearing: WordPress refuses to recognise the theme without the
# headers and index.php, functions.php bootstraps the autoloader, the block
# manifest drives registration, and the FSE templates/parts are the theme.
# A partially-populated build/ artifact or an over-eager clean step is caught
# here rather than by a customer whose site went blank after auto-updating.
AA_PACKAGE_REQUIRED=(
	'style.css'
	'index.php'
	'functions.php'
	'theme.json'
	'screenshot.png'
	'includes/class-autoloader.php'
	'includes/class-bootstrap.php'
	'includes/class-service-container.php'
	'includes/helpers.php'
	'build/blocks-manifest.php'
	'build/blocks/copyright/legal-entity-presets.json'
	'templates/index.html'
	'templates/single-product.html'
	'templates/archive-product.html'
	'parts/header.html'
	'parts/footer.html'
	'styles/section-surface.json'
	'languages/aggressive-apparel.pot'
)

# Paths pruned from the staged copy after the allowlist is applied. These live
# inside otherwise-shipping directories, so the allowlist alone cannot exclude
# them. Relative to the theme root; directories are removed recursively.
#
# build/scripts/__tests__ is compiled Jest output that webpack emits alongside
# the real bundles. The previous blocklist packaging shipped it, because
# `rm -rf tests` only ever matched the repository-root suite.
AA_PACKAGE_PRUNE=(
	'build/scripts/__tests__'
	'languages/README.md'
)

# Directories that must contain at least one entry inside the ZIP. Guards the
# case where a directory ships but its contents did not.
AA_PACKAGE_REQUIRED_NONEMPTY=(
	'build/blocks'
	'build/blocks-interactivity'
	'build/interactivity'
	'build/styles'
	'includes/Core'
	'includes/WooCommerce'
	'patterns'
)

# Extended regular expressions matched against every ZIP entry. Any hit fails
# the build. This is defence in depth behind the allowlist: it catches a path
# that slipped in through an included directory (for example a stray test
# fixture or a build artifact carrying a source map).
AA_PACKAGE_FORBIDDEN=(
	'(^|/)node_modules/'
	'(^|/)vendor/'
	'(^|/)tests?/'
	'(^|/)__tests__/'
	'(^|/)docs/'
	'(^|/)stubs/'
	'(^|/)types/'
	'(^|/)src/'
	'(^|/)bin/'
	'(^|/)\.git'
	'(^|/)\.husky/'
	'(^|/)\.vscode/'
	'(^|/)\.github/'
	'(^|/)\.wp-env'
	'(^|/)\.cache/'
	'(^|/)\.env'
	'(^|/)CLAUDE\.md$'
	'(^|/)package\.json$'
	'(^|/)composer\.(json|lock)$'
	'(^|/)pnpm-lock\.yaml$'
	'(^|/)pnpm-workspace\.yaml$'
	'(^|/)playwright\.config\.ts$'
	'(^|/)lighthouserc\.cjs$'
	'(^|/)jest\.config\.js$'
	'(^|/)phpunit\.xml'
	'(^|/)phpstan\.neon'
	'(^|/)phpcs\.xml'
	'(^|/)webpack\..*\.mjs$'
	'(^|/)tsconfig.*\.json$'
	'(^|/)eslint\.config\.js$'
	'(^|/)commitlint\.config\.mjs$'
	'(^|/)postcss\.config\.cjs$'
	'(^|/)coverage[^/]*$'
	'(^|/)test-results/'
	'\.map$'
	'\.DS_Store$'
	'(^|/)Thumbs\.db$'
	# templates/page.html is a required FSE template. Only a stray root-level
	# page.html is forbidden, so this stays anchored to the theme root.
	"^${AA_THEME_SLUG}/page\\.html$"
)

# Resolve the repository root from any script under bin/release/.
aa_release_repo_root() {
	local script_dir
	script_dir="$(cd "$(dirname "${BASH_SOURCE[1]}")" && pwd)"
	(cd "${script_dir}/../.." && pwd)
}

# Read the theme version from the style.css header.
aa_release_style_version() {
	local style_css="$1"
	sed -n 's/^Version:[[:space:]]*\([0-9][^[:space:]]*\).*$/\1/p' "${style_css}" | head -n 1
}
