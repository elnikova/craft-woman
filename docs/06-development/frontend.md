# Craft Woman — frontend (Vue 3 + TypeScript)

The SPA lives in the Laravel repository (`resources/js`) and talks to the backend only through the REST API (`/api/v1`).
Authentication uses the Sanctum session cookie (same-domain SPA); no tokens are kept in localStorage.

## Setup

1. In `.env` (Herd domain):
   ```env
   APP_URL=http://craft-woman.test
   SANCTUM_STATEFUL_DOMAINS=craft-woman.test
   SESSION_DOMAIN=null
   ```
2. Run:
   ```bash
   npm install
   php artisan config:clear
   php artisan storage:link   # product photos are on the public disk
   npm run dev                # development
   npm run build              # vue-tsc + vite build
   ```

## Structure

```
resources/js/
├── app.ts                  entry point: Vue + Pinia + Router
├── api/                    http.ts (axios, XSRF, toApiError), auth, makers, products, taxonomy, orders, favorites, account
├── stores/                 auth, cart, favorites (Pinia, setup syntax)
├── router/index.ts         routes + guard (guestOnly / requiresAuth / requiresAdmin / ownHeader)
├── config/accountNav.ts    account menu by role
├── types/                  mirrors of the API resources (user, maker, product, taxonomy, order)
├── utils/                  format (prices, minor units, Ukrainian plurals), makerStatus, productStatus, orderStatus
├── mocks/home.ts           sample data for the home page blocks without an API yet
├── layouts/                DefaultLayout (public site), AccountLayout (/account, sidebar)
├── components/
│   ├── layout/             AppHeader, AppFooter, AppLogo
│   ├── ornament/           OrnamentBand, StarOrnament, StitchLine
│   ├── ui/                 BaseButton, BaseInput, BaseTextarea, AppIcon
│   ├── home/               SectionHeader, MakerCard, CourseCard, WorkshopRow, PostCard
│   ├── makers/             MakerAvatar, MakerProfileCard, MakerStatusBadge
│   ├── products/           ProductCard, ProductCover, ProductStatusBadge, FavoriteButton
│   └── orders/             StatusPill
└── pages/                  home, auth, makers, products, cart, account, studio, admin

resources/images/patterns/   grain, linen, knit, weave, stitch (dark/light) — processed by Vite from app.css
public/images/ornaments/     4 rushnyk bands, the star (logo and decoration) — served as is, for <img>
```

> CSS textures live in `resources/images`, not `public/`: in dev mode Vite rewrites absolute
> `url('/images/…')` to its own server (port 5173), which doesn't serve `public/`, and you get a 404.

## Design tokens (`resources/css/app.css`)

| Token | Colour | Use |
|---|---|---|
| `ink` | `#2B2930` | body text |
| `ink-muted` / `ink-soft` | `#66616F` / `#5C5766` | secondary text on white / on lavender |
| `plum` | `#3A2A66` | headings, buttons, links |
| `violet` | `#6A4BC4` | hover, focus |
| `lavender-300/200/100/50` | `#A48BE6` … `#F4F0FC` | stitch line, borders, backgrounds |
| `kalyna` | `#B8436A` | ornament centres, errors |
| `line` / `line-strong` | `#E6DFF3` / `#D6CCEA` | dividers, input borders |

Utilities: `font-flair`, `tx-linen`, `tx-weave`, `tx-knit`, `ph-knit`, `ph-weave`, `ph-stitch`.

```vue
<OrnamentBand start="tree" />          <!-- rhombus | tree | barvinok | mountains -->
<OrnamentBand start="rhombus" :animated="false" />
<StarOrnament :size="200" />
```

## Conventions

- Code, comments, commit messages and PR descriptions are in English. UI copy is Ukrainian.
- `<script setup lang="ts">`; API calls only through `api/*.ts`; types in `types/*.ts` mirror the Laravel resources.
- Every data page handles loading (skeleton), error (message + retry) and empty states.
- Prices travel as minor units (`amount_minor`); inputs use `minorToInput` / `inputToMinor` from `utils/format.ts`.

## Languages (vue-i18n)

- Messages: `resources/js/i18n/uk.json` and `en.json`, set up in `i18n/index.ts`. Use `const { t } = useI18n()` in components and `t` from `@/i18n` in plain modules.
- Ukrainian plurals use three forms: `"{n} товар | {n} товари | {n} товарів"`; English uses two. Call `plural(n, 'products')` from `utils/format.ts`.
- URLs: Ukrainian has no prefix, English is under `/en`. All routes are children of `/:locale(en)?`. Named links keep the current language automatically; for string paths use `localePath('/checkout')`.
- `LocaleRoot` remounts the page when the language changes, so data is refetched in the new language. The API client sends `Accept-Language` with every request.
- Header switcher (`LocaleSwitcher`) and Settings → "Interface language". Signed-in users keep the choice in `users.locale`, and a user whose saved language is English is taken to `/en` on arrival.
- Status labels (`MAKER_STATUS_LABELS`, `ORDER_STATUS_LABELS`, …) are built with `labelMap()` and follow the current language.
- Country names come from `Intl.DisplayNames` (`countries()`, `countryName()`).
- Maker content: the API returns `content_locale`. When it differs from the interface language, pages mark the text with `lang` and show a short note.
- Not translated yet: studio and admin pages (next PR) and the sample home blocks in `mocks/home.ts`.

## Account area

After login every user lands in `/account`, a separate layout (`layouts/AccountLayout.vue`) with a sidebar.
Menu items depend on the role and are defined in one place: `config/accountNav.ts`.

| Role | Sections |
|---|---|
| Buyer | Overview, Purchases (orders, courses, workshops, favorites), "Open a studio", Settings |
| Maker | Overview, Studio (profile, products, courses, workshops, sales), Purchases, Settings |
| Administrator | Overview, Administration (maker review, users, categories, product review, all orders), Settings |

Sections not built yet are marked "скоро" (soon) and lead to `/account/soon/:section`.

## Products

| Route | Who | What |
|---|---|---|
| `/catalog` | everyone | catalog: categories with subcategories, type, search, sorting; filters in the URL |
| `/products/:slug` | everyone | product page: photos, variants and stock, maker, delivery, tags, add to cart |
| `/account/products` | maker | my products by status |
| `/account/products/new`, `/account/products/:uuid` | maker | editor: type, description, category, tags, price, variants, delivery, photos, files; submit for review |
| `/account/admin/products` | admin | product review: publish, return for changes, take off sale |

## Cart and orders

| Route | Who | What |
|---|---|---|
| `/cart` | everyone | cart grouped by maker; flags sold-out and unavailable items and changed prices |
| `/checkout` | signed in | address (physical items only), delivery quote per country, payment choice with the reason when payment on delivery is unavailable |
| `/account/orders`, `/account/orders/:number` | buyer | orders, each maker's part, tracking number, cancellation, downloads after payment |
| `/account/sales` | maker | own part of orders: "Shipped" (with tracking), "Buyer received it", "Can't fulfil" (with a reason) |
| `/account/admin/orders` | admin | all orders, search by number or e-mail |
| `/account/studio` → "Delivery & payment" | maker | flat domestic and international delivery fee, payment on delivery |

- Guest cart: the cart UUID is kept in `localStorage` (`cw.cart`) and sent as `X-Cart-Token`. After login `stores/cart.ts` calls `/cart/claim` to move the items into the account.
- Online payment will use Stripe. Until then, the order page shows a local-only "test payment" button; the backend registers that route only in `local` and `testing`.
- Payment rules: `domain-rules.md` §27.

## Favorites

- Heart on product cards and the product page (`components/products/FavoriteButton.vue`). Guests are sent to login and brought back to the same page.
- `stores/favorites.ts` keeps favorite UUIDs (`GET /me/favorites/ids`) and flips the heart at once; it rolls back if the server returns an error.
- `/account/favorites` shows products that are on sale. If a maker takes a product off sale, the record stays and the product reappears when it is republished.
- API: `GET /me/favorites`, `GET /me/favorites/ids`, `PUT|DELETE /me/favorites/{product}` (60 changes per minute).

## Settings and users

| Route | Who | What |
|---|---|---|
| `/account/settings` | everyone | name, e-mail (changing it needs the current password), password change, role and studio status |
| `/account/admin/users` | admin | search, role and access filters, block with a reason, unblock, make admin / remove admin |

- Changing the password ends all other sessions and revokes API tokens.
- Blocking sets `users.status = blocked`. Sessions and tokens are deleted at once, login returns "account blocked", and `EnsureAccountIsActive` answers `403 account_blocked` to older credentials.
- Limits: no actions on yourself; at least one active administrator must remain; the maker role is not changed here (buyer ⇄ admin only).

## Makers

| Route | Who | What |
|---|---|---|
| `/makers`, `/makers/:slug` | everyone | catalog and profile; unpublished profiles are visible only to the owner and admins |
| `/account/studio` | signed in | create and edit the profile, submit for review, moderator note |
| `/account/admin/makers` | admin | review queue: publish, return for changes (with a note), suspend, reinstate |

Demo data: `php artisan migrate:fresh --seed` — admin `admin@craftwoman.local` / `admin123`,
makers `oksana@`, `marko@`, `iryna@`, `luka@`, `sofia@craftwoman.local` / `password` (Sofia is pending review).

## Backend changes made together with the frontend

- `RegisterRequest` accepts an optional `role` (`buyer` by default, or `master`); `admin` can't be chosen at registration.
- `AuthController::login` no longer calls `Auth::attempt()` twice: `LoginRequest::after()` does the check.
- The session is regenerated after login and registration (session fixation protection).
- `logout` returned 500 under `auth:sanctum` (RequestGuard has no `logout()`); it now calls `Auth::guard('web')->logout()` explicitly.
- `bootstrap/app.php`: `statefulApi()` and API prefix `api/v1`.

## Open questions

1. **Google OAuth.** The "Continue with Google" button is inactive; the frontend flow isn't built yet.
2. **Studio and admin pages** are still Ukrainian only (next PR).

## Verified

`vue-tsc --noEmit` passes and `vite build` succeeds. Vite builds the `app.css` textures with hashes into `public/build`.
