# Архитектура

## Защо модули

Приложението ще расте — предвидени са CRM, склад и график. За да не се превърне в едно голямо `app/`, всяка област живее в собствена папка под `modules/` със свой provider, маршрути, миграции, модели и frontend.

Избрано е **собствено, минимално решение** вместо готов пакет за модули: цялата инфраструктура е един абстрактен клас (`app/Support/ModuleServiceProvider.php`, ~60 реда) и един ред в `composer.json`. Няма допълнителна зависимост, няма скрити конвенции и няма команда, която да се чупи при обновяване на Laravel.

### Какво прави `ModuleServiceProvider`

| Автоматично | Как |
| --- | --- |
| Миграции | `loadMigrationsFrom(<Модул>/Database/Migrations)` |
| Маршрути | `Route::middleware('web')->group(<Модул>/Routes/web.php)` |
| Inertia namespace за тестовете | регистрира `<име>::` в Inertia view finder-а |

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

- Материалите и мерните единици **не се изтриват**, а се архивират (`is_active = false`), за да не се повредят историческите калкулации.
- Нов потребител получава стандартния набор мерни единици чрез listener на `Registered`.

### Pricing

Калкулации, история и финансовият service.

```
PricingCalculator      чиста функция: вход → резултат; без база данни
CalculationRecorder    записва калкулация + редове в една транзакция, прави snapshot-ите
TaxRates               immutable обвивка около config/pricing.php
```

`PricingCalculator` не знае за Eloquent и за HTTP — затова се тества директно и бързо.

---

## Модел на данните

```
users
 ├── units                (user_id)
 ├── materials            (user_id, unit_id → units, RESTRICT)
 ├── user_settings        (user_id, уникален)
 └── calculations         (user_id)
      └── calculation_items  (calculation_id, CASCADE)
           ├── material_id → materials, SET NULL
           └── unit_id     → units,     SET NULL
```

### Ключови решения

**Snapshot на всичко.** `calculation_items` пази `material_name_snapshot`, `sku_snapshot`, `unit_name_snapshot`, `unit_symbol_snapshot`, `quantity`, `input_mode`, `entered_total_cost`, `unit_price` и `line_total`. `calculations` пази и трите данъчни ставки, използвания марж и всички изчислени суми. Промяна в каталога, в настройките или в `config/pricing.php` **не** променя записана калкулация.

**Връзките към каталога са информативни.** `material_id` и `unit_id` са `nullable` с `SET NULL`, за да остане редът четим дори ако записът изчезне. Четимостта идва от snapshot-ите, не от връзките.

**`unit_id` в `materials` е `RESTRICT`.** Мерна единица, която се използва от материал, не може да бъде изтрита — само архивирана.

**Пари в `DECIMAL`.** `DECIMAL(14,2)` за суми, `DECIMAL(18,6)` за единични цени и количества, `DECIMAL(9,6)` за проценти. В PHP аритметиката е `brick/math` `BigDecimal`. Подробности: [`FINANCIAL_MODEL.md`](FINANCIAL_MODEL.md).

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

| Мярка | Къде |
| --- | --- |
| Автентикация | Laravel сесии; всички маршрути на модулите са зад `auth` |
| Регистрацията може да се изключи | `APP_REGISTRATION_ENABLED=false` + `EnsureRegistrationIsEnabled` |
| CSRF | middleware групата `web`; JSON заявките пращат `X-CSRF-TOKEN` от `<meta>` |
| Масово присвояване | `$fillable` е изричен; `user_id` никога не е fillable |
| Достъп до чужди данни | `scopeOwnedBy()` + политики; `Rule::exists(...)->where('user_id', …)` при избор на материал/мярка |
| Данъчни ставки | само в `config/pricing.php`; няма маршрут, който да ги променя |
| Грешки | `APP_DEBUG=false` в production; потребителят вижда съобщения, не stack traces |
| Двойно записване | ключ за идемпотентност + заключен бутон, докато заявката тече |

---

## Производителност

- `with()` / `withCount()` навсякъде, където се показват връзки — няма N+1 заявки.
- Историята и каталогът се пагинират от backend-а.
- Екранът за калкулация зарежда само 50 материала; останалите се търсят с autocomplete (debounce 250 ms, прекъсване на предишната заявка).
- Индекси върху `(user_id, …)` за всички филтри и сортирания.
- Активите се разделят на chunk-ове от Vite; страниците се зареждат при нужда.
