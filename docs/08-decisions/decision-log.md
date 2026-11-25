# Decision log

Product and technical decisions, newest first. Each entry says what was decided, why, and where it lives in code.
Design documents link here instead of repeating the reasoning.

Add an entry whenever a pull request settles an open question or departs from a design document.

---

## 2026-11-25 — PostgreSQL for development and tests

| Decision | Why |
|---|---|
| Development and the test suite run on PostgreSQL (databases `craft_woman` and `craft_woman_test`) | The design targets it. Row locks and case-insensitive search only behave correctly there |
| `phpunit.sqlite.xml` stays as a fast fallback without a database server | Quick runs on any machine; one PostgreSQL-only test is skipped |
| Search uses `whereLike()`, which becomes `ILIKE` on PostgreSQL | `LIKE` is case-sensitive there; searching "плед" must find "Плед" |
| Translations are stored as readable JSON (`JSON_UNESCAPED_UNICODE`) | Ukrainian translations of English-source listings can be found by search |
| No data migration from SQLite | Only demo data so far; `migrate:fresh --seed` rebuilds it |

Verified: the full suite passes on PostgreSQL 16. 12 parallel bookings for a 3-seat workshop produced exactly 3 bookings.

## 2026-11-25 — Workshops

| Question | Decision | Why |
|---|---|---|
| Online / in person: per session or per workshop? | **Per workshop.** One address or one meeting link for all sessions | Simpler for makers and buyers; mixed formats are rare |
| Capacity | **One number for the whole workshop**, not per session (differs from postgresql-schema §6.2) | A booking covers every session, so per-session capacity would always equal the smallest one |
| When are seats taken? | **Held when the order is placed** (`pending` booking), **confirmed on payment**, released when the order is cancelled | The last seat can't be sold to two people; the check runs under a row lock |
| Several seats per purchase | Allowed: cart quantity = seats | People book for a friend or a child |
| Who sees the meeting link / exact address? | **Only confirmed participants**, the owner and admins. The public page shows the city only | Privacy of the maker's place; links aren't shared around |
| Meeting link required for review? | No, it can be added later | Makers often create the meeting close to the date |
| Booking window | Closes when the first session starts | Joining halfway through a multi-session workshop makes little sense |
| Cancellation by a participant | **Not in the MVP** | Refunds need Stripe; manual for now |
| Cancellation by the maker | The whole workshop, with a reason all participants see; bookings become `cancelled` | Partial cancellation adds edge cases without clear demand |
| Time zone | Each workshop has an IANA time zone. Times are entered and shown in it; online workshops also show the visitor's own time | Avoids "is that my time or hers?" |
| Completed bookings | Derived from the last session's end, not stored | One less status to keep in sync |

Code: `modules/Workshops`, `Orders\Application\Purchasables`, `BookingService::reserve()`.

## 2026-11-25 — Courses

| Question | Decision |
|---|---|
| Video | **YouTube or Vimeo links** only, embedded via `youtube-nocookie.com` / Vimeo player (`VideoUrl`). No hosting |
| Lesson materials | text, video link, file (private disk, up to 100 MB), external link; a lesson can have several |
| Course completion (domain-rules §56.7) | **All required lessons** are marked done. Makers mark lessons optional or as free previews |
| Certificate | A printable page and **public verification by number** (`CWC-YYYY-XXXXXX`). It records holder, course and maker names at issue time. Makers can switch certificates off per course |
| Moderation | Same flow as products: draft → review → published / changes requested → off sale |
| Access | Lifetime or N days from purchase. Buying a limited course again extends access from the later of today and the current end. A lifetime course can't be bought twice |
| Expired access | Progress is kept; lessons other than previews are locked |

Code: `modules/Education`.

## 2026-11-25 — Bug: cancelling an unpaid order granted course access

`OrderService::cancelByCustomer` dispatched `OrderPaid`. Fixed in the workshops PR, with a regression test (`cancelling an unpaid order does not grant course access`).

## 2026-11-25 — Languages

Ukrainian is the default with no URL prefix; English is under `/en`. Maker content has one source language plus optional translations in a JSON column. The full table is in [domain-rules.md §42](../02-architecture/domain-rules.md). API default locale: `APP_LOCALE=uk`.

## 2026-11-25 — Users and roles

| Decision | Why |
|---|---|
| A single `users.role` column (`buyer`, `master`, `admin`) instead of `roles` + `user_roles` | Every user has exactly one role in the MVP; the pivot can come later |
| `name` instead of first/last name | Enough for orders, certificates and participants lists |
| Admins can block users with a reason; blocked users are signed out on the next request (`EnsureAccountIsActive`) | Moderation needs a stop button |

## 2026-11-24 — Orders and payment

| Question (domain-rules) | Decision |
|---|---|
| §56.1 Name of one maker's part of an order | **`OrderGroup`** |
| §56.2 Mixed currencies in one cart | **Not allowed.** The cart takes the currency of its first item; adding an item in another currency fails with a message (`order.currency_mismatch`) |
| §56.3 Cash on delivery with several makers | Only if **every** maker allows it, nothing ships abroad and there are no digital items, courses or workshop seats (`PaymentPolicy`). Checkout explains why a method is unavailable |
| §56.4 Delivery currency | The maker's delivery currency must equal the cart currency, otherwise checkout fails (`order.delivery_currency`). No conversion |
| Delivery fee | Flat per maker: domestic and international amounts in the studio profile; `null` international = doesn't ship abroad |
| Stock | Reserved when the order is placed and returned when it is cancelled |
| Stripe | **Deferred.** A test-payment endpoint marks orders paid in `local` / `testing` |
| Guest carts | Allowed; the cart UUID is the guest token, merged after login |

## 2026-11-24 — Moderation (domain-rules §56.10)

Returning something for changes, suspending a maker or taking a listing off sale needs a **free-text note of at least 5 characters**, shown to the maker. There are no predefined reasons yet.

## Still open

| # | Question | Where |
|---|---|---|
| 56.5 | Review multiplicity | domain-rules §56.5 — decide before the Reviews module |
| 56.6 | Maker rating formula | domain-rules §56.6 |
| 56.8 | Existing followers when following is disabled | domain-rules §56.8 |
| 56.9 | Which notifications are mandatory | domain-rules §56.9 — decide before e-mails |
| — | Expiry of unpaid orders (they hold stock and seats forever) | roadmap |
| — | Refund model (product, course, workshop) | comes with Stripe |
