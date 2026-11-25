# Craft Woman — frontend (Vue 3 + TypeScript)

The SPA lives in the Laravel repository (`resources/js`) and talks to the backend only through the REST API (`/api/v1`).
Authentication uses the Sanctum session cookie (same-domain SPA); no tokens are kept in localStorage.

## Setup

See [getting-started.md](getting-started.md). The `vite` container runs `npm run dev`. Type-check with `.\dev tsc` and check translations with `.\dev i18n`.

## Structure

```
resources/js/
├── app.ts                  entry point: Vue + Pinia + Router
├── api/                    http.ts (axios, XSRF, toApiError), auth, account, makers, products, taxonomy,
│                           orders (cart, checkout, orders, sales), favorites, courses, workshops
├── stores/                 auth, cart, favorites (Pinia, setup syntax)
├── router/index.ts         routes + guard (guestOnly / requiresAuth / requiresAdmin / ownHeader)
├── config/accountNav.ts    account menu by role
├── i18n/                   index.ts (setup, labelMap, localePath), uk.json, en.json
├── types/                  mirrors of the API resources (user, maker, product, taxonomy, order, course, workshop)
├── utils/                  format (prices, minor units, plurals), makerStatus, productStatus, orderStatus,
│                           course (access label), workshop (time zones, session labels, .ics)
├── mocks/home.ts           sample community posts and craft list for the home page
├── layouts/                DefaultLayout (public site), AccountLayout (/account, sidebar)
├── components/
│   ├── layout/             AppHeader, AppFooter, AppLogo, LocaleSwitcher
│   ├── ornament/           OrnamentBand, StarOrnament, StitchLine
│   ├── ui/                 BaseButton, BaseInput, BaseTextarea, AppIcon, ContentLanguage
│   ├── home/               SectionHeader, PostCard
│   ├── makers/             MakerAvatar, MakerProfileCard, MakerStatusBadge
│   ├── products/           ProductCard, ProductCover, ProductStatusBadge, FavoriteButton
│   ├── courses/            CourseCard
│   ├── workshops/          WorkshopRow, WorkshopStatusBadge
│   └── orders/             StatusPill
└── pages/                  home, auth, makers, products, courses, workshops, cart, account, studio, admin

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

- Code, comments, commit messages and PR descriptions are in English. UI copy lives in `i18n/uk.json` (Ukrainian first) and `i18n/en.json`.
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
- Editing translations: `components/ui/ContentLanguage.vue` picks the source language. The product editor and the studio profile have an optional block for the other language, and admin categories take names in both languages.
- Edit endpoints (`me.*`, `admin.*`) return the source text plus `translations`; public endpoints return the reader's language, even to the owner.
- Not translated: the sample home blocks in `mocks/home.ts`.
- Adding a message: add the key to **both** `uk.json` and `en.json`, then run `npm run i18n:check`. It reports keys that exist in one file only and literal keys used in code that don't exist. Keys built at runtime (`` t(`status.${s}`) ``) aren't checked.

## Account area

After login every user lands in `/account`, a separate layout (`layouts/AccountLayout.vue`) with a sidebar.
Menu items depend on the role and are defined in one place: `config/accountNav.ts`.

| Role | Sections |
|---|---|
| Buyer | Overview, Purchases (orders, courses, workshops, favorites), "Open a studio", Settings |
| Maker | Overview, Studio (profile, products, courses, workshops, sales), Purchases, Settings |
| Administrator | Overview, Administration (maker review, users, categories, product, course and workshop review, all orders), Settings |

Every menu item now leads to a real page. The `/account/soon/:section` placeholder route stays for future sections.

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

## Courses

| Route | Who | What |
|---|---|---|
| `/courses`, `/courses/:slug` | everyone | catalog and course page: curriculum, free preview lessons, price; for a student — progress, "Continue", certificate |
| `/courses/:slug/learn/:lesson` | student, owner, admin; anyone for preview lessons | lesson player: outline, materials (text, YouTube/Vimeo embed, file download, link), "Mark as done", previous/next |
| `/certificates/:number` | everyone | printable certificate without site chrome; doubles as public verification by number |
| `/account/learning` | buyer | bought courses with progress, access end date, certificate |
| `/account/courses`, `/account/courses/new`, `/account/courses/:uuid` | maker | course list by status; editor: details + translation, price, access (lifetime or N days), certificate toggle, cover, curriculum builder (modules → lessons → materials) |
| `/account/admin/courses` | admin | review queue like products: publish, return for changes, take off sale |

- A course goes into the same cart as products (`POST /cart/items` with `course_uuid`); it needs no delivery address. After payment the `OrderPaid` event grants access (`GrantCourseAccess`). Buying a limited course again extends access from the later of today and the current end date.
- A course is complete when all **required** lessons are done; then the student can issue a certificate (`CWC-YYYY-XXXXXX`) if the course has certificates enabled.
- Curriculum changes are saved immediately and don't reset unsaved course details.
- Videos are links, not uploads. Makers should mark them "unlisted"; the player uses `youtube-nocookie.com` or the Vimeo player.
- Demo courses: Oksana "В’язання спицями з нуля" (lifetime, has an English version) and Iryna "Полтавська вишивка" (180 days).
- Known quirk: a static attribute containing `//` (e.g. `placeholder="https://"`) inside a `v-if` chain breaks `vue-tsc` scope analysis; bind it as an expression instead.

## Workshops

| Route | Who | What |
|---|---|---|
| `/workshops`, `/workshops/:slug` | everyone | upcoming workshops (search, city, online / in person) and the workshop page: schedule, seats left, seat picker |
| `/account/bookings` | participant | upcoming and past bookings with the meeting link or address, note from the maker, "Add to calendar" (.ics) |
| `/account/workshops`, `/account/workshops/new`, `/account/workshops/:uuid` | maker | list by status; editor: details + translation, format and place, time zone, price and seats, schedule, cover, participants, cancellation |
| `/account/admin/workshops` | admin | review queue like products and courses |

- Format (online / in person) and capacity belong to the whole workshop: a booking covers every session. Buying N seats = cart quantity N, prepaid only.
- Seats are held when the order is placed (row lock, so the last seat can't be sold twice), confirmed by `OrderPaid`, released when the order is cancelled. Booking closes when the first session starts.
- Meeting link, exact address and the participant note are returned only to confirmed participants, the owner and admins (`private` in the API). The link can be added after review.
- Sessions are entered as wall-clock time in the workshop's time zone (`starts_local`) and stored in UTC. Pages show the workshop's time; online workshops also show the visitor's time when it differs.
- Only the maker can cancel, and only the whole workshop, with a reason that participants see. Refunds are a manual step until Stripe arrives.
- Demo workshops: Marko (pottery, Опішня), Oksana (online socks, two evenings), Iryna (embroidery, Полтава).

## Makers

| Route | Who | What |
|---|---|---|
| `/makers`, `/makers/:slug` | everyone | catalog and profile; unpublished profiles are visible only to the owner and admins |
| `/account/studio` | signed in | create and edit the profile, submit for review, moderator note |
| `/account/admin/makers` | admin | review queue: publish, return for changes (with a note), suspend, reinstate |

Demo data: `php artisan migrate:fresh --seed` — admin `admin@craftwoman.local` / `admin123`,
makers `oksana@`, `marko@`, `iryna@`, `luka@`, `sofia@craftwoman.local` / `password` (Sofia is pending review).

## Open questions

Open product questions and technical gaps are tracked in [roadmap.md](../07-status/roadmap.md).

## Before a pull request

`.\dev check` (Pint, tests, type-check, translations) and, for larger changes, a click-through of the affected pages in both languages.
