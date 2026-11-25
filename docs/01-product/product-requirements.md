# Craft Woman — Product Requirements Specification

**Version:** 1.0
**Status:** Draft
**Last Updated:** 2026-10-01

---

## 1. Product Vision

Crafts Woman is an international creative marketplace and community platform where people can discover Makers, purchase handmade and digital products, learn through courses, attend workshops, and participate in a creative community.

The platform combines:

* Marketplace
* Education
* Workshops
* Maker profiles
* Community

The product should feel warm, creative, minimal, modern, and easy to use.

The platform is designed for all genders.

The primary goal is discovery and connection with Makers, not aggressive sales.

---

# 2. Target Users

The platform supports four access levels:

* Guest
* Customer
* Maker
* Administrator

---

## 2.1 Guest

Guests can:

* browse public content;
* search products, Makers, courses, workshops, and community content;
* filter available content;
* view public Maker profiles;
* view public product pages;
* view public course pages;
* view public workshop information.

A Guest may start checkout and create an account during checkout.

---

## 2.2 Customer

Customers can:

* browse and search the platform;
* manage favorites;
* add products to the cart;
* purchase physical products;
* purchase digital products;
* purchase courses;
* book workshops;
* view orders and purchases;
* access purchased digital products;
* track course progress;
* leave eligible reviews;
* follow Makers when following is enabled;
* receive email notifications.

---

## 2.3 Maker

A Maker can:

* create and manage a public profile;
* sell physical products;
* sell digital products;
* create courses;
* create workshops;
* create workshop sessions;
* manage inventory;
* manage relevant orders;
* view students and workshop participants;
* view basic statistics;
* publish community posts;
* enable or disable the following;
* configure selected notification preferences;
* configure whether payment on delivery is available;
* configure course access duration;
* configure whether certificates are enabled.

A Maker must be approved by an Administrator before becoming fully active.

---

## 2.4 Administrator

Administrators can:

* manage users;
* approve or reject Makers;
* manage products;
* manage courses;
* manage workshops;
* manage orders;
* manage reviews;
* manage categories;
* moderate community content;
* view platform-wide information.

Administrators have access to platform data regardless of Maker-specific settings.

---

# 3. Maker Profiles

Each approved Maker has a public profile.

A Maker profile may contain:

* profile photo;
* name;
* description;
* story;
* location;
* categories;
* products;
* courses;
* workshops;
* reviews;
* rating.

Maker rating is calculated from ratings received by the Maker's products.

A Maker may choose whether Customers can follow them.

---

# 4. Physical Products

Physical products represent handmade physical goods.

A physical product may contain:

* name;
* description;
* price;
* currency;
* photos;
* categories;
* tags;
* stock;
* materials;
* dimensions;
* weight;
* colors;
* variants;
* production time;
* shipping information;
* status.

Products may have variants such as:

* size;
* color;
* material;
* other configurable attributes.

Inventory may be tracked at variant level.

The system must prevent selling more items than are available.

---

# 5. Digital Products

Digital products represent downloadable or digitally accessible goods.

A digital product may contain:

* name;
* description;
* price;
* currency;
* images;
* category;
* tags;
* downloadable files;
* status.

Digital products support:

* direct file download;
* access to a dedicated product page.

The MVP does not impose an artificial download-count limit.

Digital files must be delivered through controlled access mechanisms.

Physical and digital products may be related.

Example:

> Physical knitting kit and digital knitting pattern.

---

# 6. Courses

Courses are purchasable educational products.

Course structure:

```text
Course
 ├── Module
 │    ├── Lesson
 │    └── Lesson
 └── Module
      └── Lesson
```

Lessons may contain:

* video;
* text;
* PDF;
* other learning materials;
* combinations of the above.

Course access can be configured as:

* lifetime;
* limited duration.

Customers can track lesson completion and course progress.

Certificate support is included in the platform.

A Maker may enable or disable certificates for a course.

---

# 7. Workshops

Workshops are scheduled events created by Makers.

A Workshop may contain multiple sessions.

Each session includes:

* date;
* start time;
* end time;
* capacity;
* online/offline mode.

Online workshops use an external meeting link in the MVP.

The platform does not host video meetings.

Offline workshops contain location information.

The system must prevent bookings from exceeding session capacity.

---

# 8. Cart

A Customer can have a cart containing products from multiple Makers.

Example:

```text
Cart
 ├── Maker A
 │    └── Product
 ├── Maker B
 │    └── Product
 └── Maker C
      └── Product
```

The cart validates:

* product availability;
* inventory;
* product status;
* current price;
* payment eligibility.

The final commercial information is stored in the Order created during checkout.

---

# 9. Orders

A single Order may contain products from multiple Makers.

The Order is divided into Maker-specific groups.

Example:

```text
Order
 ├── Maker A
 │    ├── Items
 │    └── Delivery
 ├── Maker B
 │    ├── Items
 │    └── Delivery
 └── Maker C
      ├── Items
      └── Delivery
```

There is one top-level Order.

Each Maker has a separate delivery.

Order history must preserve purchase-time product information.

Changing the current product must not change historical order information.

---

# 10. Payments

The MVP supports:

* online payment;
* payment on delivery.

A Maker may enable or disable payment on delivery.

International delivery requires full prepayment.

Stripe will be integrated as the initial online payment provider in test mode.

Payment-provider-specific logic belongs to the Payments module.

---

# 11. Favorites

Customers can add products to favorites.

The architecture should allow favorites to be extended to:

* Makers;
* courses;
* workshops.

---

# 12. Search and Filtering

The platform supports discovery across:

* products;
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

---

# 13. Community

The MVP includes Maker-created community posts.

Posts may contain:

* text;
* images;
* links;
* references to Maker products or profiles.

Customers can:

* like posts;
* comment;
* follow Makers.

Private messaging is not included in the MVP.

Advanced recommendation algorithms are not included in the MVP.

---

# 14. Notifications

Notifications use email in the MVP.

Potential notifications include:

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

Some notifications may be configurable by the Maker.

---

# 15. Reviews

Reviews are supported for:

* physical products;
* digital products;
* courses;
* workshops.

A Customer must have a valid purchase, enrollment, or booking relationship before reviewing the relevant target.

Reviews contain:

* rating;
* text;
* author;
* target;
* date.

Administrators can moderate reviews.

---

# 16. Internationalization

The MVP supports:

### Languages

* Ukrainian
* English

### Currencies

* EUR
* USD
* UAH

The architecture should allow additional languages and currencies in the future.

---

# 17. UX Principles

The platform should provide:

* minimal configuration;
* clean interface;
* minimal checkout steps;
* clear navigation;
* responsive design;
* accessible interactions.

The visual direction is:

* warm;
* handmade;
* modern;
* minimal;
* creative;
* inclusive.

The homepage should focus on discovery.

Possible homepage sections:

* Search;
* Discover Makers;
* Meet the Makers;
* Learn Something New;
* Upcoming Workshops;
* Community;
* Featured Products.

---

# 18. Technology

> **Current state (2026-11-25).** In use: Laravel 12 (PHP 8.2+), Sanctum session auth, Pest, Vue 3 + TypeScript + Vite, Pinia, vue-i18n and Tailwind CSS 4. SQLite for now. Not yet: PostgreSQL, Redis, Docker, OpenAPI. Queues use the `database` driver.

## Backend

* PHP
* Laravel
* REST API
* PostgreSQL
* Redis
* queues/jobs
* Pest

## Frontend

* Vue 3
* TypeScript
* REST API client

## Infrastructure

* Docker
* AWS-ready architecture

## API

* REST
* `/api/v1/...`
* OpenAPI 3.1

---

# 19. Architecture

The backend uses a modular monolith architecture.

The architecture follows pragmatic Domain-Driven Design principles.

The system should avoid unnecessary abstraction.

Business logic must not be placed in controllers.

Roles are authorization concepts, not modules.

Modules communicate through defined application interfaces, commands, queries, and events.

The Domain layer must not depend on Laravel.

---

# 20. Core Modules

The initial module structure is:

* Identity
* Marketplace
* Education
* Workshops
* Orders
* Payments
* Reviews
* Community
* Notifications
* Administration

Detailed ownership and dependencies are defined in the Module Architecture document.

---

# 21. Testing

The project uses Pest.

Business rules must have automated tests.

Testing should include:

* unit tests;
* feature tests;
* API tests;
* authorization tests;
* integration tests where appropriate;
* architecture tests where useful.

Every important business invariant must have a corresponding automated test.

---

# 22. MVP Scope

> What is built and what is left: [roadmap.md](../07-status/roadmap.md).

The MVP includes:

* authentication;
* Customer accounts;
* Maker accounts;
* Maker approval;
* Maker profiles;
* physical products;
* digital products;
* product variants;
* inventory;
* categories;
* tags;
* search and filters;
* favorites;
* cart;
* multi-Maker orders;
* separate Maker deliveries;
* online payments;
* payment on delivery;
* Stripe test integration;
* courses;
* course modules;
* lessons;
* course purchases;
* course access;
* progress tracking;
* certificates;
* workshops;
* workshop sessions;
* capacity management;
* workshop bookings;
* reviews;
* Maker ratings;
* community posts;
* likes;
* comments;
* Maker following;
* email notifications;
* administration;
* moderation;
* Ukrainian and English;
* EUR, USD, and UAH.

---

# 23. Future Scope

The following features are outside the MVP:

* full refund workflow;
* delivery-provider integrations;
* hosted video;
* private messaging;
* advanced recommendations;
* advanced analytics;
* advanced search engine;
* mobile applications;
* additional payment providers;
* subscriptions;
* bundles;
* coupons;
* loyalty programs;
* advanced seller payouts;
* automated tax calculation.

---

# 24. Success Criteria

The project is considered technically successful when:

1. Core business rules are implemented server-side.
2. Important business rules have automated tests.
3. The API is documented using OpenAPI.
4. Modules have clear ownership boundaries.
5. Cross-module dependencies are explicit.
6. PostgreSQL is used as the primary database.
7. Redis is used for appropriate caching/queue workloads.
8. Payment processing is isolated from the Order domain.
9. Inventory and workshop capacity are concurrency-safe.
10. Sensitive resources are protected by server-side authorization.
11. The system can be run locally using Docker.
12. The architecture can be extended without introducing unnecessary coupling.
