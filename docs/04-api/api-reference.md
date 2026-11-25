# API reference

All endpoints live under `/api/v1` and return JSON. The SPA is the only client today.

Run `php artisan route:list --path=api` for the live list. This document groups the endpoints and explains the conventions.

## Conventions

| Topic | Rule |
|---|---|
| Auth | Sanctum session cookie (same-domain SPA). Call `GET /sanctum/csrf-cookie` once, then send `X-XSRF-TOKEN`. No bearer tokens |
| Language | `Accept-Language: uk` or `en`. Without the header the API uses `APP_LOCALE` (`uk`). Errors, statuses and maker content come back in that language |
| Guest cart | The server returns a cart UUID. The SPA keeps it in localStorage and sends it as `X-Cart-Token`. After login, `POST /cart/claim` merges it into the user's cart |
| IDs | UUIDs. Public pages use slugs (products, makers, courses, workshops); certificates use their number `CWC-YYYY-XXXXXX` |
| Money | `{ "amount_minor": 4500, "currency": "EUR" }`. Inputs send `price_amount_minor` + `currency` |
| Dates | ISO 8601. Workshop sessions also have `starts_local` / `ends_local` (wall-clock `YYYY-MM-DDTHH:mm` in the workshop's `timezone`) |
| Single item | `{ "data": { … } }` |
| Lists | `{ "data": [ … ], "meta": { "current_page", "last_page", "per_page", "total" } }`; `?page=`, `?per_page=` (max 48) |
| Translatable content | Public responses: text in the reader's language + `content_locale`. Edit responses (`/me/…`, `/admin/…`): source text + `translations: { "en": { … } }` |

### Errors

| Code | When | Body |
|---|---|---|
| 401 | not signed in | `{ "message" }` |
| 403 | signed in but the role is missing, or the account is blocked | `{ "message" }` |
| 404 | not found **or not yours** (other makers' records look absent) | `{ "message" }` |
| 409 | a business rule stops the action (status change not allowed, no seats left, cart changed) | `{ "message": "<translated>" }` |
| 422 | validation | `{ "message", "errors": { "field": ["…"] } }` |

## Access legend

| Mark | Who |
|---|---|
| — | anyone |
| user | any signed-in user |
| maker | signed-in user with an approved maker profile (others get 403 or a 409 rule message) |
| owner | the maker who owns the record (otherwise 404) |
| admin | gate `manage-catalog` / `manage-users` / maker `review` policy |

## Auth and account

| Method | Path | Access | Purpose |
|---|---|---|---|
| POST | `/register` | — | create an account (`role`: `buyer` default or `master`) |
| POST | `/login` | — | sign in |
| POST | `/logout` | user | sign out |
| GET | `/me` | user | current user |
| PATCH | `/me/account` | user | name, e-mail, interface language |
| PUT | `/me/password` | user | change password |

## Makers

| Method | Path | Access | Purpose |
|---|---|---|---|
| GET | `/makers` | — | approved makers |
| GET | `/makers/{slug}` | — | maker page |
| GET | `/me/maker-profile` | user | own studio profile |
| PUT | `/me/maker-profile` | user | create or update it (delivery fees, COD, translations) |
| POST | `/me/maker-profile/submit` | user | send for review |
| GET | `/admin/makers` | admin | review queue (`?status=`) |
| POST | `/admin/makers/{uuid}/approve` · `reject` · `suspend` · `reinstate` | admin | review decisions (`note` required for reject/suspend) |

## Catalog

| Method | Path | Access | Purpose |
|---|---|---|---|
| GET | `/categories` | — | category tree |
| GET | `/tags` | — | tags |
| GET | `/products` | — | `?q=&category=&maker=&type=physical\|digital&sort=newest\|price_asc\|price_desc` |
| GET | `/products/{slug}` | — | product page |
| GET | `/me/products` | maker | own products (`?status=`) |
| POST | `/me/products` | maker | create a draft |
| GET · PUT · DELETE | `/me/products/{uuid}` | owner | read, update, delete a draft |
| POST | `/me/products/{uuid}/images` · `files` | owner | upload images / downloadable files |
| PUT | `/me/products/{uuid}/images/order` | owner | reorder images |
| DELETE | `/me/products/{uuid}/images/{image}` · `files/{file}` | owner | remove |
| POST | `/me/products/{uuid}/submit` · `archive` · `relist` | owner | lifecycle |
| GET | `/admin/products` | admin | review queue (`?status=`) |
| POST | `/admin/products/{uuid}/approve` · `reject` · `archive` | admin | review decisions |
| GET · POST · PUT · DELETE | `/admin/categories[/{uuid}]` | admin | manage categories |

## Favorites

| Method | Path | Access | Purpose |
|---|---|---|---|
| GET | `/me/favorites` | user | saved products |
| GET | `/me/favorites/ids` | user | UUIDs only, to mark hearts |
| PUT · DELETE | `/me/favorites/{product}` | user | add / remove |

## Cart and checkout

| Method | Path | Access | Purpose |
|---|---|---|---|
| GET | `/cart` | — | cart grouped by maker; each item has `kind` (`product` \| `course` \| `workshop`), `issue` when it can't be bought |
| POST | `/cart/items` | — | add one of: `{product_uuid, variant_uuid?, quantity}` · `{course_uuid}` · `{workshop_uuid, quantity}` (quantity = seats) |
| PATCH · DELETE | `/cart/items/{item}` | — | change quantity / remove |
| POST | `/cart/claim` | user | merge the guest cart after login |
| POST | `/checkout/quote` | user | groups, delivery fees, totals, which payment methods are available and why not |
| POST | `/checkout` | user | place the order: `payment_method`, `delivery` (only when something ships), `expected_total_minor` (409 if prices changed) |

## Orders and sales

| Method | Path | Access | Purpose |
|---|---|---|---|
| GET | `/me/orders` | user | own orders |
| GET | `/me/orders/{number}` | user | order with groups, items, deliveries, download links |
| POST | `/me/orders/{number}/cancel` | user | cancel while unpaid (online) or not shipped (COD) — releases stock and workshop seats |
| POST | `/me/orders/{number}/test-payment` | user | **local/testing only**: mark as paid |
| GET | `/me/orders/{number}/items/{item}/files/{file}` | user | download a paid digital file |
| GET | `/me/sales` | maker | own order groups |
| POST | `/me/sales/{group}/ship` · `deliver` · `cancel` | owner | fulfilment (`tracking_number`, cancel `reason`) |
| GET | `/admin/orders` | admin | all orders |

## Courses

| Method | Path | Access | Purpose |
|---|---|---|---|
| GET | `/courses` | — | published courses (`?q=`) |
| GET | `/courses/{slug}` | — | course with outline; `enrollment` for students |
| GET | `/courses/{slug}/lessons/{lesson}` | preview lessons: anyone; others: student, owner, admin | lesson with materials, previous / next |
| GET | `/courses/{slug}/lessons/{lesson}/materials/{material}/file` | same as above | download a lesson file |
| POST · DELETE | `/courses/{slug}/lessons/{lesson}/complete` | student | mark / unmark a lesson done; returns progress |
| POST | `/courses/{slug}/certificate` | student | issue the certificate once all required lessons are done |
| GET | `/certificates/{number}` | — | public verification |
| GET | `/me/learning` | user | bought courses with progress |
| GET · POST | `/me/courses` | maker | list / create |
| GET · PUT · DELETE | `/me/courses/{uuid}` | owner | editor data (incl. `missing` requirements) |
| POST | `/me/courses/{uuid}/cover` · `submit` · `archive` · `relist` | owner | cover and lifecycle |
| POST | `/me/courses/{uuid}/modules` · `modules/reorder` | owner | curriculum: modules |
| PATCH · DELETE | `/me/course-modules/{module}` | owner | |
| POST | `/me/course-modules/{module}/lessons` · `lessons/reorder` | owner | curriculum: lessons |
| PATCH · DELETE | `/me/lessons/{lesson}` | owner | title, `is_required`, `is_preview` |
| POST | `/me/lessons/{lesson}/materials` | owner | `type`: `text` \| `video` (YouTube/Vimeo link) \| `file` \| `link` |
| PATCH · DELETE | `/me/lesson-materials/{material}` | owner | |
| GET | `/admin/courses` | admin | review queue |
| POST | `/admin/courses/{uuid}/approve` · `reject` · `archive` | admin | |

## Workshops

| Method | Path | Access | Purpose |
|---|---|---|---|
| GET | `/workshops` | — | upcoming, soonest first (`?q=&format=online\|offline&city=&category=&maker=`) |
| GET | `/workshops/{slug}` | — | page; `private` (meeting link, address, note) only for participants, owner, admin; `my_seats` |
| GET | `/me/bookings` | user | confirmed and cancelled bookings with everything needed to attend |
| GET · POST | `/me/workshops` | maker | list / create |
| GET · PUT · DELETE | `/me/workshops/{uuid}` | owner | editor data (`missing`, `seats_booked`) |
| POST | `/me/workshops/{uuid}/cover` · `submit` · `archive` · `relist` | owner | cover and lifecycle |
| POST | `/me/workshops/{uuid}/cancel` | owner | cancel for everyone (`reason`, 5+ characters) |
| GET | `/me/workshops/{uuid}/participants` | owner | confirmed participants, seats confirmed and pending |
| POST | `/me/workshops/{uuid}/sessions` | owner | `{starts_at, ends_at}` as local `YYYY-MM-DDTHH:mm` |
| PUT · DELETE | `/me/workshop-sessions/{session}` | owner | |
| GET | `/admin/workshops` | admin | review queue |
| POST | `/admin/workshops/{uuid}/approve` · `reject` · `archive` | admin | |

## Administration

| Method | Path | Access | Purpose |
|---|---|---|---|
| GET | `/admin/users` | admin | users (`?q=&role=&status=`) |
| PATCH | `/admin/users/{uuid}/role` | admin | change role |
| POST | `/admin/users/{uuid}/block` · `unblock` | admin | block with a reason / unblock |

## Not yet

OpenAPI 3.1 (product-requirements §18) isn't generated yet. Stripe webhooks, reviews, community and notifications have no endpoints yet.
