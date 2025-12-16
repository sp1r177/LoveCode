#!/bin/sh
set -e

echo "[boot] starting php-fpm..."

# Попробовать разные команды (в разных образах имя отличается)
if command -v php-fpm8.2 >/dev/null 2>&1; then
  php-fpm8.2 -D
elif command -v php-fpm >/dev/null 2>&1; then
  php-fpm -D
else
  echo "[boot][fatal] php-fpm binary not found"
  exit 1
fi

echo "[boot] searching php-fpm socket..."
SOCKET=""

for i in 1 2 3 4 5 6 7 8 9 10; do
  for s in /run/php/*.sock /var/run/php/*.sock; do
    if [ -S "$s" ]; then
      SOCKET="$s"
      break
    fi
  done
  [ -n "$SOCKET" ] && break
  sleep 0.5
done

if [ -z "$SOCKET" ]; then
  echo "[boot][fatal] php-fpm socket not found"
  echo "[boot] ls -la /run/php:"
  ls -la /run/php 2>/dev/null || true
  echo "[boot] ls -la /var/run/php:"
  ls -la /var/run/php 2>/dev/null || true
  exit 1
fi

echo "[boot] using php-fpm socket: $SOCKET"

# nginx.conf должен содержать unix:FASTCGI_SOCKET
sed -i "s|FASTCGI_SOCKET|$SOCKET|g" /etc/nginx/sites-available/default

echo "[boot] nginx config fastcgi_pass:"
grep -n "fastcgi_pass" /etc/nginx/sites-available/default || true

echo "[boot] starting nginx..."
exec nginx -g "daemon off;"