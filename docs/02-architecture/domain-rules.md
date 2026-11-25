# Craft Woman — Domain Rules

**Version:** 1.0
**Status:** Draft
**Last Updated:** 2026-09-29

---

## 1. Purpose

This document defines the core business rules of the Craft Woman platform.

These rules describe how the system is expected to behave independently of Laravel, Vue, PostgreSQL, Stripe, or other infrastructure concerns.

The domain rules are the foundation for:

* Domain Model
* Entity and Value Object design
* Database schema
* API behavior
* Application services
* Authorization
* Automated tests

Business rules must not be implemented only in controllers or frontend code.

---

# 2. Identity and Accounts

## 2.1 User

A user account represents an authenticated person using the platform.

A user may have one or more platform roles:

* Customer
* Maker
* Administrator

Guest is not a persistent role. Guest represents an unauthenticated visitor.

## 2.2 Customer

A Customer may:

* purchase products;
* purchase courses;
* book workshops;
* manage favorites;
* follow eligible Makers;
* participate in community interactions;
* track course progress;
* access purchased digital products;
* leave eligible reviews.

## 2.3 Maker

A Maker is a user who has been approved to operate as a seller/content creator on the platform.

A Maker may:

* manage their public profile;
* create products;
* create courses;
* create workshops;
* manage workshop sessions;
* manage inventory;
* manage orders related to their products;
* view relevant customers/students/participants;
* publish community content;
* configure selected profile and notification settings.

Maker functionality is subject to approval and moderation rules.

## 2.4 Administrator

An Administrator has platform-level management and moderation permissions.

Administrators may:

* manage users;
* approve or reject Makers;
* moderate products;
* moderate courses;
* moderate workshops;
* moderate reviews;
* manage categories;
* moderate community content;
* view platform orders and related data.

Administration does not create duplicate ownership of business entities.

---

# 3. Maker Approval

A user must be approved as a Maker before receiving full Maker capabilities.

A Maker approval lifecycle should support at least:

* Pending
* Approved
* Rejected
* Suspended

Only an approved Maker may publish seller-owned content.

A rejected Maker may submit updated information for another review.

A suspended Maker must not be able to perform restricted seller operations while suspended.

Existing customer purchases and historical orders must remain auditable even if a Maker is later suspended.

---

# 4. Ownership

Business entities that belong to a Maker must have a clear owner.

Examples:

* Product → Maker
* Course → Maker
* Workshop → Maker
* Community post → Maker

A Maker may only modify their own entities.

A Maker must not be able to modify another Maker's products, courses, workshops, orders, or other protected resources.

Administrators may manage entities across Makers according to administrative permissions.

Ownership checks must be enforced server-side.

---

# 5. Product Domain

## 5.1 Product Types

The MVP supports two product types:

* Physical product
* Digital product

Physical and digital products have different fulfillment behavior and therefore must not be treated as identical business entities merely because they share common attributes.

Products may be related to another product.

Example:

* Physical knitting kit
* Digital knitting pattern

The exact relationship model is part of the Domain Model design.

---

# 6. Product Lifecycle

A product should support a lifecycle that distinguishes preparation, moderation, publication, and removal.

At minimum, the domain must distinguish:

* Draft
* Pending Review
* Published
* Rejected
* Archived

A product must not be publicly purchasable before it is published.

A rejected product may be edited and submitted again.

An archived product must not be available for new purchases.

Existing order history must remain intact after a product is archived.

Administrators may moderate products regardless of Maker-specific permissions.

---

# 7. Physical Products

A physical product may contain:

* name;
* description;
* price;
* currency;
* images;
* categories;
* tags;
* materials;
* dimensions;
* weight;
* colors;
* variants;
* production time;
* shipping information;
* inventory;
* status.

The exact set of optional/required fields will be finalized in the domain model.

---

# 8. Product Variants

A physical product may have variants.

Examples:

* Color
* Size
* Material
* Combination of attributes

Inventory may be tracked independently for each variant.

A product with variants must not allow inventory operations that bypass variant-level stock where variant stock is applicable.

A variant cannot be purchased if its available stock is zero.

Variant identifiers and attributes must remain stable enough to preserve historical order information.

---

# 9. Inventory

Inventory must never become negative.

The system must prevent concurrent purchases from overselling available inventory.

Inventory-changing operations must be transactional.

Inventory changes caused by an order must be traceable.

The exact inventory reservation strategy is an implementation decision and may use temporary reservations, transactional locking, or another appropriate mechanism.

The business invariant is:

> The system must never confirm a purchase that exceeds available inventory.

---

# 10. Digital Products

A digital product may contain one or more downloadable files.

Digital files must not require the application server to stream large files directly when a more appropriate storage/delivery mechanism is available.

Access to a purchased digital product must only be granted after the required payment condition has been satisfied.

There is no artificial download-count limit in the MVP.

The system must preserve the customer's entitlement even if the product is later unpublished, provided the purchase remains valid.

Digital file storage and delivery infrastructure are implementation concerns.

---

# 11. Historical Product Data

Orders must preserve the commercial information relevant at the time of purchase.

At minimum, historical order data must not change when a Maker later changes:

* product name;
* product price;
* product description;
* product variant;
* product status.

Order records should therefore contain appropriate snapshots of purchase-time information.

The exact snapshot structure will be defined during the database design phase.

---

# 12. Courses

A Course is a purchasable educational product owned by a Maker.

Course structure:

```text
Course
 ├── Module
 │    ├── Lesson
 │    ├── Lesson
 │    └── ...
 ├── Module
 └── ...
```

A Course may contain:

* modules;
* lessons;
* videos;
* text;
* PDFs/materials;
* combinations of these content types.

A course must have an explicit publication lifecycle.

Only published courses may be purchased.

Decision (2026-11-25): video is added as a YouTube or Vimeo link and embedded; the platform doesn't host video. Material types are text, video link, file (private download) and external link.

---

# 13. Course Access

Course access is granted after the required purchase/payment condition has been satisfied.

A Maker may configure course access as:

* Lifetime
* Limited duration

For limited access, the enrollment must contain the relevant access period.

Course access expiration must not delete the customer's historical progress.

The exact behavior after expiration must be enforced consistently across API and frontend.

---

# 14. Course Progress

Course progress is associated with a customer's enrollment.

A customer may mark eligible lessons as completed.

Progress exists primarily to support learning continuity and motivation.

Progress must not be dependent on the frontend alone.

The backend must persist completion state.

The exact definition of course completion must be explicitly defined before certificate implementation.

Decision (2026-11-25): a course is complete when **every required lesson** is marked done. Makers can mark lessons optional; optional lessons don't count.

---

# 15. Certificates

Certificate functionality is supported by the domain but may be disabled by the Maker for an individual course.

If certificates are enabled:

* the customer must satisfy the course completion requirements;
* a certificate may be issued;
* certificate issuance must be recorded.

A certificate must not be issued before the required completion criteria are satisfied.

The exact certificate format and verification mechanism are implementation/product decisions.

Decision (2026-11-25): the certificate is a printable page. Anyone can verify it at `/certificates/{number}` (format `CWC-YYYY-XXXXXX`). Holder, course and maker names are recorded when it is issued.

---

# 16. Workshops

A Workshop is a time-specific event owned by a Maker.

A Workshop may contain multiple sessions.

```text
Workshop
 ├── Session
 ├── Session
 └── ...
```

A session must contain at least:

* date;
* start time;
* end time;
* capacity;
* delivery mode.

Delivery modes:

* Online
* Offline

Online workshops initially use an external meeting link.

The platform does not host video meetings in the MVP.

Offline workshops must contain appropriate location information.

Decision (2026-11-25): the delivery mode (online / offline), location and meeting link are set **for the whole workshop**, not per session. Sessions carry only start and end. The meeting link and exact address are visible only to confirmed participants, the owner and admins; the public page shows the city. The link may be added after review.

---

# 17. Workshop Capacity

A workshop session has a maximum capacity.

Confirmed bookings must never exceed the session capacity.

Concurrent bookings must be handled safely.

The backend is responsible for enforcing capacity.

A frontend availability check is informational only and cannot be treated as sufficient protection against overbooking.

Decision (2026-11-25): capacity is **one number per workshop**, because a booking covers all its sessions. Seats are held when the order is placed (row lock on the workshop), confirmed on payment and released when the order is cancelled. Pending and confirmed bookings both count against capacity.

---

# 18. Workshop Booking

A Customer may book an available workshop session if:

* the session is open for booking;
* capacity is available;
* required payment conditions are satisfied.

A booking must be associated with the Customer and the relevant Workshop Session.

Historical bookings must remain auditable after a Workshop or Session is no longer available for new bookings.

Decision (2026-11-25):

* A booking covers the whole Workshop; one order line may book several seats.
* Booking closes when the first session starts.
* Customers can't cancel a booking in the MVP. The Maker can cancel the whole Workshop with a reason; every booking becomes cancelled and keeps that reason. Refunds are manual until online payment exists.
* Former participants can still open a cancelled Workshop's page to see the reason.

---

# 19. Cart

A Customer has a shopping cart containing purchasable items.

A cart may contain products belonging to multiple Makers.

The cart must validate:

* product availability;
* product status;
* inventory;
* current purchasable price;
* Maker-specific purchasing rules;
* payment eligibility.

The cart must not be treated as a permanent record of the final commercial terms.

The Order created during checkout becomes the authoritative historical record.

---

# 20. Orders

The platform uses a single top-level Order for a multi-Maker purchase.

Example:

```text
Order #1001
│
├── Maker A
│   ├── Items
│   └── Delivery
│
├── Maker B
│   ├── Items
│   └── Delivery
│
└── Maker C
    ├── Items
    └── Delivery
```

The exact entity name for the seller-specific group is still open:

* `OrderGroup`
* `SellerOrder`
* another domain-specific name

The group must represent the portion of an Order belonging to one Maker.

---

# 21. Order Groups

Each Maker represented in an Order must have a separate seller-specific group.

An Order Group contains:

* Maker reference;
* relevant order items;
* delivery information;
* fulfillment state.

An Order may contain multiple Order Groups.

A Maker may only access Order Groups belonging to that Maker.

The Customer may access the complete Order.

---

# 22. Order Items

An Order Item represents an item purchased at checkout.

It must preserve the relevant purchase-time information, including:

* product reference;
* product type;
* Maker reference;
* selected variant where applicable;
* quantity;
* unit price;
* currency;
* applicable item-level snapshot data.

Changing the current Product must not rewrite historical Order Items.

---

# 23. Order Status

Physical product orders should support a lifecycle including:

* Pending Payment
* Paid
* Processing
* Shipped
* Delivered
* Cancelled

Digital purchases may use a lifecycle including:

* Pending Payment
* Paid
* Completed
* Refunded

The exact status model for a multi-Maker Order must distinguish:

* overall Order state;
* seller-specific fulfillment state.

The platform must not assume that every Maker fulfills an Order at the same time.

---

# 24. Delivery

Delivery belongs to an individual Maker's Order Group.

A single multi-Maker Order may therefore contain multiple deliveries.

Example:

```text
Order #1001

Order Group A
└── Delivery A

Order Group B
└── Delivery B

Order Group C
└── Delivery C
```

Delivery-provider integrations are outside the MVP.

The architecture must allow future integration with external delivery providers without coupling the Order domain directly to a specific provider.

---

# 25. Payment Methods

The MVP supports:

* Online payment
* Payment on delivery

A Maker may configure whether payment on delivery is available.

The backend must validate payment method availability during checkout.

The frontend must not be trusted to enforce payment restrictions.

---

# 26. International Payment Rule

If an order requires international delivery:

> Payment on delivery is not allowed.

The order must be fully prepaid.

This rule is mandatory and must be enforced server-side.

It must apply even if the relevant Maker has enabled payment on delivery.

---

# 27. Multi-Maker Payment Rules

A multi-Maker Order may contain several Order Groups.

Payment behavior must be consistent with the rules of all affected groups.

An order must not enter a confirmed paid state unless the required payment conditions for the entire order have been satisfied.

Decision (2026-11-24):

* Online payment is always available.
* Payment on delivery is available only when **every** Maker in the Order allows it.
* Payment on delivery is never available for international delivery (country differs from the Maker's country) — full prepayment is required.
* Payment on delivery is never available when the Order contains digital items — files are released only after payment.
* The rules are evaluated by `Modules\Orders\Domain\PaymentPolicy`; checkout shows the reasons when a method is unavailable, and the server re-checks them when the Order is placed.

Status flow:

* online — the Order starts as `pending_payment`; after payment physical groups move to `awaiting_shipment`, digital-only groups to `completed`;
* on delivery — groups start as `awaiting_shipment`; the Order is marked paid when the last group is delivered.

---

# 28. Stripe

Stripe is used as a test payment integration for the MVP.

The Orders domain must not depend directly on Stripe.

Payment-provider-specific functionality belongs to the Payments module.

The Payments module is responsible for:

* payment creation;
* provider communication;
* payment status;
* provider identifiers;
* webhook processing;
* idempotency;
* payment failure handling.

The domain should operate on payment concepts rather than Stripe-specific objects.

---

# 29. Payment Webhooks

Payment webhooks must be treated as potentially duplicated and/or delivered out of order.

Webhook processing must therefore be idempotent.

The same provider event must not:

* create duplicate payments;
* create duplicate entitlements;
* duplicate order transitions;
* duplicate inventory operations.

Provider event identifiers should be persisted where necessary to enforce idempotency.

---

# 30. Reviews

Reviews may be created for:

* physical products;
* digital products;
* courses;
* workshops.

A Customer must have a valid purchase, enrollment, or booking relationship before becoming eligible to review the relevant target.

Reviews must contain:

* rating;
* optional text;
* author;
* target;
* creation timestamp.

Administrators may moderate reviews.

---

# 31. Review Eligibility

The backend must validate review eligibility.

A user must not be able to create a review merely by knowing the target entity ID.

The exact rule for multiple purchases/bookings and whether one customer may submit multiple reviews for the same target remains an open decision.

---

# 32. Maker Rating

Maker rating is derived from ratings of the Maker's products.

A Maker does not manually enter their overall rating.

The rating calculation must be deterministic and reproducible.

The exact aggregation formula is an open decision and must be finalized before implementation.

---

# 33. Community

The MVP community supports Maker-created posts.

A post may contain:

* text;
* images;
* links;
* references to Maker-owned products or profile content.

Users may:

* like posts;
* comment;
* follow Makers.

Advanced recommendation algorithms and private messaging are outside the MVP.

---

# 34. Maker Following

A Customer may follow a Maker only when that Maker has enabled the following feature.

If the Maker disables the following:

* new users cannot follow the Maker;
* the existing following relationships require a defined product decision.

The behavior for existing followers after disabling the feature is an open decision.

---

# 35. Community Authorization

A Maker may manage only their own posts.

A user may modify or delete their own eligible community content, according to the final authorization rules.

Administrators may moderate community content.

Like and follow operations must be idempotent from the user's perspective.

Repeatedly liking the same post must not create duplicate active likes.

---

# 36. Notifications

The MVP uses email notifications.

Potential notification events include:

* order created;
* payment successful;
* payment failed;
* order shipped;
* order delivered;
* course purchased;
* workshop booking confirmed;
* workshop reminder;
* new review;
* product approved/rejected;
* new follower;
* new community content.

Transactional notifications and optional notifications should be treated separately.

Critical transactional notifications should not depend on an optional marketing-style preference.

Maker-configurable notifications may be disabled where the product rules permit it.

---

# 37. Notification Preferences

Notification preferences belong to the relevant user/Maker configuration.

Preferences must not override a mandatory system or transactional communication.

The exact list of mandatory versus optional notification types must be finalized before implementation.

---

# 38. Categories and Tags

Administrators manage platform categories.

Products and other supported content may belong to multiple categories where the domain allows it.

Tags may be used for discovery and filtering.

Category and tag assignment must not bypass ownership or moderation rules.

The exact taxonomy and whether courses, workshops, and community posts share or use separate taxonomies will be finalized during domain modeling.

---

# 39. Search and Filtering

The platform must support discovery across:

* physical products;
* digital products;
* Makers;
* courses;
* workshops;
* community content.

Potential filters include:

* category;
* price;
* Maker;
* rating;
* location;
* online/offline;
* date;
* product type;
* availability.

Search implementation is an infrastructure concern.

The domain must expose enough structured information to support these filters without coupling the business domain to a specific search engine.

---

# 40. Money and Currency

The platform supports:

* EUR
* USD
* UAH

Money must be represented as:

```text
amount + currency
```

Currency must never be inferred from the amount alone.

Monetary calculations must use exact decimal-safe representations.

Floating-point arithmetic must not be used for financial calculations.

Historical orders must preserve the currency used at the time of purchase.

---

# 41. Currency Conversion

Automatic currency conversion is not yet defined.

The platform must not silently convert monetary values using an undocumented exchange rate.

The following checkout behavior for carts containing products priced in different currencies must be explicitly defined before implementation.

---

# 42. Internationalization

The MVP supports:

* Ukrainian
* English

User-facing translatable content must be designed so that adding additional languages later does not require restructuring core business entities.

Decision (2026-11-25):

| Area | Strategy |
|---|---|
| Interface (SPA) | Translation files per language (`resources/js/i18n/uk.json`, `en.json`, vue-i18n). |
| API messages | Laravel `lang/{uk,en}`; the language comes from `Accept-Language`. |
| Language choice | Ukrainian is the default and has no URL prefix; English lives under `/en/…`. The header switcher changes the URL; signed-in users also have `users.locale`, and a user whose saved language is English is taken to `/en/…`. |
| Administrator content (categories) | Fully translated: source name plus a translation for every supported language. |
| Maker content (products, maker profiles; later courses and workshops) | Written in one source language (`content_locale`). Translations are optional. Without one, the source text is shown with a mark of its language. |
| Tags, variant names, order snapshots | Not translated. Order items keep the name the buyer saw at checkout. |

Storage: translatable entities have `content_locale` and a JSON `translations` column keyed by locale, e.g. `{"en": {"name": "…", "description": "…"}}`. Adding a language adds keys, not columns or tables (`App\Support\HasTranslations`, `App\Support\Locales`).

---

# 43. Authorization

Authorization is based on the user's permissions and ownership context.

Roles are authorization concepts and are not domain modules.

At minimum:

```text
Guest
Customer
Maker
Administrator
```

A user's ability to perform an operation must be checked server-side.

Frontend visibility is not an authorization mechanism.

---

# 44. Administrative Access

Administrators have platform-wide management access, according to the administration policy.

Administrative access must not be implemented by duplicating Maker or Customer entities.

Administration operates through module interfaces and application services.

Sensitive administrative operations should be auditable.

---

# 45. Moderation

Content requiring moderation may include:

* Makers;
* products;
* courses;
* workshops;
* reviews;
* community posts;
* comments.

Moderation must have an explicit state or decision that can be persisted and audited.

Rejected content should retain enough information to understand the moderation outcome.

The exact moderation workflow and reason model will be finalized before implementation.

---

# 46. Concurrency

Operations that modify shared resources must be safe under concurrent requests.

Important examples:

* inventory decrement;
* workshop booking;
* payment processing;
* duplicate webhook handling;
* favorite creation;
* likes;
* checkout.

A successful frontend availability check must never be considered sufficient protection against concurrent modification.

---

# 47. Idempotency

Operations that may be retried must be designed for idempotency.

Examples:

* payment webhook handling;
* payment confirmation;
* order creation where a retry could otherwise create duplicates;
* workshop booking where applicable;
* like/follow operations;
* notification dispatch where duplicate delivery would be harmful.

Idempotency keys or equivalent mechanisms should be used where appropriate.

---

# 48. Auditability

Important business events should be traceable.

At minimum, the architecture should allow investigation of:

* Maker approval;
* content moderation;
* payment state changes;
* order state changes;
* inventory changes;
* workshop capacity changes;
* certificate issuance.

The exact audit-log implementation is an infrastructure/application concern.

---

# 49. Cross-Module Rules

Modules must communicate through defined application-level contracts, commands, queries, or domain/integration events.

A module must not directly manipulate another module's Eloquent models.

Examples:

* Orders may request product purchasing information from Marketplace.
* Payments may update the payment state associated with an Order through an application contract.
* Education may create enrollment after a successful purchase.
* Workshops may create a booking state after a successful purchase/payment condition.
* Reviews may verify an eligible customer relationship through an appropriate module interface.
* Notifications may react to relevant domain/integration events.

Cross-module dependencies must remain explicit.

---

# 50. Domain Independence

The Domain layer must not depend on Laravel framework classes.

Domain rules must not require:

* HTTP requests;
* Controllers;
* Eloquent models;
* Laravel validation;
* database queries;
* Stripe SDK objects.

Infrastructure and framework-specific concerns belong outside the Domain layer.

---

# 51. Transaction Boundaries

Business operations that must succeed or fail together should execute within an appropriate transaction boundary.

Examples may include:

* checkout/order creation;
* inventory changes;
* workshop booking;
* payment state transitions;
* entitlement creation.

The exact transaction boundary must be determined during application-service design.

Distributed operations involving external providers must not assume that a database transaction can atomically include the external provider.

---

# 52. Security Invariants

The backend must enforce:

* ownership;
* role/permission checks;
* purchase entitlements;
* digital file access;
* course access;
* workshop booking eligibility;
* inventory constraints;
* payment rules;
* moderation states.

Sensitive resources must not be exposed merely because a user knows an identifier.

Digital files must use controlled access mechanisms rather than publicly exposing protected storage paths.

---

# 53. Historical Integrity

Historical business records must remain meaningful even when current entities change.

Examples:

* changing a product price must not change an existing order;
* changing a product name must not rewrite historical order information;
* archiving a Maker's product must not delete historical purchases;
* changing workshop availability must not erase completed bookings;
* changing a course must not invalidate existing enrollment history without an explicit business rule.

Deletion behavior for historical entities must therefore be carefully controlled.

---

# 54. Data Deletion

Hard deletion must not be used where it would destroy legally or operationally important historical information.

Orders, payments, bookings, and other financial records should generally remain auditable.

Personal-data deletion requirements will be handled separately as part of the privacy/data-retention design.

---

# 55. MVP Exclusions

The following features are intentionally outside the MVP domain:

* full refund workflow;
* delivery-provider integrations;
* hosted video;
* private messaging;
* advanced recommendation engine;
* advanced analytics;
* advanced search engine;
* mobile application;
* additional payment providers;
* subscriptions;
* bundles;
* coupons;
* loyalty system;
* advanced seller payouts;
* automated tax calculation.

The architecture may expect these capabilities but must not introduce unnecessary complexity before they are required.

---

# 56. Open Business Decisions

The following decisions must be resolved before the final ERD and implementation.

> **Status (2026-11-25).** Resolved: 56.1 (`OrderGroup`), 56.2 (one currency per cart), 56.3 (payment on delivery only if every Maker allows it), 56.4 (the Maker's delivery currency must match the cart), 56.7 (all required lessons), 56.10 (free-text note, 5+ characters). Still open: 56.5, 56.6, 56.8, 56.9. Answers and reasons: [decision-log.md](../08-decisions/decision-log.md).

## 56.1 Order Group Naming

Choose the domain term:

* `OrderGroup`
* `SellerOrder`
* another name

The chosen term should describe one Maker's portion of a multi-Maker Order.

## 56.2 Multi-Currency Cart

Define what happens when a cart contains products with different currencies.

Possible product behaviors include:

* prevent mixed-currency checkout;
* convert all items into a selected checkout currency;
* require a currency selection before checkout.

No behavior is assumed yet.

## 56.3 Multi-Maker Payment-on-Delivery

Define what happens when one Order contains Makers with different payment-on-delivery policies.

For example:

```text
Maker A → COD allowed
Maker B → COD disabled
```

The checkout rule must be explicit.

## 56.4 Delivery Currency

Define whether delivery costs:

* use the Order currency;
* use the Maker's currency;
* are converted;
* follow another rule.

## 56.5 Review Multiplicity

Define whether a customer may:

* leave one review per target;
* leave one review per purchase;
* update an existing review;
* review again after a later purchase.

## 56.6 Maker Rating Formula

Define:

* average calculation;
* treatment of multiple reviews;
* rounding;
* whether all product reviews have equal weight.

## 56.7 Course Completion

Define the exact completion rule used for certificates.

Possible criteria may include:

* all lessons completed;
* percentage threshold;
* explicit final lesson;
* Maker-defined completion rule.

## 56.8 Existing Followers

Define what happens to existing followers when a Maker disables following.

## 56.9 Mandatory Notifications

Define which notifications are mandatory transactional messages and which can be disabled.

## 56.10 Moderation Reasons

Define whether rejection/moderation decisions require:

* free-text reason;
* predefined reason;
* both.

---

# 57. Domain Rule Testing

Every business invariant described in this document must have automated test coverage before the corresponding functionality is considered complete.

High-priority tests include:

* Maker cannot publish before approval;
* Maker cannot modify another Maker's entity;
* product inventory cannot become negative;
* concurrent purchase cannot oversell inventory;
* digital access requires valid entitlement;
* of course, access respects expiration;
* certificate cannot be issued before completion;
* workshop capacity cannot be exceeded;
* one Order may contain multiple Maker groups;
* each Maker group has its own delivery;
* international orders cannot use payment on delivery;
* payment webhook processing is idempotent;
* in historical orders, prices remain unchanged;
* unauthorized users cannot access protected digital files;
* reviews require valid eligibility;
* Maker rating is derived from product ratings;
* disabled Maker following prevents new follows;
* duplicate likes/follows do not create duplicate relationships.

---

# 58. Definition of Domain Completeness

The domain model is considered sufficiently defined for implementation when:

1. Core entities are identified.
2. Entity ownership is explicit.
3. Aggregate boundaries are defined.
4. Value Objects are identified.
5. State transitions are defined.
6. Cross-module interactions are documented.
7. Financial invariants are defined.
8. Inventory and capacity invariants are defined.
9. Authorization boundaries are explicit.
10. Historical-data requirements are understood.
11. Open business decisions required for the ERD are resolved.
12. Business rules have corresponding test scenarios.

---

# 59. Next Architecture Artifacts

> **Historical.** This was the plan before implementation. The design documents and the first modules now exist; the current plan is [roadmap.md](../07-status/roadmap.md).

After the open business decisions are resolved, the architecture work should proceed in this order:

```text
Domain Rules
    ↓
Domain Model
    ↓
Aggregates & Boundaries
    ↓
Value Objects
    ↓
ERD
    ↓
PostgreSQL Schema
    ↓
Laravel Module Structure
    ↓
API Contract
    ↓
Application Services
    ↓
Tests
    ↓
Implementation
```

The database schema must be derived from the domain model rather than being designed independently of the business rules.
