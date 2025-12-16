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
# API URL теперь определяется автоматически из window.location
RUN npm run build || (echo "Frontend build failed" && exit 1)
RUN if [ ! -d "dist" ]; then echo "ERROR: dist directory not created after build" && ls -la && exit 1; fi

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
    git \
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

# Устанавливаем PHP зависимости с настройками для надежности
RUN composer config --global github-protocols https && \
    composer config --global process-timeout 300 && \
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

# Копируем backend код и .env файл
COPY backend/ ./backend/
RUN cp -r backend/. /var/www/html/ && rm -rf backend/

# Копируем собранный frontend из builder stage
COPY --from=frontend-builder /app/frontend/dist /var/www/html/public/
RUN if [ ! -d "/var/www/html/public" ]; then echo "ERROR: Public directory not created" && exit 1; fi

# Настраиваем права и создаём директорию для логов
RUN chown -R www-data:www-data /var/www/html && \
    mkdir -p /var/www/html/.cursor && \
    chown -R www-data:www-data /var/www/html/.cursor && \
    # Проверяем, что index.php существует
    if [ ! -f /var/www/html/public/index.php ]; then \
        echo "ERROR: index.php not found in public directory"; \
        exit 1; \
    fi && \
    # Проверяем, что frontend файлы существуют
    if [ ! -d /var/www/html/public/assets ] && [ ! -f /var/www/html/public/index.html ]; then \
        echo "WARNING: Frontend files not found in public directory"; \
    fi
    
    # Убедимся, что хотя бы один из необходимых файлов существует
    if [ ! -f /var/www/html/public/index.html ] && [ ! -f /var/www/html/public/index.php ]; then \
        echo "ERROR: Neither index.html nor index.php found in public directory"; \
        ls -la /var/www/html/public 2>&1 || echo "Cannot list public directory"; \
        exit 1; \
    fi

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