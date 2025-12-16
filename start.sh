#!/bin/bash
set -e

LOG_FILE="/var/www/html/.cursor/debug.log"
mkdir -p "$(dirname "$LOG_FILE")"

log_debug() {
    local message=$1
    local data=$2
    local timestamp=$(date +%s)000
    local location="start.sh"
    echo "{\"timestamp\":$timestamp,\"location\":\"$location\",\"message\":\"$message\",\"data\":$data}" >> "$LOG_FILE" 2>&1 || true
}

# #region agent log - Hypothesis A, E: Проверяем конфигурацию PHP-FPM перед запуском
log_debug "Проверка конфигурации PHP-FPM" "{\"action\":\"checking_pool_config\"}"
POOL_CONFIG="/usr/local/etc/php-fpm.d/www.conf"
if [ -f "$POOL_CONFIG" ]; then
    LISTEN_LINE=$(grep "^listen" "$POOL_CONFIG" | head -1 || echo "not_found")
    ESCAPED_LISTEN=$(echo "$LISTEN_LINE" | sed 's/"/\\"/g')
    log_debug "Найдена конфигурация pool" "{\"config_file\":\"$POOL_CONFIG\",\"listen_line\":\"$ESCAPED_LISTEN\"}
else
    log_debug "Конфигурация pool не найдена" "{\"config_file\":\"$POOL_CONFIG\"}
fi
# #endregion

# Создаём директорию для сокета PHP-FPM, если её нет
mkdir -p /var/run/php
mkdir -p /run/php

EXPECTED_PATH="/var/run/php/php8.2-fpm.sock"
ALT_PATHS=("/run/php/php8.2-fpm.sock" "/var/run/php-fpm.sock" "/run/php-fpm.sock" "/tmp/php-fpm.sock")

# #region agent log - Hypothesis C: Проверяем права на директории
if [ -d /var/run/php ]; then
    VAR_RUN_EXISTS="true"
    VAR_RUN_WRITABLE=$([ -w /var/run/php ] && echo "true" || echo "false")
else
    VAR_RUN_EXISTS="false"
    VAR_RUN_WRITABLE="false"
fi
if [ -d /run/php ]; then
    RUN_EXISTS="true"
    RUN_WRITABLE=$([ -w /run/php ] && echo "true" || echo "false")
else
    RUN_EXISTS="false"
    RUN_WRITABLE="false"
fi
log_debug "Проверка прав на директории сокетов" "{\"dirs\":[{\"path\":\"/var/run/php\",\"exists\":$VAR_RUN_EXISTS,\"writable\":$VAR_RUN_WRITABLE},{\"path\":\"/run/php\",\"exists\":$RUN_EXISTS,\"writable\":$RUN_WRITABLE}]}"
# #endregion

# Запускаем PHP-FPM в фоне
log_debug "Запуск PHP-FPM" "{\"command\":\"php-fpm -D\"}"
php-fpm -D

# Ждем появления сокета
MAX_WAIT=10
WAIT_COUNT=0
while [ $WAIT_COUNT -lt $MAX_WAIT ]; do
    log_debug "Ожидание сокета PHP-FPM (${WAIT_COUNT}s)" "{\"action\":\"waiting_for_socket\",\"elapsed\":${WAIT_COUNT}}"
    sleep 1
    WAIT_COUNT=$((WAIT_COUNT + 1))
    
    # Проверяем каждый возможный путь к сокету
    for path in "$EXPECTED_PATH" "${ALT_PATHS[@]}"; do
        if [ -S "$path" ]; then
            SOCKET_FOUND="$path"
            log_debug "Сокет найден" "{\"path\":\"$SOCKET_FOUND\",\"found_at\":\"${WAIT_COUNT}s\"}"
            break 2
        fi
    done
done

# Если сокет не найден в предыдущем цикле, ищем его во всех возможных местах
if [ -z "$SOCKET_FOUND" ]; then
    log_debug "Поиск сокета PHP-FPM" "{\"action\":\"searching_socket\"}"
    FOUND_SOCKETS=$(find /var/run /run /tmp -name "*fpm*.sock" 2>/dev/null || echo "")
    ESCAPED_SOCKETS=$(echo "$FOUND_SOCKETS" | sed 's/"/\\"/g' | tr '\n' ' ')
    log_debug "Найденные сокеты" "{\"sockets\":\"$ESCAPED_SOCKETS\"}"
    
    # Используем первый найденный сокет
    SOCKET_FOUND=$(echo "$FOUND_SOCKETS" | head -1)
    
    # Проверяем, что найденный сокет не пустой
    if [ -z "$SOCKET_FOUND" ]; then
        SOCKET_FOUND=""
    fi
fi

if [ -z "$SOCKET_FOUND" ]; then
    echo "Ошибка: PHP-FPM сокет не найден"
    log_debug "ОШИБКА: сокет не найден" "{\"expected\":\"$EXPECTED_PATH\",\"checked_paths\":[\"$EXPECTED_PATH\"]}"
    exit 1
else
    log_debug "Сокет успешно найден, обновляем nginx.conf" "{\"socket_path\":\"$SOCKET_FOUND\"}"
    # Обновляем nginx.conf с правильным путём к сокету
    sed -i "s|FASTCGI_SOCKET|$SOCKET_FOUND|g" /etc/nginx/sites-available/default
fi

# Запускаем Nginx в foreground режиме
log_debug "Запуск Nginx" "{\"action\":\"starting_nginx\"}"
exec nginx -g 'daemon off;'

