#!/bin/bash
set -e

# Start PHP-FPM
if command -v php-fpm8.2 >/dev/null 2>&1; then
    php-fpm8.2 -D
elif command -v php-fpm >/dev/null 2>&1; then
    php-fpm -D
else
    echo "ERROR: php-fpm not found"
    exit 1
fi

# Wait for PHP-FPM socket to be created
SOCKET_FOUND=""
for i in {1..30}; do
    for sock in /var/run/php/*.sock /run/php/*.sock; do
        if [ -S "$sock" ]; then
            SOCKET_FOUND="$sock"
            break 2
        fi
    done
    sleep 1
done

if [ -z "$SOCKET_FOUND" ]; then
    echo "ERROR: PHP-FPM socket not found"
    ls -la /var/run/php/ /run/php/ 2>/dev/null || echo "Socket directories not found"
    exit 1
fi

echo "Using PHP-FPM socket: $SOCKET_FOUND"

# Replace placeholder in nginx config
sed -i "s|unix:FASTCGI_SOCKET|unix:$SOCKET_FOUND|g" /etc/nginx/sites-available/default

# Test nginx configuration
grep -n "fastcgi_pass" /etc/nginx/sites-available/default || echo "No fastcgi_pass found"

# Start nginx in foreground
exec nginx -g "daemon off;"