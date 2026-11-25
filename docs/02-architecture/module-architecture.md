# Craft Woman — Module Architecture

**Version:** 1.0
**Status:** Draft
**Last Updated:** 2026-10-01

---

## 1. Architecture Style

Crafts Woman uses a **Modular Monolith** architecture.

The application is deployed as one Laravel application but is divided into clearly defined business modules.

The architecture follows pragmatic Domain-Driven Design principles.

The goal is to maintain clear business boundaries without introducing the operational complexity of microservices.

---

# 2. Core Principles

## 2.1 One Business Entity Has One Owner

Every business entity has one owning module.

Other modules may use the entity through defined interfaces but must not directly manipulate its persistence model.

---

## 2.2 Roles Are Not Modules

The main roles are:

* Customer
* Maker
* Administrator

Roles describe authorization.

They do not determine module boundaries.

For example, Maker functionality is distributed across Marketplace, Education, Workshops, and Community.

---

## 2.3 No Business Logic in Controllers

Controllers are responsible for:

* receiving HTTP requests;
* validating request structure;
* invoking application services;
* returning API responses.

Business rules belong to the Domain/Application layers.

---

## 2.4 Domain Independence

The Domain layer must not depend on Laravel.

The Domain must not be directly used:

* Eloquent;
* HTTP requests;
* Laravel controllers;
* Laravel validation;
* Stripe SDK;
* framework-specific infrastructure.

---

## 2.5 No Direct Cross-Module Eloquent Access

A module must not directly access another module's Eloquent models.

For example:

```text
Orders
   X
Marketplace\Product::query()
```

Instead, Orders should use an application-level contract or query interface provided by Marketplace.

---

# 3. Modules

> **Implementation status (2026-11-25).** Identity, Marketplace, Education, Workshops and Orders exist in `modules/`. Payments, Reviews, Community and Notifications don't exist yet. Administration has no module of its own: each module serves its `/admin/…` endpoints behind gates (`manage-catalog`, `manage-users`). See [roadmap.md](../07-status/roadmap.md).

The initial system consists of:

```text
Identity
Marketplace
Education
Workshops
Orders
Payments
Reviews
Community
Notifications
Administration
```

---

# 4. Identity Module

## Responsibility

Identity owns user accounts and Maker identity/profile information.

## Entities

* User
* Maker Profile

## Responsibilities

* authentication-related identity;
* user account lifecycle;
* Maker profile;
* Maker approval state;
* user identity data;
* role/permission integration.

Identity does not own products, courses, workshops, or orders.

---

# 5. Marketplace Module

## Responsibility

Marketplace owns products and product discovery data.

## Entities

* Product
* Product Variant
* Product File
* Category
* Tag
* Favorite

## Responsibilities

* physical products;
* digital products;
* product variants;
* inventory;
* product files;
* categories;
* tags;
* product lifecycle;
* product ownership;
* favorites;
* product purchasing information.

Marketplace does not own Orders or Payments.

---

# 6. Education Module

## Responsibility

Education owns courses and learning progress.

## Entities

* Course
* Course Module
* Lesson
* Enrollment
* Lesson Progress
* Certificate

## Responsibilities

* course structure;
* modules;
* lessons;
* learning materials;
* course access;
* enrollment;
* progress;
* certificates.

Education does not own payments.

It reacts to successful purchase/payment events or uses an application-level contract to establish entitlement.

---

# 7. Workshops Module

## Responsibility

Workshops own scheduled workshops and bookings.

## Entities

* Workshop
* Workshop Session
* Booking

## Responsibilities

* workshop information;
* sessions;
* schedules;
* capacity;
* online/offline configuration;
* external meeting links;
* locations;
* bookings.

The module must enforce session capacity.

> **Implementation notes (2026-11-25).** Capacity and format belong to the workshop, not the session: a booking covers every session. Orders holds seats synchronously at checkout through `WorkshopCatalog::reserve()` and releases them on cancellation. Payment confirms them through the `OrderPaid` event. See [decision-log.md](../08-decisions/decision-log.md#2026-11-25--workshops).

---

# 8. Orders Module

## Responsibility

Orders own the commercial transaction after checkout.

## Entities

* Cart
* Order
* Order Group
* Order Item
* Delivery

## Responsibilities

* cart;
* checkout;
* orders;
* Maker-specific order groups;
* order items;
* delivery;
* order state;
* historical purchase information.

An Order may contain multiple Maker groups.

Each Maker group has its own delivery.

---

# 9. Payments Module

## Responsibility

Payments own payment processing and payment-provider integration.

## Entities

* Payment
* Payment Transaction / Provider Event where required

## Responsibilities

* payment creation;
* payment state;
* Stripe integration;
* provider identifiers;
* webhook processing;
* idempotency;
* payment failure handling.

Payments must not contain Order business rules.

---

# 10. Reviews Module

## Responsibility

Reviews own reviews and derived Maker ratings.

## Entities

* Review
* Maker Rating

## Responsibilities

* review creation;
* review eligibility;
* moderation state;
* product ratings;
* course/workshop ratings;
* Maker rating calculation.

The exact rating calculation is defined separately from review storage.

---

# 11. Community Module

## Responsibility

Community owns social interaction functionality.

## Entities

* Post
* Comment
* Like
* Follow

## Responsibilities

* Maker posts;
* comments;
* likes;
* Maker following.

Private messaging is outside the MVP.

---

# 12. Notifications Module

## Responsibility

Notification own notification delivery and preferences.

## Entities

* Notification
* Notification Preference

## Responsibilities

* email notifications;
* notification templates;
* notification preferences;
* queued notification delivery;
* notification state.

Notifications should normally react to domain/integration events rather than being directly coupled to business modules.

---

# 13. Administration Module

Administration provides platform-wide management and moderation capabilities.

Administration does **not** own duplicate versions of:

* Products;
* Orders;
* Courses;
* Workshops;
* Reviews;
* Users.

Instead, the Administration interacts with the owning modules through defined application interfaces.

---

# 14. Entity Ownership

| Entity                  | Owner         |
| ----------------------- | ------------- |
| User                    | Identity      |
| Maker Profile           | Identity      |
| Product                 | Marketplace   |
| Product Variant         | Marketplace   |
| Product File            | Marketplace   |
| Category                | Marketplace   |
| Tag                     | Marketplace   |
| Favorite                | Marketplace   |
| Course                  | Education     |
| Course Module           | Education     |
| Lesson                  | Education     |
| Enrollment              | Education     |
| Lesson Progress         | Education     |
| Certificate             | Education     |
| Workshop                | Workshops     |
| Workshop Session        | Workshops     |
| Booking                 | Workshops     |
| Cart                    | Orders        |
| Order                   | Orders        |
| Order Group             | Orders        |
| Order Item              | Orders        |
| Delivery                | Orders        |
| Payment                 | Payments      |
| Review                  | Reviews       |
| Maker Rating            | Reviews       |
| Post                    | Community     |
| Comment                 | Community     |
| Like                    | Community     |
| Follow                  | Community     |
| Notification            | Notifications |
| Notification Preference | Notifications |

---

# 15. Internal Module Structure

A module may use the following structure:

```text
modules/
└── Marketplace/
    ├── Domain/
    │   ├── Entities/
    │   ├── ValueObjects/
    │   ├── Services/
    │   ├── Events/
    │   ├── Exceptions/
    │   └── Repositories/
    │
    ├── Application/
    │   ├── Commands/
    │   ├── Queries/
    │   ├── DTOs/
    │   └── Services/
    │
    ├── Infrastructure/
    │   ├── Persistence/
    │   ├── External/
    │   └── Providers/
    │
    └── Presentation/
        └── Http/
            ├── Controllers/
            ├── Requests/
            ├── Resources/
            └── Routes/
```

Not every module must contain every directory.

The structure should reflect actual complexity rather than enforce empty abstractions.

> **In the code (2026-11-25).** Modules use `Domain/` (enums, rule classes, exceptions), `Application/{Contracts,Services,Listeners,Events}`, `Infrastructure/{Persistence,Providers}` (Eloquent models, factories and migrations live in `Persistence`) and `Presentation/Http/{Controllers,Resources,Routes}`. There are no repositories, commands or query classes yet. Services use Eloquent directly. Details in [backend.md](../06-development/backend.md).

---

# 16. Module Communication

Modules communicate using:

* application services;
* commands;
* queries;
* DTOs;
* domain events;
* integration events;
* explicit read interfaces.

The mechanism should be chosen according to the use case.

Not every interaction requires an event.

---

# 17. Conceptual Dependencies

Initial dependencies include:

```text
Orders
 ├── Identity
 └── Marketplace

Payments
 └── Orders

Education
 ├── Identity
 └── Orders

Workshops
 ├── Identity
 └── Orders

Reviews
 ├── Identity
 ├── Marketplace
 ├── Education
 └── Workshops

Community
 └── Identity

Notifications
 └── Domain / Integration Events

Administration
 └── Public interfaces of other modules
```

These dependencies describe business relationships rather than direct database access.

---

# 18. Order Architecture

Orders represent a particularly important cross-module boundary.

A customer may purchase products from multiple Makers in one Order.

Conceptually:

```text
Order
│
├── Order Group — Maker A
│   ├── Order Items
│   └── Delivery
│
├── Order Group — Maker B
│   ├── Order Items
│   └── Delivery
│
└── Order Group — Maker C
    ├── Order Items
    └── Delivery
```

Marketplace provides product purchasing information.

Orders create the historical commercial representation.

Payments handle payment processing.

Marketplace remains responsible for product and inventory rules.

---

# 19. Payment Architecture

The Orders module must not depend directly on Stripe.

Instead:

```text
Order
  ↓
Payments Application Interface
  ↓
Payment Provider
  ↓
Stripe
```

This allows additional payment providers to be introduced later.

Stripe-specific classes belong in Payment Infrastructure.

---

# 20. Event-Driven Communication

Events may be used for asynchronous or decoupled reactions.

Example:

```text
PaymentSucceeded
       ↓
 ┌─────┼─────────────┐
 ↓     ↓             ↓
Orders Education Notifications
       ↓
   Enrollment
```

Events should be used where they provide meaningful decoupling.

They should not be introduced merely to make the architecture appear more complex.

> **In the code (2026-11-25).** One integration event exists: `Orders\Application\Events\OrderPaid`, dispatched after commit. Education grants course access and Workshops confirm bookings in response. Holding workshop seats is a synchronous contract call, because it must fail the checkout when seats are gone.

---

# 21. Persistence

PostgreSQL is the primary database.

> **Current state (2026-11-25).** Development and tests run on SQLite. Row locks (`lockForUpdate`) are no-ops there, so concurrency guarantees are only proven once PostgreSQL is in place. See [roadmap.md](../07-status/roadmap.md#known-gaps).

Each module owns its persistence structures.

The application may use one PostgreSQL database because this is a modular monolith.

Logical ownership is more important than physically separating databases.

Cross-module tables should not be created simply to bypass module boundaries.

---

# 22. Caching and Queues

Redis is used where appropriate for:

* caching;
* queues;
* temporary data;
* rate limiting where applicable.

Redis must not become the source of truth for core business entities.

PostgreSQL remains the authoritative persistent store.

---

# 23. Testing Architecture

Each module should contain tests relevant to its responsibilities.

Testing levels include:

* Domain tests;
* Application tests;
* API/Feature tests;
* Integration tests;
* Authorization tests;
* Architecture tests where useful.

Cross-module behavior must also have integration coverage.

---

# 24. Architectural Constraints

The following constraints are mandatory:

1. No direct cross-module Eloquent model manipulation.
2. No business logic in controllers.
3. No Laravel dependency in Domain.
4. No Stripe dependency in Orders Domain.
5. No duplicate business entities inside Administration.
6. No unnecessary generic repositories.
7. No unnecessary base services.
8. No premature microservices.
9. No infrastructure-specific logic inside Domain.
10. Business rules must have automated tests.

---

# 25. Definition of Done

A module is considered architecturally complete when:

* ownership is explicit;
* entities are identified;
* application use cases are defined;
* external dependencies are isolated;
* authorization boundaries are defined;
* cross-module communication is explicit;
* business rules have tests;
* API behavior is documented where applicable;
* persistence does not violate module boundaries.
