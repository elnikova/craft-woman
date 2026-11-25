# Backend conventions

Laravel 12, PHP 8.2+, PostgreSQL, Sanctum session auth for the SPA, Pest for tests. Business code lives in `modules/`; `app/` holds only what every module shares.

## Modules

| Module | Owns | Provider |
|---|---|---|
| Identity | users (in `app/Models/User`), maker profiles, maker review, admin user management | `IdentityServiceProvider` |
| Marketplace | products, variants, images, files, inventory, categories, tags, favorites | `MarketplaceServiceProvider` |
| Education | courses, modules, lessons, materials, enrollments, progress, certificates | `EducationServiceProvider` |
| Workshops | workshops, sessions, bookings | `WorkshopsServiceProvider` |
| Orders | carts, checkout, orders, order groups (one per maker), items, deliveries, maker sales | `OrdersServiceProvider` |

Providers are registered in `bootstrap/providers.php` in this order: Identity, Marketplace, Education, Workshops, Orders. Each provider loads its own migrations and routes (prefix `/api/v1`).

Payments, Reviews, Community and Notifications from [module-architecture.md](../02-architecture/module-architecture.md) don't exist yet.

### Layout of a module

```text
modules/Workshops/
├── Domain/                 pure PHP: enums with state machines, rules, value calculations, exceptions
│   ├── WorkshopStatus.php
│   ├── Seats.php
│   └── Exceptions/WorkshopRuleViolation.php
├── Application/
│   ├── Contracts/          what other modules may call (interfaces + readonly DTOs)
│   ├── Services/           use cases; transactions live here
│   └── Listeners/          reactions to other modules' events
├── Infrastructure/
│   ├── Persistence/        Eloquent models, factories, migrations, contract implementations
│   └── Providers/
└── Presentation/Http/
    ├── Controllers/        thin: validate → call a service → present
    ├── Resources/          presenters that shape JSON
    └── Routes/api.php
```

Only the directories a module needs exist. There are no repositories or command buses: Eloquent models live in `Infrastructure/Persistence` and services use them directly.

## Rules of thumb

- **Controllers stay thin.** Validation happens in the controller (`$request->validate`) or a FormRequest. The business rule goes in a Domain class or an Application service.
- **Domain classes don't touch Laravel.** For example, `CourseReadiness`, `WorkshopReadiness`, `Seats`, `PaymentPolicy` and the status enums are plain PHP and unit-tested.
- **Statuses are enums with transitions.** `transitionTo()` throws a `…RuleViolation` when a move isn't allowed (see `ProductStatus`, `CourseStatus`, `WorkshopStatus`, `GroupStatus`).
- **Money is an integer in minor units** plus a three-letter currency (`price_amount_minor`, `currency`). There are no floats.
- **UUID primary keys** (`uuid`) everywhere. Public URLs use slugs; certificates use their number.
- **Snapshots in orders.** Order items copy the name, price and slug at checkout and have no foreign keys to the catalog.

## Errors

| Situation | HTTP | Shape |
|---|---|---|
| Validation | 422 | Laravel default: `message` + `errors.{field}[]` |
| Business rule broken | 409 | `{ "message": "<translated text>" }` |
| Not yours / not visible | 404 | owners are checked in controllers with `abort_unless(…, 404)` so other people's resources look absent |
| Role missing | 403 | gates `manage-catalog`, `manage-users`, policy `review` for maker profiles |

Every module throws its own `DomainException` subclass (`OrderRuleViolation`, `CourseRuleViolation`, `WorkshopRuleViolation`, …) with a `key` and `params`. `bootstrap/app.php` renders it as a 409 with `__('domain.'.$key, $params)` from `lang/{uk,en}/domain.php`. If a parameter value is itself a key under `domain.values.*`, it is translated too (statuses, readiness lists).

To add a rule:
1. Add a named constructor on the module's exception: `public static function soldOut(): self`.
2. Add the message to **both** `lang/uk/domain.php` and `lang/en/domain.php`.
3. Write a test that checks the 409 and, for important messages, the Ukrainian text.

## Languages

- `App\Http\Middleware\SetLocaleFromHeader` sets the locale from `Accept-Language`. The default is `APP_LOCALE` (`uk`).
- `App\Support\Locales` lists the supported languages. Adding one is a config change.
- Maker and admin content uses `App\Support\HasTranslations`. A model declares `$translatable`, keeps the source text in its columns with `content_locale`, and stores other languages in the JSON `translations` column.
  - Public endpoints return `localized()` text plus `content_locale`.
  - Edit endpoints (route names `me.*`, `admin.*`) return the source text plus `translations`.
- Validation attribute names live in `lang/*/validation.php` → `attributes`.

## Talking to other modules

A module may use another module's `Application\Contracts` and events. It must not use another module's Eloquent models.

| Contract | Owner | Used by | Purpose |
|---|---|---|---|
| `MakerDirectory` → `MakerSummary` | Identity | all | maker lookups, approval state, delivery and payment settings |
| `ProductCatalog` → `PurchasableLine` | Marketplace | Orders | product prices, stock, digital files |
| `CourseCatalog` → `CourseLine` | Education | Orders | course price and whether it can be bought |
| `WorkshopCatalog` → `WorkshopLine` | Workshops | Orders | seats left, hold and release seats |

Orders wraps the three catalogs in `Orders\Application\Purchasables\PurchasableCatalog`, so cart and checkout treat products, courses and workshop seats the same way (`Purchasable`).

| Event | Raised by | Listeners |
|---|---|---|
| `Orders\Application\Events\OrderPaid` (after commit) | `OrderService::markPaid` — online payment (today: the test payment) | `Education\GrantCourseAccess`, `Workshops\ConfirmWorkshopBookings` |

Courses and workshop seats can't be paid on delivery (`PaymentPolicy`), so the cash-on-delivery path doesn't need this event yet.

Workshop seats are **reserved synchronously** through `WorkshopCatalog::reserve()` inside the checkout transaction, and released through `release()` when a group is cancelled. An event would come too late to stop overbooking.

### Known boundary exceptions

These cross the rule above. Clean them up when the module is next touched:

- Orders catches `CourseRuleViolation` and `WorkshopRuleViolation` (Domain exceptions of other modules) and uses Marketplace's inventory service directly.
- Education and Workshops attach categories through Marketplace's `Category` model (polymorphic `categorizables`).
- Factories use other modules' factories (test-only).

## Files

| Disk | What | Access |
|---|---|---|
| `public` | product images, course and workshop covers | public URL via `storage:link` |
| `local` | paid product files, course lesson files | streamed by controllers after an entitlement check |

## Testing

- Pest. `tests/Unit/<Module>` covers pure Domain classes, and `tests/Feature/<Module>` covers HTTP flows against the PostgreSQL database `craft_woman_test` (`phpunit.xml`, rebuilt for every test). In Docker that database is in the `db` container. `phpunit.sqlite.xml` runs the same tests on in-memory SQLite when PostgreSQL isn't available.
- Text search uses `whereLike()` (case-insensitive: `ILIKE` on PostgreSQL). Don't write `where(col, 'like', …)`, because PostgreSQL `LIKE` is case-sensitive.
- Factories live next to the models (`Infrastructure/Persistence/*Factory.php`). `published()`-style states build ready-to-sell records.
- Switching users inside one test: Sanctum caches the user, so call `app('auth')->forgetGuards()` before the next `actingAs()` (see the `signIn()` helper in `tests/Feature/Workshops/WorkshopsTest.php`).
- Every business invariant from [domain-rules.md](../02-architecture/domain-rules.md) §57 should have a test. The current coverage is listed in [roadmap.md](../07-status/roadmap.md).
- Run `.\dev pint` (or `.\dev check` for everything) before committing.
