#!/usr/bin/env bash
# Create a throwaway WordPress test site with the plugin active (CI and local).
# Usage: WP_SRC=<WordPress core dir> WP_DIR=<target> SITE_URL=http://localhost:8899 \
#        DB_HOST=127.0.0.1 DB_NAME=wp_iaa DB_USER=iaa DB_PASSWORD=... [PLUGIN_ZIP=dist/rar-inbox-ai-assist-X.zip] \
#        bash scripts/ci/setup-wp.sh
# Never point this at a production database: it installs WordPress fresh into DB_NAME.
set -euo pipefail
repo="$(cd "$(dirname "$0")/../.." && pwd)"
: "${WP_SRC:?}" "${WP_DIR:?}" "${SITE_URL:?}" "${DB_HOST:?}" "${DB_NAME:?}" "${DB_USER:?}" "${DB_PASSWORD:?}"
mkdir -p "$WP_DIR"
cp -a "$WP_SRC"/. "$WP_DIR"/
rm -rf "$WP_DIR/.git"
salt() { php -r 'echo bin2hex(random_bytes(32));'; }
cat > "$WP_DIR/wp-config.php" <<PHP
<?php
define( 'DB_NAME', '${DB_NAME}' );
define( 'DB_USER', '${DB_USER}' );
define( 'DB_PASSWORD', '${DB_PASSWORD}' );
define( 'DB_HOST', '${DB_HOST}' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
\$table_prefix = 'wp_';
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as \$k ) {
	define( \$k, '$(salt)' . \$k );
}
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', __DIR__ . '/debug.log' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'DISABLE_WP_CRON', true );
if ( getenv( 'IAA_PROVIDER' ) ) {
	define( 'RAR_IAA_PROVIDER', getenv( 'IAA_PROVIDER' ) );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
PHP
if [ -n "${PLUGIN_ZIP:-}" ]; then
  # Install from a built release ZIP (T5.01 fresh-install check).
  unzip -q "$PLUGIN_ZIP" -d "$WP_DIR/wp-content/plugins/"
else
  ln -sfn "$repo/plugin/rar-inbox-ai-assist" "$WP_DIR/wp-content/plugins/rar-inbox-ai-assist"
fi
WP_DIR="$WP_DIR" SITE_URL="$SITE_URL" php "$repo/scripts/ci/install-wp.php"
