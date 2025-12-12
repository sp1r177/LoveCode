# Multi-stage build для React frontend
FROM node:20-alpine AS frontend-builder

WORKDIR /app/frontend

# Копируем package файлы
COPY frontend/package*.json ./

# Устанавливаем зависимости (используем npm install, если нет package-lock.json)
RUN if [ -f package-lock.json ]; then \
        npm ci; \
    else \
        npm install; \
    fi

# Копируем исходники frontend
COPY frontend/ ./

# Собираем frontend
RUN npm run build

# Финальный образ с PHP и Nginx
FROM php:8.2-fpm

# Устанавливаем необходимые пакеты
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Устанавливаем Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Настраиваем PHP-FPM для использования unix socket
COPY php-fpm-pool.conf /usr/local/etc/php-fpm.d/zzz-custom.conf
RUN mkdir -p /var/run/php && chown www-data:www-data /var/run/php

# Настраиваем рабочую директорию
WORKDIR /var/www/html

# Копируем backend зависимости
COPY backend/composer.json* ./

# Устанавливаем PHP зависимости
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Копируем backend код
COPY backend/ ./

# Копируем собранный frontend из builder stage
COPY --from=frontend-builder /app/frontend/dist /var/www/html/frontend/dist

# Настраиваем права и создаём директорию для логов
RUN chown -R www-data:www-data /var/www/html && \
    mkdir -p /var/www/html/.cursor && \
    chown -R www-data:www-data /var/www/html/.cursor

# Копируем конфигурацию Nginx и активируем её
COPY nginx.conf /etc/nginx/sites-available/default
RUN rm -f /etc/nginx/sites-enabled/default && \
    ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default && \
    rm -f /etc/nginx/conf.d/default.conf

# Копируем скрипт запуска
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]

