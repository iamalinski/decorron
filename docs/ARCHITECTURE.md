# Архитектура

## Защо модули

Приложението расте — CRM вече е отделен модул, предстоят склад и график. За да не се превърне в едно голямо `app/`, всяка област живее в собствена папка под `modules/` със свой provider, маршрути, миграции, модели и frontend.

Избрано е **собствено, минимално решение** вместо готов пакет за модули: цялата инфраструктура е един абстрактен клас (`app/Support/ModuleServiceProvider.php`, ~60 реда) и един ред в `composer.json`. Няма допълнителна зависимост, няма скрити конвенции и няма команда, която да се чупи при обновяване на Laravel.

### Какво прави `ModuleServiceProvider`

| Автоматично                    | Как                                                       |
| ------------------------------ | --------------------------------------------------------- |
| Миграции                       | `loadMigrationsFrom(<Модул>/Database/Migrations)`         |
| Маршрути                       | `Route::middleware('web')->group(<Модул>/Routes/web.php)` |
| Inertia namespace за тестовете | регистрира `<име>::` в Inertia view finder-а              |

Политиките и event listener-ите се регистрират в `bootModule()` на съответния модул.

### Разрешаване на Inertia страници

`resources/js/lib/resolve-page.ts` обработва две имена:

```
"welcome"                       → resources/js/pages/welcome.tsx
"pricing::calculations/create"  → modules/Pricing/Resources/js/pages/calculations/create.tsx
```

Глобовете на Vite обхождат и двете дървета, така че нов модул не изисква промяна във файла.

---

## Модули

### Core

Табло и потребителски настройки за ценообразуване (`user_settings`). Тук съзнателно **няма** данъчни ставки.

### Catalog

Материали и мерни единици. Лек продуктов каталог, не складова система: няма наличности, доставки и движения.

- Мерните единици **не се изтриват**, а се архивират (`is_active = false`), защото материалите сочат към тях с `RESTRICT`.
- Материалите се архивират (`is_active = false`) или се изтриват. Изтриването е окончателно, когато никой ред от калкулация не сочи към материала, и меко (`deleted_at`) в противен случай — вж. по-долу.
- Нов потребител получава стандартния набор мерни единици чрез listener на `Registered`.

### Pricing

Калкулации, история и финансовият service.

```
PricingCalculator      чиста функция: вход → резултат; без база данни
CalculationRecorder    записва калкулация + редове в една транзакция, прави snapshot-ите
TaxRates               immutable обвивка около config/pricing.php
```

`PricingCalculator` не знае за Eloquent и за HTTP — затова се тества директно и бързо.

### Crm

Клиенти, запитвания, оферти, поръчки, задачи и календар. Подробностите за работния процес са в [`CRM.md`](CRM.md).

```
ActivityRecorder     единствената точка, през която се пише в хронологията
CalculationLinker    свързва записана калкулация с клиент и запитване
QuoteWriter          суми, версии и статус на офертите
OrderWriter          поръчки от оферта или директно, без дублиране
```

**Зависимости между модулите.** `Crm` чете `Pricing` (калкулации в офертите и по запитванията), а `Pricing` чете `Crm` (клиентът на калкулацията). Връзката е съзнателна и минимална: колоните `calculations.customer_id` и `calculations.crm_opportunity_id` се създават от **миграция на Crm**, защото Crm притежава двете таблици, към които сочат. Ако CRM бъде премахнат, калкулаторът продължава да работи — колоните са nullable.

---

## Навигация

Трите основни секции (Табло, CRM, Ценообразуване) се описват на едно място — `resources/js/lib/navigation.ts`. Оттам се захранват и страничното меню, и долната лента на телефон, и лентата с подопции.

Подопция с `ready: false` **не се показва** — менюто никога не води до празен или фиктивен екран. `modules/Core/Tests/Feature/NavigationTest.php` проверява, че всеки показан адрес връща `200` за влязъл потребител и пренасочва към `/login` за гост.

---

## Radix слоеве (диалози, popover, select)

Radix пази регистър на отворените „слоеве“ в **модулна** променлива вътре в `@radix-ui/react-dismissable-layer`. Модалният диалог вдига `pointer-events: none` на `<body>` и връща `auto` само на слоевете над себе си.

Ако npm инсталира **две копия** на този пакет, всяко има собствен регистър и слоевете спират да се виждат помежду си. Popover, отворен вътре в диалог, тогава не получава `pointer-events: auto`: изглежда нормално, но всяко кликване минава под него, попада в диалога отзад и затваря менюто. Точно това се случваше с избора на клиент в „Задачи“ и „Запитвания“.

Затова `package.json` съдържа блок `overrides`, който заковава по една версия на трите пакета със споделено състояние:

```json
"overrides": {
  "@radix-ui/react-dismissable-layer": "1.2.1",
  "@radix-ui/react-focus-guards": "1.2.0",
  "@radix-ui/react-focus-scope": "1.3.1"
}
```

`npm run check:radix` ([`scripts/check-radix-layers.mjs`](../scripts/check-radix-layers.mjs)) проверява, че копията наистина са по едно. Пуснете го след всяко обновяване на Radix — грешката е тиха и не се вижда нито от TypeScript, нито от тестовете.

---

## Модел на данните

```
users
 ├── units                (user_id)
 ├── materials            (user_id, unit_id → units, RESTRICT)
 ├── user_settings        (user_id, уникален)
 ├── calculations         (user_id)
 │    ├── calculation_items  (calculation_id, CASCADE)
 │    │    ├── material_id → materials, SET NULL
 │    │    └── unit_id     → units,     SET NULL
 │    ├── customer_id        → customers,         SET NULL
 │    └── crm_opportunity_id → crm_opportunities, SET NULL
 └── customers            (user_id)
      ├── crm_opportunities  (customer_id, RESTRICT)
      │    └── crm_activity_logs (opportunity_id, CASCADE)
      ├── quotes             (customer_id, RESTRICT)
      │    ├── quote_items    (quote_id, CASCADE)
      │    └── quote_versions (quote_id, CASCADE)
      ├── orders             (customer_id, RESTRICT; quote_id SET NULL)
      │    └── order_items   (order_id, CASCADE)
      └── crm_tasks          (всички връзки SET NULL)
```

CRM таблиците са описани подробно в [`CRM.md`](CRM.md).

### Ключови решения

**Snapshot на всичко.** `calculation_items` пази `material_name_snapshot`, `sku_snapshot`, `unit_name_snapshot`, `unit_symbol_snapshot`, `quantity`, `input_mode`, `entered_total_cost`, `unit_price` и `line_total`. `calculations` пази и трите данъчни ставки, използвания марж и всички изчислени суми. Промяна в каталога, в настройките или в `config/pricing.php` **не** променя записана калкулация.

**Връзките към каталога са информативни.** `material_id` и `unit_id` са `nullable` с `SET NULL`, за да остане редът четим дори ако записът изчезне. Четимостта идва от snapshot-ите, не от връзките.

**`unit_id` в `materials` е `RESTRICT`.** Мерна единица, която се използва от материал, не може да бъде изтрита — само архивирана.

**Пари в `DECIMAL`.** `DECIMAL(14,2)` за суми, `DECIMAL(18,6)` за единични цени и количества, `DECIMAL(9,6)` за проценти. В PHP аритметиката е `brick/math` `BigDecimal`. Подробности: [`FINANCIAL_MODEL.md`](FINANCIAL_MODEL.md).

**Кошче вместо твърдо изтриване.** `customers`, `crm_opportunities`, `quotes`, `orders`, `crm_tasks` и `calculations` имат `deleted_at` (`SoftDeletes`). Трейтът `App\Concerns\Trashable` добавя към това веригата на изтриване: `deletionBlockers()` казва кои **активни** записи пречат на мекото изтриване и кои (включително от Кошчето) пречат на окончателното, а `restoreBlockers()` не допуска дете да излезе от Кошчето преди родителя си. Контролерите ползват `App\Http\Controllers\Concerns\TrashesRecords`, за да не повтарят проверките. Правилата са описани в [`CRM.md`](CRM.md#кошче-и-изтриване).

`materials` също има `deleted_at`, но се държи различно от веригата в CRM: материалът никога не блокира изтриването (`deletionBlockers()` връща празен масив), защото нито един запис не зависи от него, за да бъде четим — снимките в `calculation_items` носят името, кода и цените. Затова `MaterialController::destroy()` избира сам между двете: `forceDelete()`, ако `usageCount() === 0`, и `delete()`, ако материалът още участва в записани редове. Така каталогът се чисти от грешки веднага, а използваните материали остават в Кошчето и могат да бъдат върнати.

`units` съзнателно **няма** Кошче — там архивирането е единственият безопасен вариант заради `RESTRICT` връзката от `materials`.
**Изолация между потребителите.** Всички основни таблици имат `user_id`. Трейтът `App\Concerns\OwnedByUser` дава `scopeOwnedBy()` и попълва `user_id` при създаване; `user_id` не е `fillable`, затова не може да бъде подменен от заявка. Политиките правят втора проверка върху вече заредените модели (route model binding).

---

## Поток на една калкулация

```
Екран „Нова калкулация“ (React)
   │  lib/pricing.ts — моментално изчисление, само за показване
   ▼
POST /calculations
   │  StoreCalculationRequest  — валидация, включително границата на маржа
   ▼
PricingCalculator::calculate()  — backend-ът изчислява наново, игнорира цените от клиента
   ▼
CalculationRecorder::record()   — транзакция: калкулация + редове + snapshot-и
   ▼
redirect → /calculations/{id}
```

Frontend-ът и backend-ът използват **една и съща формула**, реализирана два пъти: `resources/js/lib/pricing.ts` и `modules/Pricing/Services/PricingCalculator.php`. Дублирането е съзнателно — така екранът реагира моментално, без заявка при всеки натиснат клавиш. При промяна на формулата двата файла се обновяват заедно; тестовете на PHP версията са източникът на истина.

---

## Сигурност

| Мярка                            | Къде                                                                                              |
| -------------------------------- | ------------------------------------------------------------------------------------------------- |
| Автентикация                     | Laravel сесии; всички маршрути на модулите са зад `auth`                                          |
| Регистрацията може да се изключи | `APP_REGISTRATION_ENABLED=false` + `EnsureRegistrationIsEnabled`                                  |
| CSRF                             | middleware групата `web`; JSON заявките пращат `X-CSRF-TOKEN` от `<meta>`                         |
| Масово присвояване               | `$fillable` е изричен; `user_id` никога не е fillable                                             |
| Достъп до чужди данни            | `scopeOwnedBy()` + политики; `Rule::exists(...)->where('user_id', …)` при избор на материал/мярка |
| Данъчни ставки                   | само в `config/pricing.php`; няма маршрут, който да ги променя                                    |
| Грешки                           | `APP_DEBUG=false` в production; потребителят вижда съобщения, не stack traces                     |
| Двойно записване                 | ключ за идемпотентност + заключен бутон, докато заявката тече                                     |

---

## Производителност

- `with()` / `withCount()` навсякъде, където се показват връзки — няма N+1 заявки.
- Историята и каталогът се пагинират от backend-а.
- Екранът за калкулация зарежда само 50 материала; останалите се търсят с autocomplete (debounce 250 ms, прекъсване на предишната заявка).
- Същото важи за избора на клиент в CRM и в калкулатора.
- Индекси върху `(user_id, …)` за всички филтри и сортирания.
- Kanban изгледът на запитванията тегли най-много 200 отворени записа; списъчният изглед се пагинира.
- Активите се разделят на chunk-ове от Vite; страниците се зареждат при нужда.
