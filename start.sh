#!/bin/bash
set -e

# Создаём директорию для сокета PHP-FPM, если её нет
mkdir -p /var/run/php

# Запускаем PHP-FPM в фоне
php-fpm -D

# Ждём, пока PHP-FPM запустится
sleep 2

# Проверяем, что PHP-FPM запущен
if [ ! -S /var/run/php/php8.2-fpm.sock ]; then
    echo "Ошибка: PHP-FPM сокет не найден"
    exit 1
fi

# Запускаем Nginx в foreground режиме
exec nginx -g 'daemon off;'

