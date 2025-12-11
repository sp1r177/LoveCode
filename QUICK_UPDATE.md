# ⚡ Быстрое обновление (5 минут)

## На вашем компьютере:

```bash
cd C:\Users\sp1r1\.cursor\LoveCode\LoveCode-1
git add .
git commit -m "Migrate to Cloud.ru AI"
git push origin main
```

## На сервере (после автоматического деплоя):

```bash
ssh root@YOUR_SERVER_IP
cd /var/www/flirt-ai/backend

# Обновите .env файл
nano .env
```

**Удалите:**
```
OPENAI_API_KEY=...
OPENAI_MODEL=...
```

**Добавьте:**
```
CLOUDRU_API_KEY=ваш_ключ_из_cloud_ru
CLOUDRU_FOLDER_ID=ваш_folder_id
CLOUDRU_MODEL=qwen3-235b-a22b-instruct-2507
```

**Сохраните** (Ctrl+O, Enter, Ctrl+X)

```bash
# Перезапустите сервисы
systemctl restart php8.2-fpm
systemctl reload nginx
```

## Проверка:

Откройте сайт и попробуйте проанализировать переписку.

---

**Полная инструкция**: см. [UPDATE_GUIDE.md](./UPDATE_GUIDE.md)

