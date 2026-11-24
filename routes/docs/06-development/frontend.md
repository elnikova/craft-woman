# Craft Woman — фронтенд (Vue 3 + TypeScript)

SPA живе в тому ж репозиторії Laravel (`resources/js`) і працює тільки через REST API.
Авторизація — сесійна cookie Sanctum (SPA на тому ж домені), без токенів у localStorage.

## Встановлення

1. У `.env` (домен Herd):
   ```env
   APP_URL=http://craft-woman.test
   SANCTUM_STATEFUL_DOMAINS=craft-woman.test
   SESSION_DOMAIN=null
   ```
2. Запуск:
   ```bash
   npm install
   php artisan config:clear
   npm run dev          # розробка
   npm run build        # vue-tsc + vite build
   ```

## Структура

```
resources/js/
├── app.ts                  точка входу: Vue + Pinia + Router
├── api/
│   ├── http.ts             axios (withCredentials, XSRF), toApiError() для 422
│   └── auth.ts             login / register / me / logout
├── stores/auth.ts          Pinia: user, isAuthenticated, isMaster…
├── router/index.ts         маршрути + guard (guestOnly / requiresAuth)
├── types/                  user.ts (дзеркало UserResource), catalog.ts
├── utils/format.ts         ціни, рейтинг, дати, українська множина
├── mocks/home.ts           приклад даних, поки немає API каталогу
├── layouts/DefaultLayout.vue
├── components/
│   ├── layout/             AppHeader, AppFooter, AppLogo
│   ├── ornament/           OrnamentBand, StarOrnament, StitchLine
│   ├── ui/                 BaseButton, BaseInput
│   └── home/               SectionHeader, MakerCard, ProductCard, CourseCard, WorkshopRow, PostCard
├── components/makers/      MakerAvatar, MakerProfileCard, MakerStatusBadge
└── pages/
    ├── HomePage, NotFoundPage, auth/AuthPage
    ├── makers/             MakersPage (/makers), MakerPage (/makers/:slug)
    ├── studio/             StudioProfilePage (/studio) — профіль майстра й надсилання на перевірку
    └── admin/              AdminMakersPage (/admin/makers) — схвалення, правки, призупинення

resources/images/patterns/   grain, linen, knit, weave, stitch (dark/light) — Vite їх обробляє з app.css
public/images/ornaments/     4 рушники, зірка (логотип і декор) — віддаються як є, для <img>
```

> Текстури для CSS лежать у `resources/images`, а не в `public/`: у dev-режимі Vite переписує
> абсолютні `url('/images/…')` на свій сервер (порт 5173), де `public/` не роздається, і отримуємо 404.

## Дизайн-токени (`resources/css/app.css`)

| Токен | Колір | Для чого |
|---|---|---|
| `ink` | `#2B2930` | основний текст |
| `ink-muted` / `ink-soft` | `#66616F` / `#5C5766` | другорядний на білому / на лаванді |
| `plum` | `#3A2A66` | заголовки, кнопки, посилання |
| `violet` | `#6A4BC4` | hover, фокус |
| `lavender-300/200/100/50` | `#A48BE6` … `#F4F0FC` | шов, рамки, фони |
| `kalyna` | `#B8436A` | серцевини орнаментів, помилки |
| `line` / `line-strong` | `#E6DFF3` / `#D6CCEA` | розділювачі, рамки полів |

Утиліти: `font-flair`, `tx-linen`, `tx-weave`, `tx-knit`, `ph-knit`, `ph-weave`, `ph-stitch`.

```vue
<OrnamentBand start="tree" />          <!-- rhombus | tree | barvinok | mountains -->
<OrnamentBand start="rhombus" :animated="false" />
<StarOrnament :size="200" />
```

## Кабінет

Після входу користувач потрапляє в `/account` — окремий layout (`layouts/AccountLayout.vue`) з бічним меню.
Пункти меню залежать від ролі й задаються в одному місці: `config/accountNav.ts`.

| Роль | Розділи |
|---|---|
| Покупець | Огляд, Покупки (замовлення, курси, майстер-класи, обране), «Відкрити майстерню», Налаштування |
| Майстер | Огляд, Майстерня (профіль, товари, курси, майстер-класи, продажі), Покупки, Налаштування |
| Адміністратор | Огляд, Адміністрування (перевірка майстрів, користувачі, категорії, модерація, замовлення), Налаштування |

Ще не реалізовані розділи позначені «скоро» і ведуть на `/account/soon/:section`.

## Майстри

| Маршрут | Хто | Що |
|---|---|---|
| `/makers`, `/makers/:slug` | усі | каталог і профіль; неопубліковані профілі бачать тільки власник і адмін |
| `/account/studio` | авторизовані | створення профілю, редагування, «Надіслати на перевірку», коментар модератора |
| `/account/admin/makers` | адмін | черга перевірки: опублікувати, повернути на правки (з коментарем), призупинити, відновити |

Демо-дані: `php artisan migrate:fresh --seed` — адмін `admin@craftwoman.local` / `admin123`,
майстри `oksana@`, `marko@`, `iryna@`, `luka@`, `sofia@craftwoman.local` / `password` (Софія — на перевірці).

## Бекенд: що змінено разом із фронтендом

- `RegisterRequest` приймає необов'язкове `role` (`buyer` за замовчуванням або `master`); `admin` через реєстрацію отримати не можна.
- `AuthController::login` більше не викликає `Auth::attempt()` вдруге: перевірку робить `LoginRequest::after()`.
- Після входу й реєстрації сесія регенерується (захист від session fixation).
- `logout` падав з 500 під `auth:sanctum` (RequestGuard не має `logout()`): тепер явно `Auth::guard('web')->logout()`.
- Тести: реєстрація повертає `201 Created`, перевірка ролей, SPA-маршрути не перехоплюють `/api/*`.
- `bootstrap/app.php`: `statefulApi()` для майбутніх роутів на `api`-middleware.

## Відкриті питання

1. **Схвалення майстрів.** PRD вимагає, щоб адміністратор підтверджував майстрів. Потрібне поле статусу,
   наприклад `maker_status: pending|approved|rejected`, і фільтр у каталозі.
2. **Версія API.** У PRD `/api/v1/...`. Коли перенесеш роути, зміни лише `baseURL` у `resources/js/api/http.ts`.
2. **Google OAuth.** Кнопка «Продовжити з Google» поки неактивна, фронтенд-флоу ще не реалізований.

## Перевірено

`vue-tsc --noEmit` без помилок, `vite build` успішний.
Текстури з `app.css` Vite збирає з хешами в `public/build`.
