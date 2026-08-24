#!/usr/bin/env bash
# Install a checksum-verified ShellCheck binary into the repository cache.

set -euo pipefail

readonly SHELLCHECK_VERSION='0.11.0'
readonly SHELLCHECK_SHA256='8c3be12b05d5c177a04c29e3c78ce89ac86f1595681cab149b65b97c4e227198'
readonly SHELLCHECK_ASSET="shellcheck-v${SHELLCHECK_VERSION}.linux.x86_64.tar.xz"
readonly SHELLCHECK_URL="https://github.com/koalaman/shellcheck/releases/download/v${SHELLCHECK_VERSION}/${SHELLCHECK_ASSET}"
readonly INSTALL_ROOT="${AA_SHELLCHECK_ROOT:-.cache/ci/shellcheck-${SHELLCHECK_VERSION}}"
readonly INSTALL_PATH="${INSTALL_ROOT}/shellcheck"
readonly ARCHIVE_PATH="${INSTALL_ROOT}/${SHELLCHECK_ASSET}"

if [[ "$(uname -m)" != "x86_64" ]]; then
	echo "ShellCheck bootstrap supports x86_64 only (found $(uname -m))." >&2
	exit 1
fi

mkdir -p "${INSTALL_ROOT}"

archive_valid=0
if [[ -f "${ARCHIVE_PATH}" ]]; then
	actual_sha256="$(sha256sum "${ARCHIVE_PATH}" | awk '{print $1}')"
	[[ "${actual_sha256}" == "${SHELLCHECK_SHA256}" ]] && archive_valid=1
fi

if [[ "${archive_valid}" -ne 1 ]]; then
	echo "Downloading ShellCheck ${SHELLCHECK_VERSION}…" >&2
	curl --fail --silent --show-error --location --retry 3 --retry-all-errors \
		--output "${ARCHIVE_PATH}.tmp" "${SHELLCHECK_URL}"
	actual_sha256="$(sha256sum "${ARCHIVE_PATH}.tmp" | awk '{print $1}')"
	if [[ "${actual_sha256}" != "${SHELLCHECK_SHA256}" ]]; then
		rm -f "${ARCHIVE_PATH}.tmp"
		echo "ShellCheck checksum verification failed." >&2
		echo "Expected: ${SHELLCHECK_SHA256}" >&2
		echo "Actual:   ${actual_sha256}" >&2
		exit 1
	fi
	mv "${ARCHIVE_PATH}.tmp" "${ARCHIVE_PATH}"
fi

if [[ ! -x "${INSTALL_PATH}" ]]; then
	staging="${INSTALL_ROOT}/extract"
	rm -rf "${staging}"
	mkdir -p "${staging}"
	tar -xJf "${ARCHIVE_PATH}" -C "${staging}"
	install -m 0755 \
		"${staging}/shellcheck-v${SHELLCHECK_VERSION}/shellcheck" \
		"${INSTALL_PATH}"
	rm -rf "${staging}"
fi

reported_version="$("${INSTALL_PATH}" --version | awk '/^version:/ { print $2 }')"
if [[ "${reported_version}" != "${SHELLCHECK_VERSION}" ]]; then
	echo "Cached ShellCheck reports ${reported_version}, expected ${SHELLCHECK_VERSION}." >&2
	exit 1
fi

printf '%s\n' "${INSTALL_PATH}"
