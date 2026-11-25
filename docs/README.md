# Craft Woman — documentation

Craft Woman is a marketplace for handmade goods, digital patterns, online courses and workshops.
It's a Laravel 12 modular monolith with a Vue 3 SPA in the same repository.

## Where to start

| You want to… | Read |
|---|---|
| Run the project locally | [06-development/getting-started.md](06-development/getting-started.md) |
| Know what's built and what's next | [07-status/roadmap.md](07-status/roadmap.md) |
| Understand a business rule | [02-architecture/domain-rules.md](02-architecture/domain-rules.md), then [05-decisions/decision-log.md](05-decisions/decision-log.md) |
| Add backend code to a module | [06-development/backend.md](06-development/backend.md) |
| Add a page or component | [06-development/frontend.md](06-development/frontend.md) |
| Call the API | [04-api/api-reference.md](04-api/api-reference.md) |

## All documents

| # | Document | What it is | State |
|---|---|---|---|
| 01 | [product-requirements.md](01-product/product-requirements.md) | Vision, users, features, MVP scope | Target: the full MVP. The implementation status is in the roadmap |
| 02 | [domain-rules.md](02-architecture/domain-rules.md) | Business invariants every module must keep | Authoritative; sections marked *Decision* record what was chosen |
| 02 | [module-architecture.md](02-architecture/module-architecture.md) | Modules, ownership, allowed dependencies | Authoritative; implementation notes show where code differs |
| 03 | [domain-model.md](03-domain/domain-model.md) | Aggregates, entities, value objects | Design reference written before the code |
| 03 | [erd.md](03-domain/erd.md) | Entity relationships | Design reference written before the code |
| 03 | [postgresql-schema.md](03-domain/postgresql-schema.md) | Tables and constraints | Design reference; *Implementation notes* list what the migrations actually do |
| 04 | [api-reference.md](04-api/api-reference.md) | Every `/api/v1` endpoint, conventions, errors | Matches the code |
| 05 | [decision-log.md](05-decisions/decision-log.md) | Dated product and technical decisions | Append-only |
| 06 | [getting-started.md](06-development/getting-started.md) | Setup on Windows + Herd, demo accounts, daily commands | Matches the code |
| 06 | [backend.md](06-development/backend.md) | Module layout, conventions, cross-module contracts, testing | Matches the code |
| 06 | [frontend.md](06-development/frontend.md) | SPA structure, design tokens, i18n, pages per area | Matches the code |
| 07 | [roadmap.md](07-status/roadmap.md) | MVP status, known gaps, next steps | Update with every feature PR |

When code and a design document disagree, the code and the *Implementation notes* win. Record the decision in the decision log, and add a note to the design document.

## Language

Documentation, code comments, commit messages and PR descriptions are in English. The interface is Ukrainian first, with English as the second language.
