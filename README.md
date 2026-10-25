# Decorron

Уеб приложение за **декорация на събития** — балонени арки, фотозони, украса за рождени дни, сватби и други.

Покрива целия път от първия контакт до изпълнената работа:

- **Ценообразуване** — въвеждате материали, труд и желан чист марж; приложението изчислява крайната цена за клиента, включително корпоративен данък и данък върху дивидента, и показва пълна разбивка на разходите и печалбата.
- **CRM** — клиенти, запитвания, оферти с версии, поръчки, задачи и календар на събитията.

Основният приоритет е **скорост на работа от мобилен телефон**, докато говорите с клиента.

---

## Съдържание

- [Технологии](#технологии)
- [Модулна структура](#модулна-структура)
- [Основни секции](#основни-секции)
- [Локална инсталация](#локална-инсталация)
- [Настройка на MySQL](#настройка-на-mysql)
- [Миграции и seed](#миграции-и-seed)
- [Стартиране на dev сървъра](#стартиране-на-dev-сървъра)
- [Тестове](#тестове)
- [Форматиране и статични проверки](#форматиране-и-статични-проверки)
- [Production build](#production-build)
- [Deployment](#deployment)
- [Backup на базата данни](#backup-на-базата-данни)
- [Добавяне на нов модул](#добавяне-на-нов-модул)
- [Какво НЕ включва първата версия](#какво-не-включва-първата-версия)
- [Документация](#документация)

---

## Технологии

| Слой | Технология |
| --- | --- |
| Backend | Laravel 12 (PHP 8.2+) |
| Frontend | React 19 + TypeScript |
| Интеграция | Inertia.js 2 |
| Стилове | Tailwind CSS 4 |
| UI компоненти | shadcn/ui (Radix UI) |
| Икони | Lucide |
| База данни | MySQL 8 |
| Десетична аритметика | `brick/math` (BigDecimal) + MySQL `DECIMAL` |
| Toast съобщения | sonner |
| Тестове | PHPUnit 11 |
| Форматиране | Laravel Pint, Prettier, ESLint |

Няма отделен Node.js backend — Laravel обслужва и API-то, и изградените React активи.

---

## Модулна структура

Приложението е разделено на самостоятелни модули в `modules/`, за да може да расте (склад, график) без да се пипа ядрото.

```
modules/
├── Core/        Табло, настройки за ценообразуване, данни на бизнеса
├── Catalog/     Материали и мерни единици
├── Pricing/     Калкулации, финансов service, история
└── Crm/         Клиенти, запитвания, оферти, поръчки, задачи, календар
```

Всеки модул има еднаква вътрешна структура:

```
modules/<Модул>/
├── Database/
│   ├── Factories/      фабрики за тестове
│   ├── Migrations/     миграции (зареждат се автоматично)
│   └── Seeders/
├── Http/
│   ├── Controllers/
│   ├── Requests/       Form Request валидация
│   └── Resources/      API Resources
├── Models/
├── Policies/
├── Providers/<Модул>ServiceProvider.php
├── Resources/js/
│   ├── components/     React компоненти на модула
│   └── pages/          Inertia страници
├── Routes/web.php      маршрути (зареждат се автоматично)
└── Tests/              Feature и Unit тестове
```

- PHP класовете се автоматично зареждат през PSR-4: `Modules\<Модул>\…` → `modules/<Модул>/…`
- Inertia страниците се адресират с namespace: `Inertia::render('pricing::calculations/create')` → `modules/Pricing/Resources/js/pages/calculations/create.tsx`
- Общата основа (базов layout, UI примитиви, финансови помощници за frontend) е в `resources/js/`

---

## Основни секции

Приложението има три основни секции в менюто:

| Секция | Подопции | Адрес |
| --- | --- | --- |
| **Табло** | — | `/dashboard` |
| **CRM** | Табло, Клиенти, Запитвания, Оферти, Поръчки, Задачи, Календар | `/crm…` |
| **Ценообразуване** | Калкулатор, История, Материали, Мерни единици, Настройки | `/calculations…`, `/materials`, `/units`, `/settings/pricing` |

Настройките на профила (данни на бизнеса, профил, парола, външен вид) са извън трите секции — достъпни са от менюто на потребителя долу вляво, а на телефон от бутона „Още“.

Навигацията се описва на едно място: `resources/js/lib/navigation.ts`. Подопция с `ready: false` не се показва, за да не води менюто до незавършен екран.

Как се работи с приложението стъпка по стъпка: [`docs/USER_GUIDE.md`](docs/USER_GUIDE.md). Техническите правила на CRM процеса: [`docs/CRM.md`](docs/CRM.md).

---

## Локална инсталация

Изисквания: **PHP ≥ 8.2** с разширения `bcmath`, `pdo_mysql`, `mbstring`, `intl`; **Composer 2**; **Node.js ≥ 20**; **MySQL 8**.

```bash
git clone <адрес-на-хранилището> Decorron
cd Decorron

composer install
npm install

cp .env.example .env
php artisan key:generate
```

След това отворете `.env` и попълнете данните за връзка с MySQL.

---

## Настройка на MySQL

Създайте базата за приложението и базата за тестовете:

```sql
CREATE DATABASE decorron CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE decorron_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'decorron'@'localhost' IDENTIFIED BY 'сложна-парола';
GRANT ALL PRIVILEGES ON decorron.* TO 'decorron'@'localhost';
GRANT ALL PRIVILEGES ON decorron_test.* TO 'decorron'@'localhost';
FLUSH PRIVILEGES;
```

Съответните редове в `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=decorron
DB_USERNAME=decorron
DB_PASSWORD=сложна-парола
```

> `decorron_test` е необходима за тестовете — те работят срещу MySQL, а не срещу SQLite, защото финансовите изчисления зависят от поведението на `DECIMAL` колоните. Името е фиксирано в `phpunit.xml`.

---

## Миграции и seed

```bash
php artisan migrate
```

Seed-ът създава администраторски профил, стандартните мерни единици и няколко примерни материала:

```bash
ADMIN_EMAIL=вие@example.com ADMIN_PASSWORD='силна-парола' php artisan db:seed
```

В Windows PowerShell:

```powershell
$env:ADMIN_EMAIL='вие@example.com'; $env:ADMIN_PASSWORD='силна-парола'; php artisan db:seed
```

Ако `ADMIN_PASSWORD` не е подадена, seeder-ът генерира случайна парола и я показва **веднъж** в конзолата. В кода и в seed файловете няма записани пароли.

Пълно пресъздаване на базата (**изтрива всички данни**):

```bash
php artisan migrate:fresh --seed
```

---

## Стартиране на dev сървъра

В два отделни терминала:

```bash
php artisan serve
```

```bash
npm run dev
```

Приложението е на `http://localhost:8000`.

---

## Тестове

```bash
php artisan test
```

Само финансовата логика:

```bash
php artisan test --filter=PricingCalculatorTest
```

Само модулите:

```bash
php artisan test --testsuite=Modules
```

Само CRM:

```bash
php artisan test --filter="Modules\\\\Crm"
```

Тестовете покриват:

- **Ценообразуване** — единични цени, пълната калкулация, независимостта на историята от каталога и настройките, snapshot логиката, валидацията на гранични стойности;
- **CRM** — клиенти и откриване на дубликати, етапи и статуси на запитванията, хронология (автоматична срещу ръчна), свързване на калкулация с CRM без дублиране, версии на офертите, превръщане на приета оферта в поръчка, задачи и календар;
- **Общи** — изолация между потребители за всеки ресурс, защита на маршрутите и проверка, че всяка видима подопция в менюто води до реална страница.

---

## Форматиране и статични проверки

```bash
./vendor/bin/pint          # PHP форматиране
npm run format             # Prettier
npm run lint               # ESLint
npm run types              # TypeScript проверка (tsc --noEmit)
```

---

## Production build

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
```

Изградените активи се записват в `public/build` и се обслужват от Laravel.

---

## Deployment

Подробни инструкции за VPS и за споделен хостинг: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

> **GitHub Pages не може да хоства това приложение.** GitHub Pages обслужва само статични файлове — не изпълнява PHP и не хоства MySQL. Необходим е PHP хостинг или VPS. Вижте [`docs/GITHUB_PAGES_ALTERNATIVE.md`](docs/GITHUB_PAGES_ALTERNATIVE.md) за описание какво би трябвало да се промени, за да съществува отделна статична версия с IndexedDB.

Накратко за production:

1. PHP ≥ 8.2, MySQL 8, уеб сървър, насочен към `public/`.
2. `.env` с `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…` и `APP_REGISTRATION_ENABLED=false`, след като си създадете профил.
3. `php artisan key:generate` (веднъж) и права за запис върху `storage/` и `bootstrap/cache/`.
4. HTTPS.

---

## Backup на базата данни

```bash
mysqldump --single-transaction --routines --triggers \
  -u decorron -p decorron | gzip > decorron-$(date +%F).sql.gz
```

Възстановяване:

```bash
gunzip -c decorron-2026-10-24.sql.gz | mysql -u decorron -p decorron
```

Автоматично ежедневно архивиране чрез cron (03:15 ч.):

```cron
15 3 * * * /usr/bin/mysqldump --single-transaction -u decorron -p'парола' decorron | gzip > /var/backups/decorron/decorron-$(date +\%F).sql.gz
```

Пазете архивите извън сървъра и проверявайте периодично, че се възстановяват.

---

## Добавяне на нов модул

1. Създайте `modules/<Име>/` по структурата по-горе.
2. Напишете `Providers/<Име>ServiceProvider.php`, който наследява `App\Support\ModuleServiceProvider` и връща `dirname(__DIR__)` от `modulePath()`.
3. Добавете провайдъра в `bootstrap/providers.php`.
4. Маршрутите, миграциите и Inertia страниците се регистрират автоматично.

---

## Какво НЕ включва първата версия

Съзнателно извън обхвата, за да остане продуктът прост и бърз:

- фактуриране и счетоводство;
- отчитане на реални плащания (капарото се записва като договорена сума, но не се следят постъпления);
- складови наличности и движения на стоки;
- управление на персонал;
- изпращане на имейл, SMS или push известия — напомнянията живеят само в приложението;
- лого в клиентските документи (показват се текстови данни на бизнеса);
- автоматично преобразуване между мерни единици (напр. см. ↔ м.);
- автоматично включване на лични осигуровки в данъчния модел.

Архитектурата позволява добавянето им като отделни модули.

---

## Документация

- [`docs/FINANCIAL_MODEL.md`](docs/FINANCIAL_MODEL.md) — формулите, данъчните допускания и правилата за закръгляване
- [`docs/USER_GUIDE.md`](docs/USER_GUIDE.md) — ръководство за работа: целият процес и значението на всяко поле
- [`docs/CRM.md`](docs/CRM.md) — работният процес от запитване до изпълнена поръчка (технически)
- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — production deployment
- [`docs/GITHUB_PAGES_ALTERNATIVE.md`](docs/GITHUB_PAGES_ALTERNATIVE.md) — защо GitHub Pages не работи и каква би била алтернативата
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — модули, модел на данните и ключови решения

---

> ⚠️ **Важно:** Decorron използва опростен модел за ценообразуване и **не замества индивидуална счетоводна консултация**. Данъчният режим и основанията за регистрация по ДДС зависят от конкретните обстоятелства на фирмата.
