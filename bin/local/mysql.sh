#!/usr/bin/env bash
# Disposable native MySQL for PHPUnit. Never touches Studio's SQLite database.

set -euo pipefail

cd "$(dirname "$0")/../.."

DATA_ROOT="${AA_TESTS_MYSQL_DIR:-.cache/local/mysql}"
DATA_DIR="${DATA_ROOT}/data"
PID_FILE="${DATA_ROOT}/mysqld.pid"
LOG_FILE="${DATA_ROOT}/error.log"
PORT="${AA_TESTS_DB_PORT:-13316}"
SOCKET="/tmp/aa-mysqld-$(id -u)-${PORT}.sock"
DB_NAME="${AA_TESTS_DB_NAME:-wordpress_test}"
DB_USER="${AA_TESTS_DB_USER:-wordpress}"
DB_PASSWORD="${AA_TESTS_DB_PASSWORD:-wordpress}"

find_mysqld() {
	local candidate
	for candidate in /usr/sbin/mysqld /usr/sbin/mariadbd /usr/local/mysql/bin/mysqld; do
		[ -x "${candidate}" ] && { printf '%s\n' "${candidate}"; return 0; }
	done
	command -v mysqld 2>/dev/null && return 0
	command -v mariadbd 2>/dev/null && return 0
	return 1
}

is_up() {
	mysqladmin --protocol=TCP --host=127.0.0.1 --port="${PORT}" \
		--user=root ping >/dev/null 2>&1
}

start() {
	if is_up; then
		echo "local-mysql: already listening on 127.0.0.1:${PORT}"
		return 0
	fi

	local mysqld
	if ! mysqld="$(find_mysqld)"; then
		echo "local-mysql: no MySQL or MariaDB server found." >&2
		echo "Install one; the system service may remain stopped." >&2
		exit 1
	fi

	mkdir -p "${DATA_ROOT}"
	if [ ! -d "${DATA_DIR}/mysql" ]; then
		echo "local-mysql: initializing ${DATA_DIR}"
		rm -rf "${DATA_DIR}"
		mkdir -p "${DATA_DIR}"
		"${mysqld}" --initialize-insecure \
			--datadir="$(pwd -P)/${DATA_DIR}" \
			--basedir=/usr \
			--log-error="$(pwd -P)/${LOG_FILE}"
	fi

	echo "local-mysql: starting on 127.0.0.1:${PORT}"
	"${mysqld}" \
		--datadir="$(pwd -P)/${DATA_DIR}" \
		--basedir=/usr \
		--socket="${SOCKET}" \
		--port="${PORT}" \
		--bind-address=127.0.0.1 \
		--pid-file="$(pwd -P)/${PID_FILE}" \
		--log-error="$(pwd -P)/${LOG_FILE}" \
		--mysqlx=0 >/dev/null 2>&1 &

	local waited=0
	while [ "${waited}" -lt 60 ]; do
		is_up && break
		sleep 1
		waited=$(( waited + 1 ))
	done

	if ! is_up; then
		echo "local-mysql: did not become ready within ${waited}s." >&2
		tail -n 15 "${LOG_FILE}" >&2 2>/dev/null || true
		exit 1
	fi

	mysql --protocol=TCP --host=127.0.0.1 --port="${PORT}" --user=root <<-SQL
		CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;
		CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASSWORD}';
		GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
		FLUSH PRIVILEGES;
	SQL

	echo "local-mysql: ready ($("${mysqld}" --version | awk '{print $3}'))"
}

stop() {
	if ! is_up; then
		echo "local-mysql: not running"
		return 0
	fi

	mysqladmin --protocol=TCP --host=127.0.0.1 --port="${PORT}" \
		--user=root shutdown >/dev/null 2>&1 || true
	rm -f "${SOCKET}"
	echo "local-mysql: stopped"
}

case "${1:-start}" in
	start) start ;;
	stop) stop ;;
	status)
		if is_up; then echo "local-mysql: up on 127.0.0.1:${PORT}"; else echo "local-mysql: down"; exit 1; fi
		;;
	*)
		echo "usage: mysql.sh start|stop|status" >&2
		exit 2
		;;
esac
