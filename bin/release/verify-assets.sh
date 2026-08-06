#!/usr/bin/env bash

# Reconcile a semantic-release draft with the locally accepted artifacts, verify
# the bytes through GitHub's download API, then publish it. semantic-release is
# configured with draftRelease=true, so the updater cannot observe a partial
# release while this script is repairing or validating it.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="${AA_RELEASE_ROOT:-$(cd "${SCRIPT_DIR}/../.." && pwd)}"
SLUG="aggressive-apparel"
VERSION="${AA_RELEASE_VERSION:?AA_RELEASE_VERSION is required}"
REPOSITORY="${GITHUB_REPOSITORY:?GITHUB_REPOSITORY is required}"

if [[ "${REPO_ROOT}" != /* || ! -d "${REPO_ROOT}" ]]; then
	echo "AA_RELEASE_ROOT must resolve to an absolute directory." >&2
	exit 2
fi

if [[ ! "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$ ]]; then
	echo "Invalid release version '${VERSION}'." >&2
	exit 2
fi
if [[ ! "${REPOSITORY}" =~ ^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]]; then
	echo "Invalid GITHUB_REPOSITORY '${REPOSITORY}'." >&2
	exit 2
fi

cd "${REPO_ROOT}"
ZIP="${SLUG}-${VERSION}.zip"
CHECKSUM="${ZIP}.sha256"
TAG="v${VERSION}"

for asset in "${ZIP}" "${CHECKSUM}"; do
	if [[ ! -f "${asset}" ]]; then
		echo "Expected local release asset '${asset}' is missing." >&2
		exit 1
	fi
done
sha256sum --check "${CHECKSUM}"
bash "${SCRIPT_DIR}/verify-package.sh" "${ZIP}" "${VERSION}"

release_rows="$(
	gh api --paginate "repos/${REPOSITORY}/releases?per_page=100" \
		--jq ".[] | select(.tag_name == \"${TAG}\") | [.id, .draft] | @tsv"
)"
row_count="$(grep -c . <<<"${release_rows}" || true)"
if [[ "${row_count}" -ne 1 ]]; then
	echo "Expected exactly one GitHub release for ${TAG}, found ${row_count}." >&2
	exit 1
fi
read -r release_id release_is_draft <<<"${release_rows}"
if [[ ! "${release_id}" =~ ^[0-9]+$ ]]; then
	echo "GitHub returned an invalid release id for ${TAG}." >&2
	exit 1
fi
if [[ "${release_is_draft}" != "true" ]]; then
	echo "Release ${TAG} is already published; refusing to modify its assets." >&2
	exit 1
fi

list_assets() {
	gh api "repos/${REPOSITORY}/releases/${release_id}/assets" \
		--jq '.[] | [.id, .name] | @tsv'
}

asset_id() {
	local asset_name="$1"
	list_assets | awk -F '\t' -v name="${asset_name}" '$2 == name { print $1 }'
}

upload_asset() {
	local asset_name="$1"
	echo "Uploading ${asset_name} to ${TAG} draft..."
	gh release upload "${TAG}" "${asset_name}" --repo "${REPOSITORY}"
}

DOWNLOAD_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/aa-release-download.XXXXXX")"
cleanup() {
	rm -rf "${DOWNLOAD_ROOT}"
}
trap cleanup EXIT

for asset in "${ZIP}" "${CHECKSUM}"; do
	remote_id="$(asset_id "${asset}")"
	if [[ -z "${remote_id}" ]]; then
		upload_asset "${asset}"
		remote_id="$(asset_id "${asset}")"
	fi
	if [[ ! "${remote_id}" =~ ^[0-9]+$ ]]; then
		echo "Could not resolve remote asset id for ${asset}." >&2
		exit 1
	fi

	gh api -H 'Accept: application/octet-stream' \
		"repos/${REPOSITORY}/releases/assets/${remote_id}" \
		>"${DOWNLOAD_ROOT}/${asset}"

	# A matching name is not evidence that the upload completed with the right
	# bytes. Replace a corrupt/truncated remote object and verify the replacement.
	if ! cmp -s "${asset}" "${DOWNLOAD_ROOT}/${asset}"; then
		echo "Remote ${asset} differs from the accepted artifact; replacing it."
		gh api --method DELETE \
			"repos/${REPOSITORY}/releases/assets/${remote_id}" >/dev/null
		upload_asset "${asset}"
		remote_id="$(asset_id "${asset}")"
		gh api -H 'Accept: application/octet-stream' \
			"repos/${REPOSITORY}/releases/assets/${remote_id}" \
			>"${DOWNLOAD_ROOT}/${asset}"
		cmp "${asset}" "${DOWNLOAD_ROOT}/${asset}"
	fi
done

(
	cd "${DOWNLOAD_ROOT}"
	sha256sum --check "${CHECKSUM}"
)
bash "${SCRIPT_DIR}/verify-package.sh" "${DOWNLOAD_ROOT}/${ZIP}" "${VERSION}"
gh attestation verify "${DOWNLOAD_ROOT}/${ZIP}" --repo "${REPOSITORY}"

gh api --method PATCH "repos/${REPOSITORY}/releases/${release_id}" \
	-F draft=false -F make_latest=true >/dev/null

published_draft_state="$(
	gh api "repos/${REPOSITORY}/releases/${release_id}" --jq '.draft'
)"
if [[ "${published_draft_state}" != "false" ]]; then
	echo "Release ${TAG} is still a draft after promotion." >&2
	exit 1
fi

echo "Release ${TAG} is remotely verified, attested and published."
