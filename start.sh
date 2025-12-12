#!/bin/bash
set -e

LOG_FILE="/var/www/html/.cursor/debug.log"
mkdir -p "$(dirname "$LOG_FILE")"

log_debug() {
    local hypothesis=$1
    local message=$2
    local data=$3
    local timestamp=$(date +%s)000
    local location="start.sh"
    echo "{\"timestamp\":$timestamp,\"location\":\"$location\",\"message\":\"$message\",\"data\":$data,\"sessionId\":\"debug-session\",\"runId\":\"run1\",\"hypothesisId\":\"$hypothesis\"}" >> "$LOG_FILE" 2>&1 || true
}

# #region agent log - Hypothesis A, E: Проверяем конфигурацию PHP-FPM перед запуском
log_debug "A" "Проверка конфигурации PHP-FPM" "{\"action\":\"checking_pool_config\"}"
POOL_CONFIG="/usr/local/etc/php-fpm.d/www.conf"
if [ -f "$POOL_CONFIG" ]; then
    LISTEN_LINE=$(grep "^listen" "$POOL_CONFIG" | head -1 || echo "not_found")
    ESCAPED_LISTEN=$(echo "$LISTEN_LINE" | sed 's/"/\\"/g')
    log_debug "A" "Найдена конфигурация pool" "{\"config_file\":\"$POOL_CONFIG\",\"listen_line\":\"$ESCAPED_LISTEN\"}"
else
    log_debug "A" "Конфигурация pool не найдена" "{\"config_file\":\"$POOL_CONFIG\"}"
fi
# #endregion

# Создаём директорию для сокета PHP-FPM, если её нет
mkdir -p /var/run/php
mkdir -p /run/php

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
log_debug "C" "Проверка прав на директории сокетов" "{\"dirs\":[{\"path\":\"/var/run/php\",\"exists\":$VAR_RUN_EXISTS,\"writable\":$VAR_RUN_WRITABLE},{\"path\":\"/run/php\",\"exists\":$RUN_EXISTS,\"writable\":$RUN_WRITABLE}]}"
# #endregion

# Запускаем PHP-FPM в фоне
log_debug "D" "Запуск PHP-FPM" "{\"command\":\"php-fpm -D\"}"
php-fpm -D

# #region agent log - Hypothesis D: Проверяем через разные интервалы
log_debug "D" "Ожидание запуска PHP-FPM (0s)" "{\"action\":\"wait_start\",\"elapsed\":0}"
sleep 1
log_debug "D" "Ожидание запуска PHP-FPM (1s)" "{\"action\":\"wait_start\",\"elapsed\":1}"
sleep 1
log_debug "D" "Ожидание запуска PHP-FPM (2s)" "{\"action\":\"wait_start\",\"elapsed\":2}"
# #endregion

# #region agent log - Hypothesis A, E: Ищем сокет во всех возможных местах
log_debug "A" "Поиск сокета PHP-FPM" "{\"action\":\"searching_socket\"}"
FOUND_SOCKETS=$(find /var/run /run /tmp -name "*fpm*.sock" 2>/dev/null || echo "")
ESCAPED_SOCKETS=$(echo "$FOUND_SOCKETS" | sed 's/"/\\"/g' | tr '\n' ' ')
log_debug "A" "Найденные сокеты" "{\"sockets\":\"$ESCAPED_SOCKETS\"}"

EXPECTED_PATH="/var/run/php/php8.2-fpm.sock"
ALT_PATHS=("/run/php/php8.2-fpm.sock" "/var/run/php-fpm.sock" "/run/php-fpm.sock" "/tmp/php-fpm.sock")
SOCKET_FOUND=""
# #endregion

# #region agent log - Hypothesis E: Проверяем все альтернативные пути
for path in "$EXPECTED_PATH" "${ALT_PATHS[@]}"; do
    if [ -S "$path" ]; then
        log_debug "E" "Сокет найден" "{\"path\":\"$path\",\"exists\":true}"
        SOCKET_FOUND="$path"
        break
    else
        log_debug "E" "Сокет не найден по пути" "{\"path\":\"$path\",\"exists\":false}"
    fi
done
# #endregion

# #region agent log - Hypothesis B: Проверяем, не использует ли PHP-FPM TCP
if [ -z "$SOCKET_FOUND" ]; then
    TCP_LISTEN=$(ss -tlnp 2>/dev/null | grep php-fpm || netstat -tlnp 2>/dev/null | grep php-fpm || echo "not_found")
    ESCAPED_TCP=$(echo "$TCP_LISTEN" | sed 's/"/\\"/g' | tr '\n' ' ')
    log_debug "B" "Проверка TCP подключения" "{\"tcp_listen\":\"$ESCAPED_TCP\"}"
fi
# #endregion

if [ -z "$SOCKET_FOUND" ]; then
    # #region agent log - Hypothesis A, B: Выводим конфигурацию для диагностики
    if [ -f "$POOL_CONFIG" ]; then
        POOL_CONTENT=$(cat "$POOL_CONFIG" | grep -E "^(listen|user|group)" | head -5 | tr '\n' ';')
        ESCAPED_POOL=$(echo "$POOL_CONTENT" | sed 's/"/\\"/g')
        log_debug "A" "Конфигурация pool для диагностики" "{\"pool_config_excerpt\":\"$ESCAPED_POOL\"}"
    fi
    # #endregion
    echo "Ошибка: PHP-FPM сокет не найден"
    CHECKED_PATHS_JSON=$(printf ',"%s"' "${ALT_PATHS[@]}" | sed 's/^,//')
    log_debug "A" "ОШИБКА: сокет не найден" "{\"expected\":\"$EXPECTED_PATH\",\"checked_paths\":[\"$EXPECTED_PATH\",$CHECKED_PATHS_JSON]}"
    exit 1
else
    log_debug "A" "Сокет успешно найден, обновляем nginx.conf" "{\"socket_path\":\"$SOCKET_FOUND\"}"
    # Обновляем nginx.conf с правильным путём к сокету
    sed -i "s|unix:/var/run/php/php8.2-fpm.sock|unix:$SOCKET_FOUND|g" /etc/nginx/sites-available/default
fi

# Запускаем Nginx в foreground режиме
log_debug "D" "Запуск Nginx" "{\"action\":\"starting_nginx\"}"
exec nginx -g 'daemon off;'

