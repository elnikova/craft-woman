# Status and roadmap

State on **2026-11-25**. Update this file in every feature pull request.

## MVP status

Scope from [product-requirements.md §22](../01-product/product-requirements.md).

| Area | Status | Notes |
|---|---|---|
| E-mail / password auth, registration as buyer or maker | ✅ Done | |
| Google sign-in | ⏳ Not started | Socialite is installed; routes are commented out in `routes/api.php`; the button is inactive |
| Password reset, e-mail verification | ⏳ Not started | No mail is sent anywhere yet |
| Maker profiles and approval | ✅ Done | Draft → review → approved / changes requested / suspended |
| Physical and digital products, variants, stock, images, files | ✅ Done | Moderation like the other listings |
| Categories (2 levels, bilingual), tags | ✅ Done | Tags are not translated |
| Search and filters | 🟡 Partial | Products: text, category, maker, type, sort. Workshops: text, format, city. Courses: text only. No price or rating filters |
| Favorites | 🟡 Partial | Products only; courses and workshops can't be saved yet |
| Cart, multi-maker orders, delivery per maker | ✅ Done | Products, courses and workshop seats in one cart; one currency per cart |
| Payment on delivery | ✅ Done | Rules in `PaymentPolicy` |
| Online payment (Stripe test mode) | ⏳ Not started | Deferred; a local-only test-payment button marks orders paid |
| Courses: curriculum, purchase, access, progress, certificates | ✅ Done | Video by YouTube/Vimeo link |
| Workshops: sessions, capacity, bookings | ✅ Done | Maker cancellation; no participant cancellation |
| Reviews and maker rating | ⏳ Not started | Needs decisions 56.5, 56.6 |
| Community: posts, likes, comments, following | ⏳ Not started | Home page posts are still sample data (`mocks/home.ts`) |
| E-mail notifications | ⏳ Not started | Needs decision 56.9 |
| Administration and moderation | ✅ Done | Makers, products, courses, workshops, categories, users, all orders |
| Ukrainian and English | ✅ Done | Interface, API messages, maker content translations |
| EUR, USD, UAH | 🟡 Partial | Prices in any of the three; no conversion, no currency switcher |

## Known gaps

Technical debt and risks, roughly by importance:

1. **Unpaid orders never expire.** They hold stock and workshop seats indefinitely. Needs a scheduled job (for example, cancel after 24–48 h) and a decision on the timeout.
2. **No refunds.** Cancelled workshops and cancelled paid orders need a manual refund until Stripe arrives.
3. **No e-mails.** Participants learn about a cancelled workshop or a changed time only in their account.
4. **Module boundary leaks** — listed in [backend.md](../06-development/backend.md#known-boundary-exceptions).
5. **No OpenAPI spec.** [api-reference.md](../05-api/api-reference.md) is maintained by hand.
6. **Migrations are edited in place.** That's fine before the first deploy. After that, only add new migrations.
7. **No CI.** Tests, Pint, `vue-tsc` and `i18n:check` run locally only.

## Test coverage of the key invariants

From [domain-rules.md §57](../02-architecture/domain-rules.md).

| Invariant | Covered |
|---|---|
| Maker cannot publish before approval | ✅ |
| Maker cannot change another maker's entity | ✅ products, courses, workshops, sales |
| Stock never negative / reserved at checkout | ✅ (guarded update + row lock on PostgreSQL) |
| Digital files only with a valid purchase | ✅ |
| Course access respects expiration | ✅ |
| Certificate only after completion | ✅ |
| Workshop capacity never exceeded | ✅ test suite; plus a manual race of 12 parallel bookings for 3 seats on PostgreSQL → exactly 3 |
| Several makers → several groups, each with its own delivery | ✅ |
| International orders can't use payment on delivery | ✅ |
| Historical order prices unchanged | ✅ snapshots |
| Payment webhooks idempotent | ⏳ no webhooks yet |
| Review eligibility, rating, following, likes | ⏳ modules not built |

## Next steps

| # | What | Size | Depends on |
|---|---|---|---|
| 1 | Expire unpaid orders (scheduled job) | S | timeout decision |
| 2 | Stripe test mode: payment intents, webhooks, `payments` table, idempotency | L | — |
| 3 | E-mail notifications: order paid, shipped, workshop booked / reminder / cancelled, maker review results | M | decision 56.9 |
| 4 | Reviews and maker rating | M | decisions 56.5, 56.6 |
| 5 | Community: posts, comments, likes, following | L | decision 56.8 |
| 6 | Favorites for courses and workshops; price filters | S | — |
| 7 | Google sign-in, password reset | S | mail (3) |
| 8 | CI pipeline on PostgreSQL (tests, Pint, type-check, i18n check) | M | — |
