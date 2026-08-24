#!/usr/bin/env bash
# Run the WordPress PHPUnit suites without Docker or the Studio database.

set -euo pipefail

cd "$(dirname "$0")/../.."

bash bin/local/mysql.sh start
bash bin/local/wp-core.sh

repo_root="$(pwd -P)"
export WP_TESTS_DIR="${repo_root}/vendor/wp-phpunit/wp-phpunit"
export WP_PHPUNIT__TESTS_CONFIG="${repo_root}/tests/wp-tests-config.php"
export AA_TESTS_ABSPATH="${repo_root}/${AA_TESTS_WP_DIR:-.cache/local/wordpress}"
export AA_TESTS_DB_HOST="127.0.0.1:${AA_TESTS_DB_PORT:-13316}"

./vendor/bin/phpunit "$@"

echo "Native PHPUnit used PHP $(php -r 'echo PHP_VERSION;') and $(bash bin/local/mysql.sh status)."
echo "The pinned CI lane remains authoritative for release parity."
