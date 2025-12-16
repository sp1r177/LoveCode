# Пошаговая инструкция по деплою на cloud.ru

## 📋 Предварительные требования

- Аккаунт на [cloud.ru](https://cloud.ru)
- Аккаунт на GitHub
- Домен (опционально, можно использовать IP)
- SSH доступ к серверу

---

## Шаг 1: Подготовка сервера на cloud.ru

### 1.1 Создание виртуальной машины

1. Войдите в панель cloud.ru
2. Перейдите в раздел "Виртуальные машины"
3. Создайте новую VM:
   - **ОС**: Ubuntu 22.04 LTS
   - **RAM**: минимум 2 GB (рекомендуется 4 GB)
   - **CPU**: минимум 2 ядра
   - **Диск**: минимум 20 GB SSD
   - **Сеть**: включите публичный IP

### 1.2 Подключение к серверу

```bash
ssh root@YOUR_SERVER_IP
```

Или если используете ключ:
```bash
ssh -i ~/.ssh/your_key root@YOUR_SERVER_IP
```

---

## Шаг 2: Установка необходимого ПО

### 2.1 Обновление системы

```bash
apt update && apt upgrade -y
```

### 2.2 Установка PHP 8.2 и расширений

```bash
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip
```

Проверьте версию:
```bash
php -v
```

### 2.3 Установка MySQL

```bash
apt install -y mysql-server
mysql_secure_installation
```

При настройке:
- Установите пароль для root
- Удалите анонимных пользователей: **Y**
- Отключите удаленный вход root: **Y**
- Удалите тестовую БД: **Y**
- Перезагрузите таблицы привилегий: **Y**

### 2.4 Установка Nginx

```bash
apt install -y nginx
systemctl start nginx
systemctl enable nginx
```

### 2.5 Установка Composer

```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
composer --version
```

### 2.6 Установка Node.js 20

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
node -v
npm -v
```

---

## Шаг 3: Настройка базы данных

### 3.1 Создание базы данных

```bash
mysql -u root -p
```

В MySQL консоли выполните:

```sql
CREATE DATABASE flirt_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ai_user'@'localhost' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON flirt_ai.* TO 'ai_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3.2 Импорт схемы

```bash
# Позже, после клонирования репозитория
mysql -u ai_user -p flirt_ai < /var/www/flirt-ai/db/schema.sql
```

---

## Шаг 4: Настройка структуры проекта на сервере

### 4.1 Создание директорий

```bash
mkdir -p /var/www/flirt-ai
cd /var/www/flirt-ai
```

### 4.2 Клонирование репозитория (временно, для получения файлов)

```bash
# Если репозиторий приватный, используйте SSH ключ
git clone https://github.com/YOUR_USERNAME/YOUR_REPO.git /var/www/flirt-ai
```

Или создайте структуру вручную (файлы будут деплоиться через GitHub Actions).

### 4.3 Установка прав

```bash
chown -R www-data:www-data /var/www/flirt-ai
chmod -R 755 /var/www/flirt-ai
```

---

## Шаг 5: Настройка Nginx

### 5.1 Создание конфигурации

```bash
nano /etc/nginx/sites-available/flirt-ai
```

Вставьте следующую конфигурацию:

```nginx
server {
    listen 80;
    server_name YOUR_DOMAIN.com www.YOUR_DOMAIN.com;
    
    # Логи
    access_log /var/log/nginx/flirt-ai-access.log;
    error_log /var/log/nginx/flirt-ai-error.log;

    # Frontend (статичные файлы)
    root /var/www/flirt-ai/frontend/dist;
    index index.html;

    # Frontend routes
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Backend API
    location /api {
        try_files $uri $uri/ /backend/public/index.php?$query_string;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root/backend/public/index.php;
        include fastcgi_params;
        
        # CORS headers
        add_header 'Access-Control-Allow-Origin' '$http_origin' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Authorization, Content-Type' always;
        
        if ($request_method = 'OPTIONS') {
            return 204;
        }
    }

    # PHP files security
    location ~ \.php$ {
        deny all;
    }

    # Static files caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

Сохраните: `Ctrl+O`, `Enter`, `Ctrl+X`

### 5.2 Активация конфигурации

```bash
ln -s /etc/nginx/sites-available/flirt-ai /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default  # Удалить дефолтный сайт
nginx -t  # Проверить конфигурацию
systemctl reload nginx
```

---

## Шаг 6: Настройка PHP-FPM

### 6.1 Редактирование конфигурации

```bash
nano /etc/php/8.2/fpm/php.ini
```

Найдите и измените:
```ini
upload_max_filesize = 10M
post_max_size = 10M
memory_limit = 256M
```

### 6.2 Редактирование пула PHP-FPM

```bash
nano /etc/php/8.2/fpm/pool.d/www.conf
```

Убедитесь, что:
```ini
user = www-data
group = www-data
listen = /var/run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
```

Перезапустите PHP-FPM:
```bash
systemctl restart php8.2-fpm
```

---

## Шаг 7: Настройка переменных окружения

### 7.1 Backend .env

```bash
cd /var/www/flirt-ai/backend
nano .env
```

**Важно**: Перед настройкой `.env` файла, настройте Cloud.ru AI API (см. [CLOUDRU_SETUP.md](./CLOUDRU_SETUP.md))

Вставьте:

```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=flirt_ai
DB_USER=ai_user
DB_PASSWORD=YOUR_STRONG_PASSWORD

# VK OAuth
VK_APP_ID=your_vk_app_id
VK_APP_SECRET=your_vk_app_secret
VK_REDIRECT_URI=https://YOUR_DOMAIN.com/vk-callback.php

# JWT
JWT_SECRET=GENERATE_RANDOM_32_CHARS_MINIMUM_SECRET_KEY_HERE

# Cloud.ru AI
CLOUDRU_API_KEY=your_cloudru_api_key
CLOUDRU_FOLDER_ID=your_folder_id
CLOUDRU_MODEL=qwen3-235b-a22b-instruct-2507

# YooMoney
YOOMONEY_RECEIVER=410011234567890
YOOMONEY_SECRET=

# Frontend URL
FRONTEND_URL=https://YOUR_DOMAIN.com
```

**Важно**: Сгенерируйте безопасный JWT_SECRET:
```bash
openssl rand -base64 32
```

### 7.2 Frontend .env

```bash
cd /var/www/flirt-ai/frontend
nano .env
```

Вставьте:

```env
VITE_API_URL=https://YOUR_DOMAIN.com
```

---

## Шаг 8: Установка зависимостей

### 8.1 Backend зависимости

```bash
cd /var/www/flirt-ai/backend
composer install --no-dev --optimize-autoloader
```

### 8.2 Frontend зависимости и сборка

```bash
cd /var/www/flirt-ai/frontend
npm install
npm run build
```

---

## Шаг 9: Настройка SSL (Let's Encrypt)

### 9.1 Установка Certbot

```bash
apt install -y certbot python3-certbot-nginx
```

### 9.2 Получение сертификата

```bash
certbot --nginx -d YOUR_DOMAIN.com -d www.YOUR_DOMAIN.com
```

Следуйте инструкциям:
- Email: ваш email
- Согласие с условиями: **Y**
- Редирект HTTP на HTTPS: **2** (рекомендуется)

### 9.3 Автоматическое обновление

Certbot автоматически настроит cron для обновления сертификатов.

Проверьте:
```bash
certbot renew --dry-run
```

---

## Шаг 10: Настройка GitHub Actions

### 10.1 Создание SSH ключа для деплоя

На сервере:

```bash
ssh-keygen -t ed25519 -C "github-actions" -f ~/.ssh/github_actions -N ""
cat ~/.ssh/github_actions.pub >> ~/.ssh/authorized_keys
cat ~/.ssh/github_actions  # Скопируйте приватный ключ
```

### 10.2 Добавление Secrets в GitHub

1. Перейдите в ваш репозиторий на GitHub
2. Settings → Secrets and variables → Actions
3. Добавьте следующие secrets:

   - **SERVER_HOST**: IP адрес вашего сервера (например: `123.45.67.89`)
   - **SERVER_USER**: пользователь для SSH (обычно `root`)
   - **SERVER_PATH**: путь на сервере (например: `/var/www/flirt-ai`)
   - **SSH_KEY**: приватный SSH ключ (содержимое `~/.ssh/github_actions`)

### 10.3 Обновление workflow файла

Убедитесь, что файл `.github/workflows/deploy.yml` содержит правильные пути.

---

## Шаг 11: Настройка VK OAuth

### 11.1 Создание приложения VK

1. Перейдите на [VK Developers](https://dev.vk.com/)
2. Создайте новое приложение:
   - Тип: **Веб-сайт**
   - Название: AI Ассистент
3. Получите **App ID** и **App Secret**
4. В настройках приложения добавьте:
   - **Redirect URI**: `https://YOUR_DOMAIN.com/vk-callback.php`
   - **Доверенный redirect URI**: `https://YOUR_DOMAIN.com/vk-callback.php`

### 11.2 Обновление .env

Добавьте полученные значения в `backend/.env`:
```env
VK_APP_ID=your_app_id
VK_APP_SECRET=your_app_secret
```

---

## Шаг 12: Настройка ЮMoney

### 12.1 Регистрация кошелька

1. Зарегистрируйтесь на [yoomoney.ru](https://yoomoney.ru)
2. Создайте кошелёк
3. Получите номер кошелька (формат: `410011234567890`)

### 12.2 Настройка приёма платежей

1. В настройках кошелька включите "Приём платежей"
2. Добавьте номер кошелька в `backend/.env`:
```env
YOOMONEY_RECEIVER=410011234567890
```

---

## Шаг 13: Первый деплой через GitHub Actions

### 13.1 Push в main ветку

```bash
git add .
git commit -m "Initial deployment setup"
git push origin main
```

### 13.2 Проверка деплоя

1. Перейдите в GitHub → Actions
2. Дождитесь завершения workflow "Deploy"
3. Проверьте логи на ошибки

### 13.3 Ручной деплой (если нужно)

Если GitHub Actions не работает, выполните вручную:

```bash
# На сервере
cd /var/www/flirt-ai
git pull origin main

# Backend
cd backend
composer install --no-dev --optimize-autoloader

# Frontend
cd ../frontend
npm install
npm run build

# Права
cd ..
chown -R www-data:www-data /var/www/flirt-ai
chmod -R 755 /var/www/flirt-ai

# Перезапуск
systemctl reload php8.2-fpm
systemctl reload nginx
```

---

## Шаг 14: Проверка работоспособности

### 14.1 Проверка сервисов

```bash
systemctl status nginx
systemctl status php8.2-fpm
systemctl status mysql
```

### 14.2 Проверка сайта

1. Откройте в браузере: `https://YOUR_DOMAIN.com`
2. Проверьте главную страницу
3. Попробуйте авторизацию через VK
4. Проверьте API: `https://YOUR_DOMAIN.com/api/profile` (должна быть ошибка 401 без токена)

### 14.3 Проверка логов

```bash
# Nginx
tail -f /var/log/nginx/flirt-ai-error.log

# PHP-FPM
tail -f /var/log/php8.2-fpm.log

# MySQL
tail -f /var/log/mysql/error.log
```

---

## Шаг 15: Настройка файрвола

### 15.1 UFW (если установлен)

```bash
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
```

### 15.2 Cloud.ru Security Groups

В панели cloud.ru:
1. Перейдите в "Сети" → "Группы безопасности"
2. Создайте или отредактируйте группу для вашей VM
3. Добавьте правила:
   - **HTTP (80)**: входящий, все источники
   - **HTTPS (443)**: входящий, все источники
   - **SSH (22)**: входящий, только ваш IP (для безопасности)

---

## 🔧 Полезные команды для обслуживания

### Перезапуск сервисов

```bash
systemctl restart nginx
systemctl restart php8.2-fpm
systemctl restart mysql
```

### Просмотр логов

```bash
# Nginx access
tail -f /var/log/nginx/flirt-ai-access.log

# Nginx errors
tail -f /var/log/nginx/flirt-ai-error.log

# PHP errors
tail -f /var/log/php8.2-fpm.log
```

### Обновление проекта

```bash
cd /var/www/flirt-ai
git pull origin main
cd backend && composer install --no-dev
cd ../frontend && npm install && npm run build
cd .. && chown -R www-data:www-data .
systemctl reload php8.2-fpm && systemctl reload nginx
```

### Резервное копирование БД

```bash
mysqldump -u ai_user -p flirt_ai > backup_$(date +%Y%m%d).sql
```

---

## 🐛 Решение проблем

### Проблема: 502 Bad Gateway

**Решение:**
```bash
# Проверить PHP-FPM
systemctl status php8.2-fpm
systemctl restart php8.2-fpm

# Проверить сокет
ls -la /var/run/php/php8.2-fpm.sock
```

### Проблема: 403 Forbidden

**Решение:**
```bash
# Проверить права
chown -R www-data:www-data /var/www/flirt-ai
chmod -R 755 /var/www/flirt-ai
```

### Проблема: База данных не подключается

**Решение:**
```bash
# Проверить подключение
mysql -u ai_user -p flirt_ai

# Проверить .env файл
cat /var/www/flirt-ai/backend/.env | grep DB_
```

### Проблема: CORS ошибки

**Решение:**
- Проверьте `FRONTEND_URL` в `backend/.env`
- Убедитесь, что в Nginx конфигурации правильно настроены CORS headers

---

## ✅ Чек-лист готовности

- [ ] Сервер создан и доступен по SSH
- [ ] Установлены PHP 8.2, MySQL, Nginx, Node.js, Composer
- [ ] База данных создана и схема импортирована
- [ ] Nginx настроен и работает
- [ ] SSL сертификат установлен (Let's Encrypt)
- [ ] Backend .env настроен со всеми ключами
- [ ] Frontend .env настроен
- [ ] Зависимости установлены (composer, npm)
- [ ] Frontend собран (npm run build)
- [ ] GitHub Actions secrets настроены
- [ ] VK OAuth приложение создано и настроено
- [ ] ЮMoney кошелёк настроен
- [ ] Сайт открывается по HTTPS
- [ ] Авторизация через VK работает
- [ ] API endpoints отвечают

---

## 📞 Поддержка

При возникновении проблем:
1. Проверьте логи (см. раздел "Просмотр логов")
2. Проверьте статус сервисов: `systemctl status`
3. Проверьте конфигурацию Nginx: `nginx -t`
4. Проверьте права на файлы: `ls -la /var/www/flirt-ai`

---

**Готово!** Ваш проект должен быть доступен по адресу `https://YOUR_DOMAIN.com`


