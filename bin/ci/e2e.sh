#!/usr/bin/env bash

# Canonical required-release browser lane with clean, isolated WordPress state.
#
# Runs natively, without Docker: the pinned WordPress and WooCommerce from
# bin/ci/.wp-env.json, served by PHP's built-in server. The database is the one
# named by AA_E2E_DB_HOST when the caller provides it (the Actions service
# container); otherwise a disposable native mysqld under .cache/ci/e2e. Every run
# starts from an empty database. This never addresses WordPress Studio.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd -P)"
cd "${REPO_ROOT}"

E2E_ROOT="${REPO_ROOT}/.cache/ci/e2e"
WP_DIR="${E2E_ROOT}/wordpress"
WP_CLI="${E2E_ROOT}/wp"
PORT="${AA_E2E_PORT:-9950}"
BASE_URL="http://127.0.0.1:${PORT}"
DB_NAME="${AA_E2E_DB_NAME:-aa_e2e}"
DB_USER="${AA_E2E_DB_USER:-wordpress}"
DB_PASSWORD="${AA_E2E_DB_PASSWORD:-wordpress}"
FIXTURE="${REPO_ROOT}/bin/wp-env/mu-plugins/e2e-product-tabs-style.php"

server_pid=""
stop_mysql=0

# The native mysqld is addressed through bin/local/mysql.sh, on its own data
# directory and port so it never collides with the PHPUnit database.
local_mysql() {
	AA_TESTS_MYSQL_DIR=.cache/ci/e2e/mysql \
		AA_TESTS_DB_PORT="${AA_E2E_DB_PORT:-13317}" \
		AA_TESTS_DB_NAME="${DB_NAME}" \
		AA_TESTS_DB_USER="${DB_USER}" \
		AA_TESTS_DB_PASSWORD="${DB_PASSWORD}" \
		bash bin/local/mysql.sh "$@"
}

cleanup() {
	local status=$? log
	# A crashed PHP worker surfaces in Playwright only as ERR_EMPTY_RESPONSE,
	# so print the server's own evidence whenever the run fails.
	if (( status != 0 )) && [[ -n "${server_pid}" ]]; then
		if ! kill -0 "${server_pid}" 2>/dev/null; then
			echo "e2e: the PHP server had exited before cleanup." >&2
		fi
		for log in "${E2E_ROOT}/server.log" "${WP_DIR}/wp-content/debug.log"; do
			[[ -s "${log}" ]] || continue
			echo "::group::e2e: tail of ${log##*/}" >&2
			tail -n 80 "${log}" >&2 || true
			echo "::endgroup::" >&2
		done
	fi
	if [[ -n "${server_pid}" ]]; then
		kill "${server_pid}" 2>/dev/null || true
		wait "${server_pid}" 2>/dev/null || true
	fi
	if (( stop_mysql )) && ! local_mysql stop; then
		echo "Warning: the E2E MySQL server could not be stopped." >&2
	fi
}
trap cleanup EXIT

if [[ -n "${AA_E2E_DB_HOST:-}" ]]; then
	db_host="${AA_E2E_DB_HOST}"
else
	# Only stop a server this run started; leave an already-running one alone.
	local_mysql status >/dev/null 2>&1 || stop_mysql=1
	local_mysql start
	db_host="127.0.0.1:${AA_E2E_DB_PORT:-13317}"
fi

AA_TESTS_WP_DIR="${WP_DIR}" AA_TESTS_WP_SKIP_CONTENT=0 bash bin/local/wp-core.sh

# A stable executable the Playwright fixtures call (WP_CLI_RUNNER=native), with
# PHP notices on stderr so they never corrupt the JSON the fixtures parse.
cat > "${WP_CLI}" <<-SH
	#!/usr/bin/env bash
	exec php -d display_errors=stderr -d memory_limit=512M \\
		-d error_reporting='E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED' \\
		"${REPO_ROOT}/.cache/ci/wp" --path="${WP_DIR}" "\$@"
SH
chmod +x "${WP_CLI}"

"${WP_CLI}" config create \
	--dbname="${DB_NAME}" \
	--dbuser="${DB_USER}" \
	--dbpass="${DB_PASSWORD}" \
	--dbhost="${db_host}" \
	--skip-check \
	--force

# Same wp-config constants as the release gate environment.
while IFS=$'\t' read -r name value; do
	"${WP_CLI}" config set "${name}" "${value}" --raw
done < <(
	# shellcheck disable=SC2016 # A JavaScript template literal, not shell.
	node -e '
		const { config } = JSON.parse(require("fs").readFileSync("bin/ci/.wp-env.json"));
		for (const [name, value] of Object.entries(config)) {
			console.log(`${name}\t${JSON.stringify(value)}`);
		}
	'
)

"${WP_CLI}" db reset --yes
"${WP_CLI}" core install \
	--url="${BASE_URL}" \
	--title='Aggressive Apparel E2E' \
	--admin_user=admin \
	--admin_password=password \
	--admin_email=admin@example.test \
	--skip-email
# core install derives siteurl from wp_guess_url(), which misreads a site nested
# inside this checkout (its path already contains wp-content) as a subdirectory.
"${WP_CLI}" option update siteurl "${BASE_URL}"
"${WP_CLI}" option update home "${BASE_URL}"
"${WP_CLI}" plugin activate woocommerce
"${WP_CLI}" theme activate aggressive-apparel
"${WP_CLI}" rewrite structure '/%postname%/'

mkdir -p "${WP_DIR}/wp-content/mu-plugins"
ln -sfn "${FIXTURE}" "${WP_DIR}/wp-content/mu-plugins/aa-e2e-product-tabs-style.php"

# Several workers so WP-Cron and WooCommerce loopback requests cannot deadlock
# the single-threaded default server while a page request waits on them.
PHP_CLI_SERVER_WORKERS=4 php -S "127.0.0.1:${PORT}" -t "${WP_DIR}" \
	> "${E2E_ROOT}/server.log" 2>&1 &
server_pid=$!

for attempt in {1..30}; do
	curl --fail --silent --output /dev/null "${BASE_URL}/wp-login.php" && break
	if (( attempt == 30 )) || ! kill -0 "${server_pid}" 2>/dev/null; then
		echo "The E2E WordPress server did not become ready." >&2
		tail -n 20 "${E2E_ROOT}/server.log" >&2 || true
		exit 1
	fi
	sleep 1
done

echo "e2e: PHP $(php -r 'echo PHP_VERSION;') serving ${BASE_URL}"

CI=1 \
	WP_BASE_URL="${BASE_URL}" \
	WP_CLI_RUNNER=native \
	AA_E2E_WP_CLI="${WP_CLI}" \
	playwright test "$@"
