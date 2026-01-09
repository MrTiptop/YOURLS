#!/bin/bash
set -e

# Wait for database to be ready
echo "Waiting for database to be ready..."
DB_HOST="${YOURLS_DB_HOST:-db}"
DB_NAME="${YOURLS_DB_NAME:-yourls}"
DB_USER="${YOURLS_DB_USER:-yourls}"
DB_PASS="${YOURLS_DB_PASS:-yourls_password}"

until php -r "
try {
    \$pdo = new PDO(
        'mysql:host=${DB_HOST};dbname=${DB_NAME}',
        '${DB_USER}',
        '${DB_PASS}'
    );
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->query('SELECT 1');
    exit(0);
} catch (PDOException \$e) {
    exit(1);
}
" 2>/dev/null; do
    echo "Database is unavailable - sleeping"
    sleep 2
done

echo "Database is ready!"

# Ensure user directory exists and has correct permissions
mkdir -p /var/www/html/user
chown -R www-data:www-data /var/www/html/user
chmod -R 755 /var/www/html/user

# Create config.php from environment variables if it doesn't exist
if [ ! -f /var/www/html/user/config.php ]; then
    echo "Creating config.php from environment variables..."
    
    # Generate cookie key if not provided
    if [ -z "$YOURLS_COOKIEKEY" ]; then
        YOURLS_COOKIEKEY=$(openssl rand -hex 32)
    fi
    
    cat > /var/www/html/user/config.php <<EOF
<?php
/** MySQL database username */
define( 'YOURLS_DB_USER', '${DB_USER}' );

/** MySQL database password */
define( 'YOURLS_DB_PASS', '${DB_PASS}' );

/** The name of the database for YOURLS */
define( 'YOURLS_DB_NAME', '${DB_NAME}' );

/** MySQL hostname */
define( 'YOURLS_DB_HOST', '${DB_HOST}' );

/** MySQL tables prefix */
define( 'YOURLS_DB_PREFIX', '${YOURLS_DB_PREFIX:-yourls_}' );

/** YOURLS installation URL */
define( 'YOURLS_SITE', '${YOURLS_SITE:-http://localhost:8080}' );

/** YOURLS language */
define( 'YOURLS_LANG', '${YOURLS_LANG:-}' );

/** Allow multiple short URLs for a same long URL */
define( 'YOURLS_UNIQUE_URLS', ${YOURLS_UNIQUE_URLS:-true} );

/** Private means the Admin area will be protected with login/pass */
define( 'YOURLS_PRIVATE', ${YOURLS_PRIVATE:-true} );

/** A random secret hash used to encrypt cookies */
define( 'YOURLS_COOKIEKEY', '${YOURLS_COOKIEKEY}' );

/** Username(s) and password(s) allowed to access the site */
\$yourls_user_passwords = array(
    '${YOURLS_USER:-admin}' => '${YOURLS_PASS:-admin}',
);

/** URL shortening method: either 36 or 62 */
define( 'YOURLS_URL_CONVERT', ${YOURLS_URL_CONVERT:-36} );

/** Debug mode */
define( 'YOURLS_DEBUG', ${YOURLS_DEBUG:-false} );

/** Reserved keywords */
\$yourls_reserved_URL = array();
EOF
    chown www-data:www-data /var/www/html/user/config.php
    chmod 644 /var/www/html/user/config.php
    echo "config.php created successfully"
fi

# Execute the main command
exec "$@"
