# Деплой на Amvera

Проект настроен для деплоя на Amvera с использованием Dockerfile.

## Что было создано

- `Dockerfile` - основной файл для сборки Docker образа
- `amvera.yaml` - конфигурационный файл для Amvera
- `nginx.conf` - конфигурация веб-сервера Nginx
- `start.sh` - скрипт запуска PHP-FPM и Nginx
- `.dockerignore` - исключения для Docker сборки

## Настройка переменных окружения в Amvera

После создания приложения в Amvera необходимо настроить следующие переменные окружения:

### База данных
- `DB_HOST` - хост базы данных
- `DB_PORT` - порт базы данных (обычно 3306)
- `DB_NAME` - имя базы данных
- `DB_USER` - пользователь базы данных
- `DB_PASSWORD` - пароль базы данных

### VK OAuth
- `VK_APP_ID` - ID приложения VK
- `VK_APP_SECRET` - секретный ключ приложения VK
- `VK_REDIRECT_URI` - URI для редиректа (например: `https://your-domain.amvera.io/vk-callback.php`)

### JWT
- `JWT_SECRET` - секретный ключ для JWT (минимум 32 символа)

### Cloud.ru AI
- `CLOUDRU_API_KEY` - API ключ Cloud.ru
- `CLOUDRU_FOLDER_ID` - ID папки в Cloud.ru
- `CLOUDRU_MODEL` - модель AI (например: `qwen3-235b-a22b-instruct-2507`)

### YooMoney
- `YOOMONEY_RECEIVER` - номер кошелька ЮMoney
- `YOOMONEY_SECRET` - секретный ключ (если используется)

### Frontend
- `FRONTEND_URL` - URL фронтенда (например: `https://your-domain.amvera.io`)

## Процесс деплоя

1. Создайте приложение в Amvera
2. Подключите репозиторий GitHub
3. Amvera автоматически обнаружит Dockerfile в корне проекта
4. Настройте переменные окружения (см. выше)
5. Запустите сборку

## Архитектура

Dockerfile использует multi-stage build:
1. **Frontend builder** - собирает React приложение в статические файлы
2. **Production image** - содержит PHP 8.2, Nginx и собранные файлы

Nginx обслуживает:
- Статические файлы фронтенда из `/var/www/html/frontend/dist`
- API запросы проксируются к PHP-FPM через `/api` endpoint

## Проблемы и решения

### Ошибка сборки
- Убедитесь, что все файлы находятся в правильных местах
- Проверьте логи сборки в панели Amvera

### Ошибка подключения к БД
- Проверьте переменные окружения `DB_*`
- Убедитесь, что база данных доступна из контейнера

### CORS ошибки
- Проверьте `FRONTEND_URL` в переменных окружения
- Убедитесь, что домен совпадает с реальным URL приложения

### Ошибка доступа к VK ID OAuth
- Проверьте доступность публичных endpoint'ов:
  - `curl -i https://your-domain.amvera.io/vkid.php`
  - `curl -i https://your-domain.amvera.io/vk-callback.php`



