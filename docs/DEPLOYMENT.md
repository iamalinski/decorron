# Deployment

Decorron е Laravel приложение с MySQL. За production е нужен **PHP хостинг или VPS**, не статичен хостинг.

> **GitHub Pages не работи за това приложение** — той обслужва само статични файлове и не изпълнява PHP, нито хоства MySQL. Вижте [`GITHUB_PAGES_ALTERNATIVE.md`](GITHUB_PAGES_ALTERNATIVE.md).

---

## 1. Публикуване в GitHub

Преди първия `push` проверете, че няма секрети:

```bash
git status --ignored | grep -i "\.env$"     # .env трябва да е игнориран
git grep -nI -e "DB_PASSWORD=" -e "APP_KEY=base64" -- ':!.env.example'
```

```bash
git init
git add .
git commit -m "Decorron — първоначална версия"
git branch -M main
git remote add origin git@github.com:<потребител>/decorron.git
git push -u origin main
```

В хранилището влизат `.env.example`, `.gitignore` и целият код. **Не влизат** `.env`, `vendor/`, `node_modules/`, `public/build/`, пароли и ключове.

---

## 2. Изисквания на сървъра

| Компонент | Версия |
| --- | --- |
| PHP | ≥ 8.2 |
| PHP разширения | `bcmath`, `pdo_mysql`, `mbstring`, `intl`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `curl` |
| MySQL | 8.0+ (или MariaDB 10.6+) |
| Composer | 2.x |
| Node.js | ≥ 20 — само за билда; може и локално/в CI |
| Уеб сървър | Nginx или Apache, document root → `public/` |

`bcmath` е задължително — `brick/math` го използва за точната decimal аритметика.

---

## 3. VPS (Ubuntu + Nginx) — стъпка по стъпка

### 3.1. Пакети

```bash
sudo apt update
sudo apt install -y nginx mysql-server php8.3-fpm php8.3-mysql php8.3-bcmath \
  php8.3-mbstring php8.3-xml php8.3-intl php8.3-curl php8.3-zip unzip git
```

### 3.2. База данни

```bash
sudo mysql
```

```sql
CREATE DATABASE decorron CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'decorron'@'localhost' IDENTIFIED BY 'силна-парола';
GRANT ALL PRIVILEGES ON decorron.* TO 'decorron'@'localhost';
FLUSH PRIVILEGES;
```

### 3.3. Код

```bash
sudo mkdir -p /var/www/decorron
sudo chown -R $USER:www-data /var/www/decorron
git clone git@github.com:<потребител>/decorron.git /var/www/decorron
cd /var/www/decorron

composer install --no-dev --optimize-autoloader
npm ci && npm run build        # или качете предварително изградения public/build
```

### 3.4. Конфигурация

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

```dotenv
APP_NAME=Decorron
APP_ENV=production
APP_DEBUG=false
APP_URL=https://decorron.example.com
APP_TIMEZONE=Europe/Sofia
APP_LOCALE=bg

# Изключете регистрацията веднага след като създадете профила си.
APP_REGISTRATION_ENABLED=false

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=decorron
DB_USERNAME=decorron
DB_PASSWORD=силна-парола

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
LOG_LEVEL=warning
```

### 3.5. Миграции и първи потребител

```bash
php artisan migrate --force
ADMIN_EMAIL=вие@example.com ADMIN_PASSWORD='силна-парола' php artisan db:seed --force
```

Запишете паролата в мениджър за пароли и **изтрийте `ADMIN_*` променливите от `.env`**, ако сте ги добавили там.

### 3.6. Права

```bash
sudo chown -R www-data:www-data /var/www/decorron/storage /var/www/decorron/bootstrap/cache
sudo chmod -R 775 /var/www/decorron/storage /var/www/decorron/bootstrap/cache
```

### 3.7. Кеширане

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> След всяка промяна в `.env` или в конфигурацията изпълнете `php artisan config:clear && php artisan config:cache`.

### 3.8. Nginx

```nginx
server {
    listen 80;
    server_name decorron.example.com;
    root /var/www/decorron/public;

    index index.php;
    charset utf-8;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "same-origin";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    client_max_body_size 8M;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/decorron /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 3.9. HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d decorron.example.com
```

### 3.10. Планировчик (по желание)

```cron
* * * * * cd /var/www/decorron && php artisan schedule:run >> /dev/null 2>&1
```

---

## 4. Apache

Ако използвате Apache, document root също сочи към `public/`, а `mod_rewrite` трябва да е включен. Laravel вече съдържа `public/.htaccess`.

```apache
<VirtualHost *:80>
    ServerName decorron.example.com
    DocumentRoot /var/www/decorron/public

    <Directory /var/www/decorron/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

## 5. Споделен хостинг (cPanel и подобни)

Много споделени хостинги не позволяват document root извън `public_html`. Тогава:

1. Качете проекта в `~/decorron` (извън `public_html`).
2. Копирайте съдържанието на `public/` в `public_html/`.
3. В `public_html/index.php` поправете двата пътя:

```php
require __DIR__.'/../decorron/vendor/autoload.php';
$app = require_once __DIR__.'/../decorron/bootstrap/app.php';
```

4. Изпълнете `composer install --no-dev` и `php artisan migrate --force` през SSH или през терминала на хостинга.
5. Изградете активите локално (`npm run build`) и качете `public/build`.

Ако хостингът няма SSH, подгответе `vendor/` и `public/build` локално и ги качете по FTP.

---

## 6. Обновяване

```bash
cd /var/www/decorron
php artisan down

git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up
```

---

## 7. Чеклист за сигурност преди пускане

- [ ] `APP_DEBUG=false` и `APP_ENV=production`
- [ ] `APP_KEY` е генериран и е уникален за този сървър
- [ ] `.env` не е в Git и не е достъпен по HTTP (document root е `public/`)
- [ ] `APP_REGISTRATION_ENABLED=false` след създаването на профилите
- [ ] HTTPS с валиден сертификат, `SESSION_SECURE_COOKIE=true`
- [ ] MySQL потребителят има права само върху базата `decorron`
- [ ] `storage/` и `bootstrap/cache/` са записваеми само за уеб потребителя
- [ ] Настроен автоматичен backup на базата (вж. README)
- [ ] Конфигуриран firewall; MySQL слуша само на `127.0.0.1`
- [ ] `php artisan about` не показва неочаквани настройки
