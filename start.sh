#!/bin/bash
set -e

echo "Starting PHP-FPM..."
# Start PHP-FPM
if command -v php-fpm8.2 >/dev/null 2>&1; then
    php-fpm8.2 -D
elif command -v php-fpm >/dev/null 2>&1; then
    php-fpm -D
else
    echo "ERROR: php-fpm not found"
    exit 1
fi

echo "Waiting for PHP-FPM socket..."
# Wait for PHP-FPM socket to be created
SOCKET_FOUND=""
for i in {1..30}; do
    for sock in /var/run/php/*.sock /run/php/*.sock; do
        if [ -S "$sock" ]; then
            SOCKET_FOUND="$sock"
            echo "Found socket: $SOCKET_FOUND"
            break 2
        fi
    done
    echo "Waiting for socket... ($i/30)"
    sleep 1
done

if [ -z "$SOCKET_FOUND" ]; then
    echo "ERROR: PHP-FPM socket not found"
    echo "Checking directories:"
    ls -la /var/run/php/ 2>/dev/null || echo "/var/run/php/ not found"
    ls -la /run/php/ 2>/dev/null || echo "/run/php/ not found"
    exit 1
fi

echo "Using PHP-FPM socket: $SOCKET_FOUND"

# Replace placeholder in nginx config
echo "Updating nginx configuration..."
sed -i "s|unix:FASTCGI_SOCKET|unix:$SOCKET_FOUND|g" /etc/nginx/sites-available/default

# Show the updated configuration
echo "Updated fastcgi_pass lines:"
grep -n "fastcgi_pass" /etc/nginx/sites-available/default || echo "No fastcgi_pass found"

# Test nginx configuration
echo "Testing nginx configuration..."
nginx -t

echo "Starting nginx..."
# Start nginx in foreground
exec nginx -g "daemon off;"