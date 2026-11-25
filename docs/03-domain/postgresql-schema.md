# Craft Woman — PostgreSQL Schema

## 1. Purpose

This document defines the logical PostgreSQL schema for the Craft Woman modular monolith.

The schema translates the conceptual domain model and ERD into a concrete relational structure while preserving the module boundaries defined by the application architecture.

The schema is designed for:

* PostgreSQL
* Laravel
* REST API
* UUID-based primary keys
* modular monolith architecture
* transactional consistency
* historical order integrity
* internationalization
* multi-Maker orders
* physical and digital products
* courses and workshops
* community features
* reviews and ratings

This document describes the database structure and constraints. It does not define Laravel migrations or Eloquent implementation details.

---

# 2. Database Principles

## 2.1 Database engine

PostgreSQL is the primary relational database.

```text
PostgreSQL
```

One PostgreSQL database is used by the modular monolith.

Application modules share the same database, but module ownership and access rules are enforced at the application level.

---

## 2.2 Primary key convention

Every business entity uses a UUID primary key named:

```text
uuid
```

There is no separate `id` column.

Example:

```text
products
├── uuid                  PK
├── maker_profile_uuid    FK
├── name
├── description
└── ...
```

PostgreSQL type:

```sql
uuid
```

Example:

```sql
uuid uuid primary key
```

---

## 2.3 Foreign key convention

Foreign keys use the referenced entity name followed by `_uuid`.

Examples:

```text
user_uuid
maker_profile_uuid
product_uuid
product_variant_uuid
course_uuid
workshop_uuid
order_uuid
payment_uuid
```

This convention is used consistently throughout the schema.

---

## 2.4 Human-readable identifiers

Technical UUIDs are not intended to be displayed to customers.

Where a human-readable identifier is useful, a separate field is introduced.

For example:

```text
orders
├── uuid
├── order_number
```

`order_number` is a business/display identifier and is not the primary key.

---

## 2.5 Timestamps

Entities that require lifecycle tracking use:

```text
created_at timestamptz
updated_at timestamptz
```

Nullable lifecycle timestamps may also be used:

```text
published_at
approved_at
completed_at
deleted_at
```

All timestamps are stored as `timestamptz`.

Application code is responsible for timezone presentation.

---

## 2.6 Money

Money must never be stored as floating-point numbers.

Monetary values use:

```text
amount_minor bigint
currency char(3)
```

Example:

```text
amount_minor = 2599
currency = EUR
```

represents:

```text
€25.99
```

Supported currencies:

```text
EUR
USD
UAH
```

---

## 2.7 Status fields

Statuses are stored as strings rather than PostgreSQL enums.

Example:

```text
status varchar(32)
```

This keeps the schema flexible and allows domain/application code to own the allowed state transitions.

Allowed values should be enforced by application/domain rules and, where appropriate, database `CHECK` constraints.

---

# 3. Identity Module

## 3.1 users

> **Implementation notes (2026-11-25).** The code keeps a single `role` column (`buyer`, `master`, `admin`) instead of `roles`/`user_roles`, and `name` instead of first/last name. `status` (`active` | `blocked`), `blocked_at` and `blocked_reason` are implemented; `locale`, `avatar_url` and soft deletes are not yet.

Stores authenticated users.

```text
users
├── uuid                  PK
├── email                UNIQUE
├── password
├── first_name
├── last_name
├── avatar_url
├── locale
├── status
├── email_verified_at
├── created_at
├── updated_at
└── deleted_at
```

Important rules:

* Email must be unique among active users.
* A user may have multiple roles.
* A user may become a Maker through an approved Maker profile.
* A Maker remains a Customer as well.
* Soft deletion may be used.

---

## 3.2 roles

Stores system roles.

```text
roles
├── uuid                  PK
├── name                  UNIQUE
├── created_at
└── updated_at
```

Initial roles:

```text
customer
maker
administrator
```

Guest is not stored as a database role because Guest represents an unauthenticated state.

---

## 3.3 user_roles

Many-to-many relationship between users and roles.

```text
user_roles
├── user_uuid             FK
├── role_uuid             FK
├── created_at
└── PRIMARY KEY (user_uuid, role_uuid)
```

Foreign keys:

```text
user_uuid → users.uuid
role_uuid → roles.uuid
```

---

## 3.4 maker_profiles

Stores the Maker-specific profile of a User.

```text
maker_profiles
├── uuid                  PK
├── user_uuid             FK UNIQUE
├── display_name
├── slug                  UNIQUE
├── bio
├── story
├── avatar_url
├── country_code
├── city
├── address
├── status
├── follow_enabled
├── review_note           NULL — reviewer's message for rejected/suspended
├── approved_at
├── created_at
├── updated_at
└── deleted_at
```

Possible Maker profile statuses:

```text
draft
pending_review
approved
rejected
suspended
```

Rules:

* One User may have at most one MakerProfile.
* A MakerProfile must belong to a User.
* A Maker must be approved before using full Maker capabilities.
* MakerProfile is the source of public Maker information.
* Allowed transitions: draft → pending_review → approved | rejected; rejected → pending_review;
  approved → suspended → approved. Rejecting or suspending requires a `review_note`.
* The slug follows `display_name` until the profile is approved; after that it is stable.
* Only approved profiles are public. Others are visible to the owner and administrators.

---

# 4. Marketplace Module

## 4.1 products

Represents physical and digital products.

```text
products
├── uuid                  PK
├── maker_profile_uuid    FK
├── type
├── name
├── slug
├── description
├── price_amount_minor
├── currency
├── status
├── production_time_days
├── shipping_info
├── review_note           NULL — moderator's message (rejected/archived)
├── published_at
├── created_at
├── updated_at
└── deleted_at
```

Product types:

```text
physical
digital
```

Product statuses:

```text
draft
pending_review
published
rejected
archived
```

Rules:

* Every product belongs to exactly one Maker.
* Only approved Makers can create sellable products.
* Only published products are purchasable.
* Physical products must have at least one ProductVariant.
* Digital products do not consume physical inventory.
* Allowed transitions: draft → pending_review → published | rejected; rejected → pending_review;
  published → archived; archived → draft (relist, reviewed again).
* Submitting for review requires: name (3+), description (20+), price > 0, a category, at least one image;
  physical — at least one active variant; digital — at least one file.
* The slug follows the name until the first publication; after that it is stable.
* Only drafts can be deleted; published products are archived to keep order history intact.
* Products of suspended Makers are hidden from the public catalog.

---

## 4.2 product_variants

Inventory belongs to ProductVariant.

```text
product_variants
├── uuid                  PK
├── product_uuid          FK
├── sku
├── name
├── price_amount_minor
├── currency
├── stock
├── weight_grams
├── is_default
├── status
├── created_at
└── updated_at
```

Foreign key:

```text
product_uuid → products.uuid
```

Rules:

* A ProductVariant belongs to exactly one Product.
* Stock must never become negative.
* Physical products must have at least one variant.
* A default variant may represent products without meaningful customer-visible variations.
* Digital products do not require physical stock.

Suggested constraint:

```text
stock >= 0
```

---

## 4.3 product_images

Stores product image references.

```text
product_images
├── uuid                  PK
├── product_uuid          FK
├── file_url
├── storage_path          path on the public disk (for deletion)
├── alt_text
├── sort_order
├── created_at
└── updated_at
```

Foreign key:

```text
product_uuid → products.uuid
```

The database stores file references rather than binary image data.

---

## 4.4 product_files

Stores downloadable digital product files.

```text
product_files
├── uuid                  PK
├── product_uuid          FK
├── file_name
├── storage_disk
├── storage_path
├── mime_type
├── file_size
├── sort_order
├── created_at
└── updated_at
```

Files should be stored in object storage rather than loaded through the application server.

---

## 4.5 categories

Categories are managed by Administrators.

```text
categories
├── uuid                  PK
├── parent_uuid           FK NULL
├── name
├── slug
├── description
├── sort_order
├── is_visible
├── created_at
└── updated_at
```

Self-reference:

```text
parent_uuid → categories.uuid
```

This allows hierarchical categories.

Implementation rules:

* The tree has two levels: category → subcategory.
* A category with subcategories or attached entities cannot be deleted; hide it with `is_visible = false` instead.
* Hidden categories are not returned by the public API.

Examples:

```text
Jewelry
Ceramics
Textile
Wood
Candles
Painting
Leather
Knitting
```

---

## 4.6 categorizables

Polymorphic relationship between categories and supported domain entities.

```text
categorizables
├── category_uuid         FK
├── categorizable_uuid
├── categorizable_type
├── created_at
└── PRIMARY KEY (
      category_uuid,
      categorizable_uuid,
      categorizable_type
   )
```

`categorizable_type` identifies the owning entity.

Supported types:

```text
Product
Course
Workshop
MakerProfile
```

Important:

`categorizable_uuid` is the UUID of the referenced entity.

There is no `categorizable_id`.

---

## 4.7 tags

Stores product tags.

```text
tags
├── uuid                  PK
├── name
├── slug                  UNIQUE
├── created_at
└── updated_at
```

---

## 4.8 product_tags

Many-to-many relationship between products and tags.

```text
product_tags
├── product_uuid          FK
├── tag_uuid              FK
├── PRIMARY KEY (product_uuid, tag_uuid)
```

Foreign keys:

```text
product_uuid → products.uuid
tag_uuid → tags.uuid
```

---

## 4.9 favorites

Stores user favorites.

```text
favorites
├── uuid                  PK
├── user_uuid             FK
├── product_uuid          FK
├── created_at
```

Foreign keys:

```text
user_uuid → users.uuid
product_uuid → products.uuid
```

Unique constraint:

```text
UNIQUE (user_uuid, product_uuid)
```

The initial implementation supports product favorites.

The model can be extended later to support additional favorite targets.

---

# 5. Education Module

## 5.1 courses

Stores purchasable courses.

```text
courses
├── uuid                  PK
├── maker_profile_uuid    FK
├── name
├── slug
├── description
├── price_amount_minor
├── currency
├── access_type
├── access_days
├── certificate_enabled
├── status
├── published_at
├── created_at
├── updated_at
└── deleted_at
```

Access types:

```text
lifetime
limited
```

If:

```text
access_type = limited
```

then `access_days` is required.

Rules:

* A Course belongs to one Maker.
* A Course is purchasable.
* Course access is granted through enrollment after successful payment.
* Progress remains stored even after access expires.

---

## 5.2 course_modules

Stores course modules.

```text
course_modules
├── uuid                  PK
├── course_uuid           FK
├── title
├── description
├── sort_order
├── created_at
└── updated_at
```

Foreign key:

```text
course_uuid → courses.uuid
```

A Course contains multiple Modules.

---

## 5.3 lessons

Stores lessons inside modules.

```text
lessons
├── uuid                  PK
├── course_module_uuid    FK
├── title
├── description
├── lesson_type
├── sort_order
├── is_required
├── created_at
└── updated_at
```

Foreign key:

```text
course_module_uuid → course_modules.uuid
```

Lesson types may include:

```text
video
text
file
mixed
```

---

## 5.4 lesson_materials

Stores materials belonging to lessons.

```text
lesson_materials
├── uuid                  PK
├── lesson_uuid           FK
├── type
├── title
├── content
├── storage_disk
├── storage_path
├── external_url
├── sort_order
├── is_required
├── created_at
└── updated_at
```

Possible material types:

```text
text
video
pdf
file
link
```

A material may reference object storage or an external URL.

---

## 5.5 enrollments

Represents course access purchased by a Customer.

```text
enrollments
├── uuid                  PK
├── user_uuid             FK
├── course_uuid           FK
├── order_item_uuid       FK
├── starts_at
├── expires_at
├── status
├── created_at
└── updated_at
```

Foreign keys:

```text
user_uuid → users.uuid
course_uuid → courses.uuid
order_item_uuid → order_items.uuid
```

Possible statuses:

```text
active
expired
cancelled
completed
```

Rules:

* Enrollment is created only after successful payment.
* Enrollment provides course access.
* Progress remains stored independently of enrollment expiration.

---

## 5.6 lesson_progress

Stores Customer progress for lessons.

```text
lesson_progress
├── uuid                  PK
├── enrollment_uuid       FK
├── lesson_uuid           FK
├── completed_at
├── progress_percent
├── last_position
├── created_at
└── updated_at
```

Unique constraint:

```text
UNIQUE (enrollment_uuid, lesson_uuid)
```

Completion rules depend on lesson/material type.

Examples:

* text lesson → user reaches the end
* file material → file is opened
* video → video reaches completion

---

## 5.7 certificates

Stores course completion certificates.

```text
certificates
├── uuid                  PK
├── enrollment_uuid       FK UNIQUE
├── certificate_number    UNIQUE
├── issued_at
├── file_url
├── created_at
└── updated_at
```

Rules:

* One Enrollment may have at most one Certificate.
* Certificate can be issued only when course completion requirements are satisfied.
* Certificate generation is available only when enabled by the Maker.

---

# 6. Workshops Module

## 6.1 workshops

A Workshop is one purchasable/bookable product.

```text
workshops
├── uuid                  PK
├── maker_profile_uuid    FK
├── name
├── slug
├── description
├── price_amount_minor
├── currency
├── format
├── location_country_code
├── location_city
├── location_address
├── external_url
├── status
├── published_at
├── created_at
├── updated_at
└── deleted_at
```

Formats:

```text
online
offline
```

Rules:

* A Customer books the entire Workshop.
* A Workshop may contain multiple sessions.
* Online Workshops may use an external meeting link.
* Offline Workshops contain physical location information.
* A Workshop is purchased/booked as one unit.

---

## 6.2 workshop_sessions

Stores individual Workshop sessions.

```text
workshop_sessions
├── uuid                  PK
├── workshop_uuid         FK
├── starts_at
├── ends_at
├── capacity
├── status
├── created_at
└── updated_at
```

Foreign key:

```text
workshop_uuid → workshops.uuid
```

Rules:

* Every session belongs to one Workshop.
* Capacity is enforced at session level.
* A Workshop may have multiple sessions.
* Booking remains associated with the Workshop rather than a single Session.

---

## 6.3 workshop_bookings

Stores Customer bookings.

```text
workshop_bookings
├── uuid                  PK
├── user_uuid             FK
├── workshop_uuid         FK
├── order_item_uuid       FK
├── status
├── booked_at
├── cancelled_at
├── created_at
└── updated_at
```

Foreign keys:

```text
user_uuid → users.uuid
workshop_uuid → workshops.uuid
order_item_uuid → order_items.uuid
```

Possible statuses:

```text
confirmed
cancelled
completed
```

Capacity must be checked against the sessions belonging to the Workshop.

---

# 7. Orders Module

> **Implementation notes (2026-11-24).** What the code does where it differs from or adds to the tables below:
>
> * `orders.payment_method` (`online` | `cash_on_delivery`) and `orders.billing_email` (snapshot).
> * `order_groups.maker_name` — snapshot of the Maker's display name; `order_groups.cancel_reason` — set when a Maker cancels their part. `maker_profile_uuid` is indexed but has no FK, so Orders survive a deleted Maker profile.
> * `order_items` keep product/variant UUIDs without FKs plus snapshots (name, variant name, unit price) and `metadata` (`type`, `slug`, `image_url`).
> * `deliveries.phone` added. Delivery fee per group comes from the Maker profile: `maker_profiles.delivery_currency`, `delivery_domestic_minor`, `delivery_international_minor` (`null` = does not ship abroad), `cod_enabled`.
> * Guest carts: `carts.user_uuid` is nullable and the cart UUID is the guest token (`X-Cart-Token` header). After login the guest cart is merged into the user's cart.
> * Order statuses in code: `pending_payment`, `processing`, `partially_shipped`, `shipped`, `completed`, `cancelled` — derived from group statuses (`pending_payment`, `awaiting_shipment`, `shipped`, `delivered`, `completed`, `cancelled`). Payment is recorded in `orders.paid_at`; the `payments` table arrives with Stripe.

## 7.1 carts

Stores the current shopping cart.

```text
carts
├── uuid                  PK
├── user_uuid             FK NULL
├── currency
├── created_at
└── updated_at
```

`user_uuid` may be nullable to support guest checkout.

A Cart can contain products from multiple Makers.

A Cart supports only one currency.

---

## 7.2 cart_items

Stores products currently in a Cart.

```text
cart_items
├── uuid                  PK
├── cart_uuid             FK
├── product_uuid          FK
├── product_variant_uuid  FK NULL
├── quantity
├── unit_price_amount_minor
├── currency
├── created_at
└── updated_at
```

Foreign keys:

```text
cart_uuid → carts.uuid
product_uuid → products.uuid
product_variant_uuid → product_variants.uuid
```

Price snapshots in the cart are used for calculation consistency.

Final purchase prices are stored in OrderItem snapshots.

---

## 7.3 orders

Represents a completed checkout/order.

```text
orders
├── uuid                  PK
├── user_uuid             FK NULL
├── order_number          UNIQUE
├── currency
├── subtotal_amount_minor
├── delivery_amount_minor
├── total_amount_minor
├── status
├── billing_email
├── placed_at
├── paid_at
├── created_at
└── updated_at
```

Possible statuses:

```text
pending_payment
paid
processing
partially_shipped
shipped
completed
cancelled
```

Rules:

* One Order uses exactly one currency.
* All OrderGroups inside the Order use the same currency.
* `payment_method` is `online` or `cash_on_delivery`; payment on delivery follows domain-rules.md §27.
* Guest checkout is supported, so `user_uuid` may be nullable.
* `order_number` is a customer-facing identifier.

---

## 7.4 order_groups

Splits an Order by Maker.

```text
order_groups
├── uuid                  PK
├── order_uuid            FK
├── maker_profile_uuid    FK
├── subtotal_amount_minor
├── delivery_amount_minor
├── total_amount_minor
├── status
├── created_at
└── updated_at
```

Foreign keys:

```text
order_uuid → orders.uuid
maker_profile_uuid → maker_profiles.uuid
```

Unique constraint:

```text
UNIQUE (order_uuid, maker_profile_uuid)
```

Rules:

* One Order may contain multiple OrderGroups.
* Each Maker receives one OrderGroup per Order.
* Each OrderGroup has its own Delivery.
* Maker-specific order state may be managed independently.

---

## 7.5 order_items

Stores immutable purchase-time item snapshots.

```text
order_items
├── uuid                  PK
├── order_group_uuid      FK
├── product_uuid          FK NULL
├── product_variant_uuid  FK NULL
├── course_uuid           FK NULL
├── workshop_uuid         FK NULL
├── product_name
├── variant_name
├── unit_price_amount_minor
├── quantity
├── total_amount_minor
├── currency
├── metadata
├── created_at
└── updated_at
```

Possible purchased targets:

```text
physical product
digital product
course
workshop
```

The snapshot fields preserve historical purchase information.

For example, changing the Product name later must not change the historical OrderItem description.

Rules:

* OrderItem belongs to exactly one OrderGroup.
* Historical price must not change after checkout.
* Historical product information must remain available after the source Product changes or is deleted.

---

## 7.6 deliveries

Stores delivery information for physical items.

```text
deliveries
├── uuid                  PK
├── order_group_uuid      FK UNIQUE
├── recipient_name
├── country_code
├── city
├── address
├── postal_code
├── tracking_number
├── status
├── shipped_at
├── delivered_at
├── created_at
└── updated_at
```

Foreign key:

```text
order_group_uuid → order_groups.uuid
```

Rules:

* One OrderGroup has at most one Delivery.
* Digital-only OrderGroups do not require a Delivery.
* Delivery uses the same currency as the Order.

No external shipping-provider integration is required for MVP.

---

# 8. Payments Module

## 8.1 payments

Stores payment attempts.

```text
payments
├── uuid                  PK
├── order_uuid            FK
├── provider
├── provider_payment_id
├── amount_minor
├── currency
├── status
├── failure_code
├── failure_message
├── paid_at
├── created_at
└── updated_at
```

Foreign key:

```text
order_uuid → orders.uuid
```

Possible statuses:

```text
pending
processing
succeeded
failed
cancelled
refunded
```

Relationship:

```text
Order 1:N Payment
```

Multiple Payment records are allowed because a payment may fail and later be retried successfully.

Stripe is isolated behind the Payments module.

---

## 8.2 payment_webhook_events

Stores processed payment provider webhook events.

```text
payment_webhook_events
├── uuid                  PK
├── provider
├── external_event_id     UNIQUE
├── event_type
├── payload
├── processed_at
├── created_at
└── updated_at
```

Purpose:

* webhook idempotency
* auditability
* safe retry handling

The `external_event_id` must be unique per provider.

---

# 9. Reviews Module

## 9.1 reviews

Stores Customer reviews.

```text
reviews
├── uuid                  PK
├── user_uuid             FK
├── product_uuid          FK NULL
├── course_uuid           FK NULL
├── workshop_uuid         FK NULL
├── order_item_uuid       FK NULL
├── rating
├── title
├── body
├── status
├── moderated_at
├── created_at
└── updated_at
```

Foreign keys:

```text
user_uuid → users.uuid
product_uuid → products.uuid
course_uuid → courses.uuid
workshop_uuid → workshops.uuid
order_item_uuid → order_items.uuid
```

Exactly one target should be set:

```text
product_uuid
OR
course_uuid
OR
workshop_uuid
```

This can be enforced with a database `CHECK` constraint.

Possible statuses:

```text
pending
published
rejected
```

Rules:

* Review requires a valid purchase/enrollment/booking.
* One User may create only one review per target.
* Reviews require moderation before publication where moderation is enabled.

---

## 9.2 Maker rating

Maker rating is derived from Product reviews.

There is no independent source-of-truth `maker_ratings` table in the MVP.

The rating is calculated as:

```text
average of published Product reviews
belonging to Products owned by the Maker
```

Course and Workshop reviews do not contribute to the Maker rating.

A cached aggregate may be introduced later for performance, but the derived value remains the business source of truth.

---

# 10. Community Module

## 10.1 posts

Stores Maker community posts.

```text
posts
├── uuid                  PK
├── maker_profile_uuid    FK
├── body
├── status
├── published_at
├── created_at
├── updated_at
└── deleted_at
```

Foreign key:

```text
maker_profile_uuid → maker_profiles.uuid
```

Posts may contain text, images and links.

---

## 10.2 post_media

Stores media attached to posts.

```text
post_media
├── uuid                  PK
├── post_uuid             FK
├── media_type
├── file_url
├── sort_order
├── created_at
└── updated_at
```

Foreign key:

```text
post_uuid → posts.uuid
```

---

## 10.3 comments

Stores comments on community posts.

```text
comments
├── uuid                  PK
├── post_uuid             FK
├── user_uuid             FK
├── body
├── status
├── created_at
├── updated_at
└── deleted_at
```

Foreign keys:

```text
post_uuid → posts.uuid
user_uuid → users.uuid
```

---

## 10.4 likes

Stores post likes.

```text
likes
├── uuid                  PK
├── post_uuid             FK
├── user_uuid             FK
├── created_at
```

Foreign keys:

```text
post_uuid → posts.uuid
user_uuid → users.uuid
```

Unique constraint:

```text
UNIQUE (post_uuid, user_uuid)
```

---

## 10.5 follows

Stores Customer → Maker follows.

```text
follows
├── uuid                  PK
├── user_uuid             FK
├── maker_profile_uuid    FK
├── created_at
```

Foreign keys:

```text
user_uuid → users.uuid
maker_profile_uuid → maker_profiles.uuid
```

Unique constraint:

```text
UNIQUE (user_uuid, maker_profile_uuid)
```

A follow is allowed only when the Maker has enabled following.

---

# 11. Notifications Module

## 11.1 notifications

Stores application notifications.

```text
notifications
├── uuid                  PK
├── user_uuid             FK
├── type
├── title
├── body
├── data
├── read_at
├── created_at
└── updated_at
```

Foreign key:

```text
user_uuid → users.uuid
```

The MVP uses email notifications as the primary delivery channel.

The table can support additional channels later.

---

## 11.2 notification_preferences

Stores user notification preferences.

```text
notification_preferences
├── uuid                  PK
├── user_uuid             FK
├── notification_type
├── channel
├── enabled
├── created_at
└── updated_at
```

Foreign key:

```text
user_uuid → users.uuid
```

Unique constraint:

```text
UNIQUE (user_uuid, notification_type, channel)
```

Customer transactional order notifications cannot be disabled.

Other optional notification types may be configured by the Maker.

---

# 12. Relationships Summary

## Identity

```text
User
 ├── Roles
 └── MakerProfile
```

```text
users.uuid
    │
    ├── user_roles.user_uuid
    │
    └── maker_profiles.user_uuid
```

---

## Marketplace

```text
MakerProfile
 ├── Products
 │    ├── Variants
 │    ├── Images
 │    └── Files
 ├── Categories
 └── Favorites
```

```text
products.uuid
    │
    ├── product_variants.product_uuid
    ├── product_images.product_uuid
    ├── product_files.product_uuid
    └── product_tags.product_uuid
```

---

## Education

```text
Course
 └── CourseModule
      └── Lesson
           └── LessonMaterial
```

```text
courses.uuid
    │
    └── course_modules.course_uuid
          │
          └── lessons.course_module_uuid
                │
                └── lesson_materials.lesson_uuid
```

---

## Workshops

```text
Workshop
 └── WorkshopSession
```

```text
workshops.uuid
    │
    └── workshop_sessions.workshop_uuid
```

Customer booking belongs to:

```text
workshop_bookings.workshop_uuid
```

not to an individual session.

---

## Orders

```text
Order
 └── OrderGroup
      ├── OrderItems
      └── Delivery
```

```text
orders.uuid
    │
    └── order_groups.order_uuid
          │
          ├── order_items.order_group_uuid
          └── deliveries.order_group_uuid
```

---

## Payments

```text
Order
 └── Payments
```

```text
orders.uuid
    │
    └── payments.order_uuid
```

One Order may have multiple Payment attempts.

---

## Reviews

```text
User
 └── Review
       ├── Product
       ├── Course
       └── Workshop
```

The review target is explicitly represented through:

```text
product_uuid
course_uuid
workshop_uuid
```

---

# 13. Cross-Module Foreign Keys

Because Craft Woman is a modular monolith, cross-module database relationships are allowed where they provide meaningful referential integrity.

Examples:

```text
orders.user_uuid
orders → users
```

```text
order_groups.maker_profile_uuid
order_groups → maker_profiles
```

```text
enrollments.order_item_uuid
enrollments → order_items
```

```text
payments.order_uuid
payments → orders
```

```text
reviews.product_uuid
reviews → products
```

The existence of a foreign key does not mean that one module may directly manipulate another module's domain model.

Application-level module boundaries remain enforced.

---

# 14. Referential Integrity

Foreign keys should generally use:

```text
ON DELETE RESTRICT
```

for business-critical historical relationships.

For dependent entities where cascading deletion is safe, for example media records owned by a Product or Post, cascading may be appropriate.

Examples:

```text
product_images
    ON DELETE CASCADE
```

```text
post_media
    ON DELETE CASCADE
```

Historical order data should not be physically removed through cascading deletes.

Orders and OrderItems are treated as historical records.

---

# 15. Soft Deletes

Soft deletion should be used selectively.

Recommended entities:

```text
users
maker_profiles
products
courses
workshops
posts
```

Soft deletion should not automatically be applied to every table.

Transactional and historical records such as:

```text
orders
order_items
payments
```

should generally remain permanently stored.

---

# 16. Indexing Strategy

Indexes should be added according to actual query patterns.

Initial indexes should include:

## Identity

```text
users.email
users.status
maker_profiles.user_uuid
maker_profiles.slug
maker_profiles.status
```

## Marketplace

```text
products.maker_profile_uuid
products.status
products.type
products.slug
product_variants.product_uuid
product_variants.sku
categories.parent_uuid
categories.slug
categorizables.category_uuid
categorizables.categorizable_uuid
product_tags.tag_uuid
favorites.user_uuid
favorites.product_uuid
```

## Education

```text
courses.maker_profile_uuid
courses.status
courses.slug
course_modules.course_uuid
lessons.course_module_uuid
enrollments.user_uuid
enrollments.course_uuid
lesson_progress.enrollment_uuid
lesson_progress.lesson_uuid
```

## Workshops

```text
workshops.maker_profile_uuid
workshops.status
workshops.slug
workshop_sessions.workshop_uuid
workshop_sessions.starts_at
workshop_bookings.user_uuid
workshop_bookings.workshop_uuid
```

## Orders

```text
carts.user_uuid
cart_items.cart_uuid
orders.user_uuid
orders.order_number
orders.status
order_groups.order_uuid
order_groups.maker_profile_uuid
order_items.order_group_uuid
deliveries.order_group_uuid
```

## Payments

```text
payments.order_uuid
payments.provider_payment_id
payments.status
payment_webhook_events.external_event_id
```

## Reviews

```text
reviews.user_uuid
reviews.product_uuid
reviews.course_uuid
reviews.workshop_uuid
reviews.status
```

## Community

```text
posts.maker_profile_uuid
posts.status
comments.post_uuid
comments.user_uuid
likes.post_uuid
likes.user_uuid
follows.user_uuid
follows.maker_profile_uuid
```

## Notifications

```text
notifications.user_uuid
notifications.read_at
notification_preferences.user_uuid
```

---

# 17. Unique Constraints

Important uniqueness rules include:

```text
users.email
roles.name
maker_profiles.user_uuid
maker_profiles.slug
product_tags(product_uuid, tag_uuid)
favorites(user_uuid, product_uuid)
enrollments(user_uuid, course_uuid)
lesson_progress(enrollment_uuid, lesson_uuid)
certificates.enrollment_uuid
certificates.certificate_number
order_groups(order_uuid, maker_profile_uuid)
order_number
payment_webhook_events.external_event_id
reviews(user_uuid, target)
likes(post_uuid, user_uuid)
follows(user_uuid, maker_profile_uuid)
notification_preferences(user_uuid, notification_type, channel)
```

For reviews, uniqueness must account for the explicit target columns.

A practical implementation may use partial unique indexes for:

```text
product_uuid
course_uuid
workshop_uuid
```

because only one target is populated per review.

---

# 18. Check Constraints

Important database-level checks include:

## Product variants

```text
stock >= 0
```

## Money

```text
amount_minor >= 0
```

where negative values are not meaningful for the specific field.

## Rating

```text
rating BETWEEN 1 AND 5
```

## Workshop sessions

```text
capacity > 0
```

and:

```text
ends_at > starts_at
```

## Course access

If access is limited:

```text
access_days > 0
```

## Review target

Exactly one of:

```text
product_uuid
course_uuid
workshop_uuid
```

must be populated.

---

# 19. Currency Constraints

Supported currencies:

```text
EUR
USD
UAH
```

The database should use:

```text
char(3)
```

for currency codes.

Application/domain validation should ensure that only supported currencies are accepted.

An Order must contain only one currency.

All OrderGroups belonging to an Order must use the same currency.

Delivery amounts use the same currency as the Order.

---

# 20. Inventory Consistency

Inventory belongs exclusively to:

```text
product_variants.stock
```

There is no:

```text
products.stock
```

column.

Stock updates must be concurrency-safe.

The application must prevent:

```text
stock < 0
```

and overselling.

Inventory changes should use transactional database operations and appropriate row-level locking or atomic updates.

---

# 21. Historical Order Integrity

Order data must remain stable after checkout.

OrderItem stores snapshots of important purchase-time information:

```text
product_name
variant_name
unit_price_amount_minor
quantity
total_amount_minor
currency
metadata
```

This prevents later changes to:

* Product name
* Product variant name
* Product price
* Product configuration

from changing historical orders.

---

# 22. Digital Product Files

Digital product files should not be served by loading the entire file through the Laravel application server.

Recommended storage:

```text
Object Storage
    ↓
temporary/signed access
    ↓
Customer
```

Examples of future storage infrastructure include S3-compatible object storage.

The database stores metadata and storage references:

```text
storage_disk
storage_path
mime_type
file_size
```

---

# 23. Course Completion Persistence

Course completion is derived from lesson/material progress.

The database stores progress at lesson level:

```text
lesson_progress
```

and material definitions at:

```text
lesson_materials
```

This allows the application to determine whether all required learning materials have been completed.

Certificate issuance must depend on the resulting completion state.

---

# 24. Workshop Capacity

Capacity belongs to:

```text
workshop_sessions.capacity
```

rather than to Workshop itself.

A Workshop may contain multiple sessions with different capacities.

Booking a Workshop requires checking the capacity of its relevant sessions.

Capacity checks must be performed transactionally to prevent concurrent bookings from exceeding capacity.

---

# 25. Search and Filtering

The initial relational schema supports filtering by:

```text
category
price
Maker
rating
location
format
date
product type
availability
```

Search implementation is intentionally separated from the core transactional schema.

The MVP may use PostgreSQL search capabilities.

A dedicated search engine may be introduced later if scale requires it.

---

# 26. Auditability

The initial MVP does not require a full event-sourcing or audit-log system.

However, important external and financial operations should retain sufficient records for troubleshooting and reconciliation.

Examples:

```text
payment_webhook_events
payments
orders
```

Administration actions may receive a dedicated audit log in a later iteration.

---

# 27. Migration Order

Recommended migration order:

```text
1. users
2. roles
3. user_roles
4. maker_profiles

5. categories
6. tags
7. products
8. product_variants
9. product_images
10. product_files
11. categorizables
12. product_tags
13. favorites

14. courses
15. course_modules
16. lessons
17. lesson_materials
18. enrollments
19. lesson_progress
20. certificates

21. workshops
22. workshop_sessions
23. workshop_bookings

24. carts
25. cart_items
26. orders
27. order_groups
28. order_items
29. deliveries

30. payments
31. payment_webhook_events

32. reviews

33. posts
34. post_media
35. comments
36. likes
37. follows

38. notifications
39. notification_preferences
```

Where cross-module foreign keys create migration ordering constraints, migrations should be split or deferred appropriately.

---

# 28. UUID Generation

UUIDs should be generated by the application/database consistently.

The project should use a modern UUID strategy supported by PostgreSQL and Laravel.

The exact UUID generation mechanism is an implementation decision and should not change the database naming convention:

```text
primary key column → uuid
foreign key column → <entity>_uuid
```

---

# 29. Example Schema

A simplified example:

```sql
CREATE TABLE products (
    uuid UUID PRIMARY KEY,
    maker_profile_uuid UUID NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    price_amount_minor BIGINT NOT NULL,
    currency CHAR(3) NOT NULL,
    type VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL,

    CONSTRAINT products_maker_profile_fk
        FOREIGN KEY (maker_profile_uuid)
        REFERENCES maker_profiles (uuid)
);
```

Product variants:

```sql
CREATE TABLE product_variants (
    uuid UUID PRIMARY KEY,
    product_uuid UUID NOT NULL,
    sku VARCHAR(255),
    name VARCHAR(255),
    price_amount_minor BIGINT NOT NULL,
    currency CHAR(3) NOT NULL,
    stock BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL,

    CONSTRAINT product_variants_product_fk
        FOREIGN KEY (product_uuid)
        REFERENCES products (uuid),

    CONSTRAINT product_variants_stock_check
        CHECK (stock >= 0)
);
```

Order:

```sql
CREATE TABLE orders (
    uuid UUID PRIMARY KEY,
    user_uuid UUID,
    order_number BIGINT NOT NULL UNIQUE,
    currency CHAR(3) NOT NULL,
    subtotal_amount_minor BIGINT NOT NULL,
    delivery_amount_minor BIGINT NOT NULL,
    total_amount_minor BIGINT NOT NULL,
    status VARCHAR(32) NOT NULL,
    billing_email VARCHAR(255) NOT NULL,
    placed_at TIMESTAMPTZ,
    paid_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL,

    CONSTRAINT orders_user_fk
        FOREIGN KEY (user_uuid)
        REFERENCES users (uuid)
);
```

Payment:

```sql
CREATE TABLE payments (
    uuid UUID PRIMARY KEY,
    order_uuid UUID NOT NULL,
    provider VARCHAR(32) NOT NULL,
    provider_payment_id VARCHAR(255),
    amount_minor BIGINT NOT NULL,
    currency CHAR(3) NOT NULL,
    status VARCHAR(32) NOT NULL,
    paid_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL,

    CONSTRAINT payments_order_fk
        FOREIGN KEY (order_uuid)
        REFERENCES orders (uuid)
);
```

---

# 30. Naming Convention Summary

The database follows these conventions consistently.

### Primary keys

```text
uuid
```

Example:

```text
products.uuid
courses.uuid
orders.uuid
payments.uuid
```

### Foreign keys

```text
<entity>_uuid
```

Examples:

```text
user_uuid
maker_profile_uuid
product_uuid
product_variant_uuid
course_uuid
course_module_uuid
lesson_uuid
workshop_uuid
order_uuid
order_group_uuid
order_item_uuid
```

### Polymorphic references

```text
categorizable_uuid
categorizable_type
```

### Human-readable identifiers

Use separate fields where needed:

```text
order_number
certificate_number
slug
sku
```

They do not replace the UUID primary key.

---

# 31. Final Schema Rules

The following rules are considered architectural conventions for the Craft Woman database:

1. Every business entity uses `uuid` as its primary key.
2. There is no generic `id` primary-key column.
3. Foreign keys use the `<entity>_uuid` convention.
4. PostgreSQL `uuid` is used for technical entity identifiers.
5. Money is stored as integer minor units plus ISO currency code.
6. Timestamps use `timestamptz`.
7. Inventory belongs to ProductVariant.
8. Physical Products must have at least one ProductVariant.
9. Digital Products do not consume physical inventory.
10. Categories use a polymorphic `categorizables` table.
11. Orders are split into Maker-specific OrderGroups.
12. Each OrderGroup may have one Delivery.
13. OrderItems preserve historical purchase snapshots.
14. Payments have a one-to-many relationship with Orders.
15. Workshops are purchased/booked as a whole.
16. Workshop capacity belongs to WorkshopSession.
17. Course progress is persisted independently from access expiration.
18. Certificates require course completion.
19. Reviews use explicit target UUID columns.
20. Maker rating is derived from published Product reviews only.
21. Customer transactional order notifications cannot be disabled.
22. Cross-module foreign keys are allowed where they protect data integrity.
23. Cross-module Eloquent/domain manipulation is not allowed.
24. Historical transactional data must not be destroyed by cascading deletes.
25. Database constraints protect critical invariants, while business workflows remain in the Domain/Application layers.
26. The schema should remain intentionally simple until real requirements justify additional infrastructure.
