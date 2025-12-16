#!/bin/bash
set -e

# Handle shutdown signals gracefully
trap 'echo "Received SIGTERM, shutting down gracefully"; exit 0' TERM
trap 'echo "Received SIGINT, shutting down gracefully"; exit 0' INT

LOG_FILE="/var/www/html/.cursor/debug.log"
mkdir -p "$(dirname "$LOG_FILE")"
chmod 777 "$(dirname "$LOG_FILE")" 2>/dev/null || true

log_debug() {
    local message=$1
    local data=$2
    local timestamp=$(date +%s)000
    local location="start.sh"
    echo "{\"timestamp\":$timestamp,\"location\":\"$location\",\"message\":\"$message\",\"data\":$data}" >> "$LOG_FILE" 2>&1 || true
}

# Проверяем, что public директория существует
if [ ! -d "/var/www/html/public" ]; then
    echo "ERROR: Public directory not found"
    exit 1
fi

# Проверяем, что index.php существует
if [ ! -f "/var/www/html/public/index.php" ]; then
    echo "ERROR: index.php not found"
    exit 1
fi

log_debug "Файловая структура проверена" "{\"public_dir_exists\":true,\"index_php_exists\":true}"

# Проверяем обязательные переменные окружения
if [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
    log_debug "Предупреждение: Не все переменные окружения БД установлены" "{\"db_host_set\":$([ -z "$DB_HOST" ] && echo "false" || echo "true"),\"db_name_set\":$([ -z "$DB_NAME" ] && echo "false" || echo "true"),\"db_user_set\":$([ -z "$DB_USER" ] && echo "false" || echo "true")}"
fi

# Логируем содержимое public директории для отладки
PUBLIC_CONTENT=$(ls -la /var/www/html/public 2>&1 || echo "error_reading_dir")
log_debug "Содержимое public директории" "{\"content\":\"$PUBLIC_CONTENT\"}"

# #region agent log - Hypothesis A, E: Проверяем конфигурацию PHP-FPM перед запуском
log_debug "Проверка конфигурации PHP-FPM" "{\"action\":\"checking_pool_config\"}"
POOL_CONFIG="/usr/local/etc/php-fpm.d/www.conf"

# Проверяем также нашу кастомную конфигурацию
CUSTOM_POOL_CONFIG="/usr/local/etc/php-fpm.d/zzz-custom.conf"
if [ -f "$CUSTOM_POOL_CONFIG" ]; then
    CUSTOM_LISTEN=$(grep "^listen" "$CUSTOM_POOL_CONFIG" | head -1 || echo "not_found")
    log_debug "Кастомная конфигурация PHP-FPM" "{\"config_file\":\"$CUSTOM_POOL_CONFIG\",\"listen_line\":\"$CUSTOM_LISTEN\"}"
fi
if [ -f "$POOL_CONFIG" ]; then
    LISTEN_LINE=$(grep "^listen" "$POOL_CONFIG" | head -1 || echo "not_found")
    ESCAPED_LISTEN=$(echo "$LISTEN_LINE" | sed 's/"/\\"/g')
    log_debug "Найдена конфигурация pool" "{\"config_file\":\"$POOL_CONFIG\",\"listen_line\":\"$ESCAPED_LISTEN\"}"
else
    log_debug "Конфигурация pool не найдена" "{\"config_file\":\"$POOL_CONFIG\"}"
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
# Пробуем разные версии php-fpm
PHP_FPM_STARTED=false
if command -v php-fpm8.2 >/dev/null 2>&1; then
    php-fpm8.2 -D 2>&1
    if [ $? -eq 0 ]; then
        PHP_FPM_STARTED=true
        log_debug "PHP-FPM 8.2 запущен" "{\"status\":\"success\"}"
    fi
elif command -v php-fpm >/dev/null 2>&1; then
    php-fpm -D 2>&1
    if [ $? -eq 0 ]; then
        PHP_FPM_STARTED=true
        log_debug "PHP-FPM запущен" "{\"status\":\"success\"}"
    fi
else
    log_debug "ОШИБКА: PHP-FPM не найден" "{\"error\":\"php-fpm command not found\"}"
    exit 1
fi

if [ "$PHP_FPM_STARTED" = "false" ]; then
    log_debug "ОШИБКА: Не удалось запустить PHP-FPM" "{\"status\":\"failed\"}"
    exit 1
fi

# Дополнительная проверка, что PHP-FPM запущен
sleep 2
PHP_FPM_STATUS=$(ps aux | grep php-fpm | grep -v grep || echo "not_running")
log_debug "Статус PHP-FPM" "{\"status\":\"$PHP_FPM_STATUS\"}"

# Проверяем, что PHP-FPM процесс запущен корректно
PHP_FPM_MASTER=$(ps aux | grep "php-fpm: master" | grep -v grep || echo "not_found")
PHP_FPM_WORKER=$(ps aux | grep "php-fpm: pool" | grep -v grep || echo "not_found")
log_debug "Процессы PHP-FPM" "{\"master\":\"$PHP_FPM_MASTER\",\"worker\":\"$PHP_FPM_WORKER\"}"

# Ждем появления сокета
MAX_WAIT=15
WAIT_COUNT=0
SOCKET_FOUND=""

while [ $WAIT_COUNT -lt $MAX_WAIT ] && [ -z "$SOCKET_FOUND" ]; do
    log_debug "Ожидание сокета PHP-FPM (${WAIT_COUNT}s)" "{\"action\":\"waiting_for_socket\",\"elapsed\":${WAIT_COUNT}}"
    sleep 1
    WAIT_COUNT=$((WAIT_COUNT + 1))
    
    # Проверяем каждый возможный путь к сокету
    for path in "$EXPECTED_PATH" "${ALT_PATHS[@]}"; do
        if [ -S "$path" ]; then
            SOCKET_FOUND="$path"
            log_debug "Сокет найден" "{\"path\":\"$SOCKET_FOUND\",\"found_at\":\"${WAIT_COUNT}s\"}"
            break
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
    if [ -z "$SOCKET_FOUND" ] || [ "$SOCKET_FOUND" = "" ]; then
        SOCKET_FOUND=""
    fi
fi

if [ -z "$SOCKET_FOUND" ]; then
    echo "Ошибка: PHP-FPM сокет не найден"
    log_debug "ОШИБКА: сокет не найден" "{\"expected\":\"$EXPECTED_PATH\",\"checked_paths\":[\"$EXPECTED_PATH\"]}"
    # List all sockets for debugging
    ALL_SOCKETS=$(find /var/run /run /tmp -name "*.sock" 2>/dev/null || echo "none found")
    log_debug "Все найденные сокеты" "{\"all_sockets\":\"$ALL_SOCKETS\"}"
    exit 1
else
    log_debug "Сокет успешно найден, обновляем nginx.conf" "{\"socket_path\":\"$SOCKET_FOUND\"}"
    
    # Проверяем, что сокет файл действительно существует
    if [ ! -S "$SOCKET_FOUND" ]; then
        log_debug "ОШИБКА: Сокет файл не существует" "{\"socket_path\":\"$SOCKET_FOUND\",\"file_exists\":false}"
        exit 1
    fi
    
    # Проверяем, что nginx.conf существует
    if [ ! -f /etc/nginx/sites-available/default ]; then
        log_debug "ОШИБКА: nginx.conf не найден" "{\"path\":\"/etc/nginx/sites-available/default\"}"
        exit 1
    fi
    
    # Логируем содержимое nginx.conf для отладки
    NGINX_CONF_CONTENT=$(head -20 /etc/nginx/sites-available/default 2>&1 || echo "error_reading_conf")
    log_debug "Содержимое nginx.conf (первые 20 строк)" "{\"content\":\"$NGINX_CONF_CONTENT\"}"
    
    # Обновляем nginx.conf с правильным путём к сокету
    sed -i "s|FASTCGI_SOCKET|$SOCKET_FOUND|g" /etc/nginx/sites-available/default
    # Verify the replacement worked
    REPLACEMENT_CHECK=$(grep "fastcgi_pass unix:$SOCKET_FOUND" /etc/nginx/sites-available/default | wc -l)
    log_debug "Проверка замены сокета" "{\"matches_found\":$REPLACEMENT_CHECK,\"socket_path\":\"$SOCKET_FOUND\"}"
    
    # Also check that FASTCGI_SOCKET was replaced
    BEFORE_REPLACEMENT=$(grep "fastcgi_pass unix:FASTCGI_SOCKET" /etc/nginx/sites-available/default | wc -l)
    log_debug "Проверка наличия незамененного плейсхолдера" "{\"unreplaced_count\":$BEFORE_REPLACEMENT,\"should_be_zero\":true}"
fi

# Запускаем Nginx в foreground режиме
log_debug "Запуск Nginx" "{\"action\":\"starting_nginx\"}"
# Проверяем конфигурацию nginx перед запуском
NGINX_TEST=$(nginx -t 2>&1 || echo "config_error")
log_debug "Проверка конфигурации Nginx" "{\"result\":\"$NGINX_TEST\"}"

# Если есть ошибки конфигурации, выходим
if [[ "$NGINX_TEST" == *"config_error"* ]] || [[ "$NGINX_TEST" == *"emerg"* ]]; then
    log_debug "ОШИБКА: Неверная конфигурация Nginx" "{\"error\":\"$NGINX_TEST\"}"
    exit 1
fi

# Небольшая задержка перед запуском nginx для уверенности
sleep 1

# Проверяем, что все сервисы готовы
MAX_READY_WAIT=30
READY_WAIT=0
while [ $READY_WAIT -lt $MAX_READY_WAIT ]; do
    # Проверяем, что PHP-FPM слушает на сокете
    if [ -S "$SOCKET_FOUND" ]; then
        log_debug "Сервисы готовы" "{\"php_fpm_socket\":\"$SOCKET_FOUND\",\"wait_time\":$READY_WAIT}"
        break
    fi
    
    sleep 1
    READY_WAIT=$((READY_WAIT + 1))
    
    if [ $READY_WAIT -eq $MAX_READY_WAIT ]; then
        log_debug "ОШИБКА: Сервисы не готовы в течение времени ожидания" "{\"max_wait\":$MAX_READY_WAIT}"
        exit 1
    fi
done

# Создаем простой health check файл если его нет
if [ ! -f /var/www/html/public/health ]; then
    echo "OK" > /var/www/html/public/health
    chmod 644 /var/www/html/public/health
    log_debug "Создан health check файл" "{\"path\":\"/var/www/html/public/health\"}"
fi

# Проверяем, что nginx может запуститься
if ! command -v nginx >/dev/null 2>&1; then
    log_debug "ОШИБКА: nginx не найден" "{\"error\":\"nginx command not found\"}"
    exit 1
fi

# Запускаем nginx в foreground режиме
nginx -g 'daemon off;'

