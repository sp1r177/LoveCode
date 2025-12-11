# 🚀 Быстрый старт - Деплой на cloud.ru

## Краткая инструкция (5 минут)

### 1. Подготовка сервера (выполнить один раз)

```bash
# Подключиться к серверу
ssh root@YOUR_SERVER_IP

# Установить всё необходимое
apt update && apt upgrade -y
apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl nginx mysql-server nodejs composer

# Создать БД
mysql -u root -p
CREATE DATABASE ai_assistant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ai_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON ai_assistant.* TO 'ai_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Создать директорию
mkdir -p /var/www/flirt-ai
chown -R www-data:www-data /var/www/flirt-ai
```

### 2. Настройка GitHub Secrets

В GitHub → Settings → Secrets → Actions добавить:

- `SERVER_HOST` = IP вашего сервера
- `SERVER_USER` = `root`
- `SERVER_PATH` = `/var/www/flirt-ai`
- `SSH_KEY` = приватный SSH ключ (см. DEPLOY.md шаг 10.1)

### 3. Первый деплой

```bash
# На сервере - клонировать репозиторий
cd /var/www/flirt-ai
git clone YOUR_REPO_URL .

# Импортировать схему БД
mysql -u ai_user -p ai_assistant < db/schema.sql

# Создать .env файлы (см. DEPLOY.md шаг 7)
# Настроить Nginx (см. DEPLOY.md шаг 5)
# Настроить SSL (см. DEPLOY.md шаг 9)
```

### 4. Автоматический деплой

После настройки GitHub Secrets, каждый push в `main` автоматически деплоит проект.

---

**Полная инструкция**: см. [DEPLOY.md](./DEPLOY.md)


