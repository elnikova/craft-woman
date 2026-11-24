# Craft Woman — Domain Model

## 1. Purpose

This document defines the conceptual domain model of the Crafts Woman platform.

It describes:

* domain entities;
* aggregate roots and aggregate boundaries;
* value objects;
* enums;
* entity ownership;
* relationships between entities;
* cross-module references;
* domain events;
* important domain invariants.

This document is intentionally independent of database implementation details.

The database schema and ERD are derived from this model and must not introduce business concepts that are not defined here without updating the domain model first.

---

# 2. Domain Modeling Principles

The platform follows these principles:

1. Each business entity has one owning module.
2. Each aggregate has one aggregate root.
3. Aggregates protect their own invariants.
4. Cross-module business interactions use application interfaces, IDs, or domain/integration events rather than direct manipulation of another module's entities.
5. Domain entities must not depend on Laravel or other infrastructure frameworks.
6. Money is represented using an amount and currency.
7. Monetary amounts must never use floating-point numbers.
8. Historical commercial data must remain stable after a purchase.
9. Authorization rules are enforced at the application boundary and must also be respected by domain operations.
10. Domain rules must be covered by automated tests.

---

# 3. Bounded Contexts / Modules

The platform is divided into the following modules:

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

The modules form a modular monolith.

The module boundaries are logical and are enforced through code structure and application contracts rather than separate databases.

---

# 4. Identity Module

## 4.1 User

Represents an account on the platform.

### Responsibilities

* authentication identity;
* account status;
* basic user information;
* ownership of customer activity;
* association with a Maker profile when applicable.

### Relationships

```text
User
 ├── MakerProfile (optional)
 ├── Favorites
 ├── Cart
 ├── Orders
 ├── Course Enrollments
 ├── Workshop Bookings
 ├── Reviews
 ├── Community Posts
 ├── Comments
 ├── Likes
 └── Follows
```

A User may act as a Customer and, after approval, as a Maker.

Roles are authorization concepts and are not separate domain entities.

---

## 4.2 MakerProfile

Represents the public profile of a Maker.

### Responsibilities

* public Maker information;
* Maker story and description;
* location;
* categories;
* public profile settings;
* follow availability;
* Maker approval state;
* association with products, courses, workshops and community content.

### Important Rules

* A Maker must be approved before obtaining full Maker capabilities.
* A Maker may manage only their own entities.
* A Maker cannot directly modify another Maker's content.
* A Maker may enable or disable following.

---

# 5. Marketplace Module

## 5.1 Product

Represents a product available on the marketplace.

Product is the aggregate root for product-specific configuration and lifecycle.

### Product Types

```text
Physical
Digital
```

The product type determines which additional information is applicable.

### Responsibilities

* product identity;
* name and description;
* price;
* currency;
* category;
* tags;
* images;
* product type;
* publication lifecycle;
* Maker ownership.

### Product Lifecycle

```text
Draft
PendingReview
Published
Rejected
Archived
```

Only a published product can be purchased.

---

## 5.2 ProductVariant

Represents a purchasable variation of a physical product.

Examples:

```text
Color: Red
Size: M
```

A product may have multiple variants.

### Responsibilities

* variant attributes;
* variant-specific price when applicable;
* inventory;
* purchasable state.

For physical products, inventory is maintained at the appropriate variant level.

---

## 5.3 ProductFile

Represents a downloadable or accessible file associated with a digital product.

### Responsibilities

* file metadata;
* storage reference;
* file type;
* association with the product.

The domain does not depend on a specific storage provider.

Actual file storage is an infrastructure concern.

---

## 5.4 Category

Represents an administrator-managed marketplace category.

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

Categories can be associated with relevant marketplace entities.

---

## 5.5 Tag

Represents a reusable descriptive label.

Tags support discovery and filtering.

---

## 5.6 Favorite

Represents a user's saved marketplace item.

MVP favorites primarily target products.

The model should remain extensible for future support of other favoriteable entities.

---

# 6. Education Module

## 6.1 Course

Represents a purchasable educational product created by a Maker.

Course is the aggregate root for course structure and course configuration.

### Responsibilities

* course identity;
* title and description;
* Maker ownership;
* price;
* currency;
* publication state;
* access duration;
* certificate availability;
* course structure.

---

## 6.2 CourseModule

Represents a logical section of a course.

A Course contains one or more CourseModules.

```text
Course
 ├── CourseModule
 ├── CourseModule
 └── CourseModule
```

---

## 6.3 Lesson

Represents an individual learning unit.

A lesson may contain one or more material types:

```text
Text
Video
File
```

A combination is allowed.

---

## 6.4 Enrollment

Represents a Customer's entitlement to access a purchased course.

### Responsibilities

* course access;
* access start date;
* optional access expiration date;
* enrollment status;
* connection to the underlying purchase.

### Access Modes

```text
Lifetime
Limited
```

Course progress remains associated with the enrollment even if access expires.

---

## 6.5 LessonProgress

Represents a user's progress through a lesson.

Progress may be tracked per material type.

### Completion Rules

Text:

```text
User reaches the end of the content.
```

File:

```text
File is opened.
```

Video:

```text
Video is watched to completion.
```

A lesson is considered completed when all required materials have been completed.

---

## 6.6 Certificate

Represents a course completion certificate.

A certificate may be issued only when:

1. the course has certificates enabled;
2. the Customer has completed all required course materials.

---

# 7. Workshops Module

## 7.1 Workshop

Represents a time-based educational or creative event created by a Maker.

A Workshop may contain multiple sessions.

```text
Workshop
 ├── Session
 ├── Session
 └── Session
```

---

## 7.2 WorkshopSession

Represents one scheduled session.

### Session Types

```text
Online
Offline
```

### Online Session

Contains an external meeting URL.

The platform does not host video meetings in the MVP.

### Offline Session

Contains physical location information.

---

## 7.3 WorkshopBooking

Represents a Customer's booking for a Workshop.

### Responsibilities

* participant identity;
* workshop/session association;
* booking status;
* booking time;
* connection to payment.

### Capacity Rule

The number of active bookings must never exceed the configured capacity.

Capacity enforcement must be concurrency-safe.

---

# 8. Orders Module

## 8.1 Cart

Represents the Customer's current shopping cart.

A Cart may contain products from multiple Makers.

### Currency Rule

A Cart contains products in **one currency only**.

Products with another currency cannot be added to the same Cart.

---

## 8.2 CartItem

Represents a product selection inside a Cart.

Contains:

* product reference;
* optional product variant reference;
* quantity;
* current purchase price information.

Cart contents are mutable before checkout.

---

# 9. Order

Order is the aggregate root representing a completed checkout transaction.

An Order may contain products from multiple Makers.

```text
Order
 ├── OrderGroup
 │    ├── OrderItem
 │    └── Delivery
 │
 ├── OrderGroup
 │    ├── OrderItem
 │    └── Delivery
 │
 └── ...
```

---

## 9.1 OrderGroup

Represents one Maker-specific portion of an Order.

Each OrderGroup belongs to exactly one Maker.

### Responsibilities

* Maker-specific order items;
* Maker-specific fulfillment state;
* Maker-specific delivery;
* Maker-specific totals.

An Order may contain multiple OrderGroups.

---

## 9.2 OrderItem

Represents a purchased product inside an OrderGroup.

OrderItem must contain a historical snapshot of the commercial product information at the moment of purchase.

The snapshot protects historical order integrity if the original Product later changes.

The snapshot may include:

* product name;
* variant information;
* price;
* currency;
* quantity;
* relevant product attributes.

OrderItem must not depend on the current mutable state of the Product to display historical order information.

---

## 9.3 Delivery

Represents fulfillment information for an OrderGroup.

Each OrderGroup has its own Delivery.

This is necessary because different Makers may fulfill orders from different locations.

### Delivery Currency

Delivery charges use the currency selected by the Customer for the Order.

### Future Extensions

The domain should allow future integration with:

* Nova Poshta;
* DHL;
* other delivery providers.

Provider integrations are outside the MVP.

---

# 10. Payment Module

## 10.1 Payment

Represents a financial payment associated with an Order.

### Payment Method

MVP supports:

```text
Online payment
```

Cash on Delivery is not part of the domain.

---

## 10.2 Payment Provider

The domain should not depend on Stripe directly.

Conceptually:

```text
Order
   ↓
Payment Application Interface
   ↓
Payment Provider
   ↓
Stripe
```

Stripe-specific implementation belongs to the Payments Infrastructure layer.

---

## 10.3 Payment Webhook

External payment provider notifications must be processed idempotently.

Repeated delivery of the same provider event must not cause duplicate business effects.

---

# 11. Reviews Module

## 11.1 Review

Represents a Customer's evaluation of an eligible purchased or attended item.

A review contains:

* author;
* target;
* rating;
* text;
* creation date;
* moderation state.

### Review Eligibility

A Customer may create a review only when they have a valid relationship with the target through:

* purchase;
* course enrollment;
* workshop booking.

### Review Multiplicity

A Customer may submit **one review per target**.

For example:

```text
Customer A → Product X → one review
Customer A → Course Y → one review
Customer A → Workshop Z → one review
```

---

## 11.2 MakerRating

Maker rating is derived from product reviews.

The rating is the arithmetic mean of all eligible product ratings belonging to that Maker.

It is not manually entered by the Maker.

The calculation must be based on the defined set of product reviews and must not include unrelated ratings such as course or workshop ratings unless the business rules are explicitly changed later.

---

# 12. Community Module

## 12.1 Post

Represents community content created by a Maker.

A post may contain:

* text;
* images;
* links;
* references to Maker products;
* references to the Maker profile.

---

## 12.2 Comment

Represents a user's response to a community post.

---

## 12.3 Like

Represents a user's positive interaction with a community post.

A user should not have multiple active likes on the same post.

---

## 12.4 Follow

Represents a Customer following a Maker.

Following is available only when the Maker has enabled the feature.

The behavior of existing followers when a Maker disables following is intentionally deferred.

---

# 13. Notifications Module

## 13.1 Notification

Represents an email notification generated by a domain or application event.

Examples:

* order created;
* payment successful;
* payment failed;
* order shipped;
* order delivered;
* course purchased;
* workshop booking confirmed;
* workshop reminder;
* new review;
* product approved;
* product rejected;
* new follower;
* new community activity.

---

## 13.2 NotificationPreference

Represents a user's configurable notification preferences.

Most notification categories are optional.

### Mandatory Notifications

Order-related notifications required for the Customer's transaction are mandatory and cannot be disabled.

Optional notifications may be configured by the user where applicable.

---

# 14. Administration Module

Administration does not own the core business entities.

It provides administrative capabilities over entities owned by other modules.

Administrators may:

* approve or reject Makers;
* approve or reject products;
* manage categories and tags;
* moderate reviews;
* moderate community content;
* manage users;
* inspect orders;
* inspect courses;
* inspect workshops.

Administrative actions must use the owning module's application interfaces or authorized operations.

Administration must not directly manipulate another module's internal domain entities.

---

# 15. Value Objects

The following concepts should be modeled as Value Objects where appropriate.

## 15.1 Money

Represents a monetary amount and currency.

```text
Money
 ├── amount
 └── currency
```

Rules:

* amount uses a precise numeric representation;
* floating-point numbers are prohibited;
* arithmetic is allowed only between compatible currencies.

---

## 15.2 Currency

Represents a supported currency.

MVP currencies:

```text
EUR
USD
UAH
```

---

## 15.3 Address

Represents a physical address where required.

It may contain:

* country;
* city;
* postal code;
* address lines.

The exact structure is an implementation decision to be refined during the database design stage.

---

## 15.4 Location

Represents a Maker or Workshop geographic location.

Location may contain:

* country;
* city;
* optional address information.

---

## 15.5 DateRange

Represents a period with a start and optional end.

Used where appropriate for:

* course access;
* workshop scheduling;
* other time-bound domain concepts.

---

## 15.6 Percentage / Rating

Ratings must use a defined bounded representation.

Product review ratings are expected to use a fixed scale, such as 1–5.

The exact storage representation belongs to the implementation layer.

---

# 16. Important Enums

The following concepts are candidates for explicit domain enums.

## User / Identity

```text
UserStatus
MakerApprovalStatus
```

## Marketplace

```text
ProductType
ProductStatus
```

## Education

```text
CourseStatus
CourseAccessType
LessonMaterialType
EnrollmentStatus
```

## Workshops

```text
WorkshopStatus
WorkshopSessionType
BookingStatus
```

## Orders

```text
OrderStatus
OrderGroupStatus
OrderItemType
DeliveryStatus
```

## Payments

```text
PaymentStatus
PaymentMethod
```

## Reviews

```text
ReviewStatus
```

## Community

```text
PostStatus
```

## Notifications

```text
NotificationType
NotificationStatus
```

The exact enum values and state transitions must be finalized before implementation.

---

# 17. Aggregate Boundaries

The following aggregate boundaries are proposed.

## Identity

```text
User
```

```text
MakerProfile
```

MakerProfile may reference User but should not become responsible for the User aggregate.

---

## Marketplace

```text
Product
 ├── ProductVariant
 └── ProductFile
```

Category, Tag and Favorite are separate domain concepts.

---

## Education

```text
Course
 ├── CourseModule
 │    └── Lesson
```

```text
Enrollment
 └── LessonProgress
```

```text
Certificate
```

Course structure and Customer learning progress are intentionally separate concerns.

---

## Workshops

```text
Workshop
 └── WorkshopSession
```

```text
WorkshopBooking
```

---

## Orders

```text
Cart
 └── CartItem
```

```text
Order
 └── OrderGroup
      ├── OrderItem
      └── Delivery
```

Order owns the commercial snapshot.

---

## Payments

```text
Payment
```

Payment provider interaction is handled through application/infrastructure services.

---

## Reviews

```text
Review
```

MakerRating is derived information rather than independently authored business data.

---

## Community

```text
Post
 ├── Comment
 └── Like
```

Follow is a separate relationship.

---

# 18. Cross-Module References

Cross-module relationships should use stable identifiers and application contracts rather than direct manipulation of foreign module entities.

Examples:

```text
Order → User ID
OrderGroup → Maker ID
OrderItem → Product ID
Enrollment → User ID
Enrollment → Course ID
WorkshopBooking → User ID
WorkshopBooking → Workshop ID
Review → User ID
Review → Target ID
Post → Maker ID
Follow → User ID
Follow → Maker ID
```

A module may know the identifier of an entity owned by another module without owning or modifying that entity.

---

# 19. Domain Events

Important domain events include:

## Identity

```text
MakerApproved
MakerRejected
```

## Marketplace

```text
ProductSubmittedForReview
ProductApproved
ProductRejected
ProductPublished
ProductArchived
```

## Orders

```text
OrderCreated
OrderPaid
OrderCancelled
OrderShipped
OrderDelivered
```

## Payments

```text
PaymentSucceeded
PaymentFailed
```

## Education

```text
CoursePurchased
CourseEnrollmentCreated
LessonCompleted
CourseCompleted
CertificateIssued
```

## Workshops

```text
WorkshopBookingCreated
WorkshopBookingConfirmed
WorkshopCancelled
```

## Reviews

```text
ReviewCreated
ReviewModerated
```

## Community

```text
MakerFollowed
PostCreated
CommentCreated
```

## Notifications

Notifications should generally react to relevant application/domain events rather than being tightly embedded into unrelated domain entities.

---

# 20. Historical Integrity

Commercial information must remain historically accurate.

After an Order is created:

* Product name changes must not alter the OrderItem snapshot.
* Product price changes must not alter the OrderItem price.
* Product variant changes must not alter historical order information.
* Product deletion or archival must not invalidate historical Orders.

Orders represent what the Customer purchased at the time of checkout, not the current state of the Marketplace.

---

# 21. Concurrency-Sensitive Domains

The following operations require concurrency protection:

### Product inventory

Two customers must not be able to purchase the same remaining inventory simultaneously.

### Workshop capacity

Two customers must not be able to book the final available seat simultaneously.

### Payment processing

Repeated payment provider callbacks must not create duplicate payment effects.

These rules will later be implemented using database transactions, locking, idempotency mechanisms, and appropriate application services.

---

# 22. Currency Rules

Supported currencies:

```text
EUR
USD
UAH
```

Rules:

1. A Cart contains one currency.
2. An Order contains one currency.
3. Product prices are stored with their currency.
4. Delivery charges use the Customer's selected Order currency.
5. Money must never be represented as a floating-point number.
6. Currency conversion, if required, must be explicit and auditable.

Multi-currency conversion strategy is an implementation/business concern to be refined before checkout implementation.

---

# 23. Course Completion Rules

A course is completed when all required learning materials have been completed.

Completion is determined by material type:

### Text

The user reaches the end of the text content.

### File

The user opens the file.

### Video

The user watches the video to completion.

The exact technical threshold for "video watched to completion" will be defined during implementation.

Course completion is required before issuing a certificate when certificates are enabled.

---

# 24. Review and Rating Rules

A Customer may submit one review per target.

The Customer must have a valid qualifying relationship with the target.

Maker rating is derived from product reviews.

The rating calculation must be deterministic and reproducible from the underlying reviews.

Maker ratings must not be manually editable.

---

# 25. Authorization Rules

Authorization is enforced outside the domain model where appropriate, but domain operations must preserve ownership invariants.

Examples:

```text
Maker A cannot edit Maker B's Product.
Customer A cannot access Customer B's private course progress.
Customer A cannot modify Customer B's Order.
Administrator can access entities across Makers according to administrative permissions.
```

Roles are not domain modules.

---

# 26. Aggregate Consistency Rules

An aggregate must protect its own invariants.

Examples:

```text
Order
→ cannot contain invalid OrderGroups

OrderGroup
→ must belong to one Maker

OrderItem
→ must have a valid quantity and purchase snapshot

Workshop
→ cannot exceed configured capacity

Product
→ cannot become purchasable unless published

Enrollment
→ cannot provide access outside its defined entitlement

Review
→ cannot be created without valid review eligibility
```

Cross-aggregate workflows should be handled by application services or domain events rather than by creating large aggregates.

---

# 27. Entity Ownership Map

| Entity                 | Owning Module |
| ---------------------- | ------------- |
| User                   | Identity      |
| MakerProfile           | Identity      |
| Product                | Marketplace   |
| ProductVariant         | Marketplace   |
| ProductFile            | Marketplace   |
| Category               | Marketplace   |
| Tag                    | Marketplace   |
| Favorite               | Marketplace   |
| Course                 | Education     |
| CourseModule           | Education     |
| Lesson                 | Education     |
| Enrollment             | Education     |
| LessonProgress         | Education     |
| Certificate            | Education     |
| Workshop               | Workshops     |
| WorkshopSession        | Workshops     |
| WorkshopBooking        | Workshops     |
| Cart                   | Orders        |
| CartItem               | Orders        |
| Order                  | Orders        |
| OrderGroup             | Orders        |
| OrderItem              | Orders        |
| Delivery               | Orders        |
| Payment                | Payments      |
| Review                 | Reviews       |
| MakerRating            | Reviews       |
| Post                   | Community     |
| Comment                | Community     |
| Like                   | Community     |
| Follow                 | Community     |
| Notification           | Notifications |
| NotificationPreference | Notifications |

Administration owns none of the core business entities.

---

# 28. Deferred Decisions

The following decisions remain intentionally open:

1. Exact behavior of existing followers after a Maker disables following.
2. Exact database representation of addresses and locations.
3. Exact video completion threshold.
4. Exact enum values and state transitions.
5. Exact currency conversion mechanism.
6. Exact delivery provider integration model.
7. Exact moderation workflow beyond Admin comments.
8. Advanced rating/statistics behavior.
9. Future refund model.
10. Future seller payout model.

These decisions must not block the initial domain model.

---

# 29. Domain Model to Database Mapping

The database schema must be derived from this domain model.

The next design artifact should define:

* tables;
* primary keys;
* foreign keys;
* unique constraints;
* indexes;
* nullable fields;
* check constraints;
* pivot tables;
* JSON fields where justified;
* monetary column types;
* enum strategy;
* soft deletion strategy;
* audit fields.

The database design must preserve the aggregate boundaries and ownership defined in this document.

---

# 30. Next Architecture Artifact

After approval of this Domain Model, the next artifact is:

```text
docs/03-domain/erd.md
```

The ERD will translate the conceptual domain model into concrete entity relationships.

After the ERD is approved, the project can proceed to:

```text
PostgreSQL Schema
        ↓
Laravel Module Skeleton
        ↓
Migrations
        ↓
Domain Classes
        ↓
Application Layer
        ↓
API Contracts
        ↓
Tests
        ↓
Vue Frontend
```

Database migrations must not be created before the ERD and PostgreSQL schema have been reviewed.
