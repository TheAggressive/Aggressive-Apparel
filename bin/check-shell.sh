#!/usr/bin/env bash
# ShellCheck over every shell script in bin/.
#
# The release pipeline is written in bash: it builds, verifies, packages, and
# publishes. PHP gets PHPStan level 8 with no baseline and TypeScript gets
# ESLint with --max-warnings 0, while the language that actually ships releases
# had no static analysis at all — even though 21 of these files already carried
# `# shellcheck` directives written for a tool that never ran.
#
# It earned its place on the first run: a comment inside a single-quoted
# `bash -c` body used a typographic apostrophe deliberately, and "correcting" it
# to ASCII silently terminated the string. `bash -n` still parsed the file.
# ShellCheck caught it.
#
# No baseline and no allowlist: findings are either fixed or suppressed inline
# with a written reason, so every suppression is reviewable.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Pinned by digest so local runs and CI analyse with byte-identical rules — a
# newer ShellCheck adding a rule must be an explicit bump, not a surprise red
# build on an unrelated change.
readonly SHELLCHECK_IMAGE='koalaman/shellcheck@sha256:bb596a0d169b85ddd81d8b6d3a2ff6d5baf5fca10b97f575ebc647c3dff62b3d'

# -x follows sourced files; -P SCRIPTDIR resolves `# shellcheck source=`
# relative to the script being checked rather than the working directory,
# without which every `source lib.sh` reports SC1091 and nothing is followed.
SHELLCHECK_ARGS=(-x -P SCRIPTDIR -f gcc)

# Overridable only so the "found nothing" guard below is reachable in tests. A
# typo in the find expression would otherwise scan zero files and pass.
SCAN_DIR="${AA_SHELL_SCAN_DIR:-bin}"

find_scripts() {
	{
		find "${SCAN_DIR}" -type f -name '*.sh'

		# The extension is not what makes a file a shell script. bin/ci/wp and
		# bin/ci/composer are bash with no suffix, and a `*.sh` glob silently
		# skipped both — the same "checked nothing, reported success" shape this
		# lane exists to prevent. Classify anything extensionless by its shebang.
		find "${SCAN_DIR}" -type f ! -name '*.*' -print0 |
			while IFS= read -r -d '' file; do
				case "$(head -n 1 "${file}" 2> /dev/null)" in
					'#!'*sh | '#!'*sh\ *) printf '%s\n' "${file}" ;;
				esac
			done
	} | sort -u
}

script_count="$(find_scripts | wc -l | tr -d ' ')"

if [[ "${script_count}" -eq 0 ]]; then
	echo "No shell scripts found under bin/ — refusing to report success." >&2
	exit 1
fi

echo "Running ShellCheck over ${script_count} shell scripts..."

# The pinned image is preferred over an installed ShellCheck, not the other way
# round: whichever runs first is the one the pin actually governs. Preferring an
# ambient binary would mean the digest pinned nothing on any machine that has
# ShellCheck — including GitHub runners — and a runner-image bump would produce
# exactly the surprise red build the pin is meant to prevent.
if [[ "${AA_SHELLCHECK_NATIVE:-0}" == "1" ]]; then
	shellcheck_bin="$(bash bin/ci/install-shellcheck.sh)"
	echo "Using repository-pinned ShellCheck ($("${shellcheck_bin}" --version |
		awk '/^version:/ { print $2 }')) for the Docker-free local lane." >&2
	find_scripts | xargs "${shellcheck_bin}" "${SHELLCHECK_ARGS[@]}"
elif command -v docker > /dev/null 2>&1 && docker info > /dev/null 2>&1; then
	find_scripts | xargs docker run --rm -i \
		-v "${ROOT}:/mnt" -w /mnt "${SHELLCHECK_IMAGE}" "${SHELLCHECK_ARGS[@]}"
elif command -v shellcheck > /dev/null 2>&1; then
	echo "Note: using the installed ShellCheck ($(shellcheck --version |
		awk '/^version:/ { print $2 }')), not the pinned image." >&2
	echo "Findings may differ from CI. Start Docker for the pinned analysis." >&2
	find_scripts | xargs shellcheck "${SHELLCHECK_ARGS[@]}"
else
	# Fail closed. A missing linter must never read as a clean tree.
	echo "ShellCheck is unavailable and Docker cannot provide it." >&2
	echo "Install ShellCheck (apt install shellcheck / brew install shellcheck)" >&2
	echo "or start Docker, then re-run." >&2
	exit 1
fi

echo "ShellCheck passed (${script_count} scripts, no findings)."
