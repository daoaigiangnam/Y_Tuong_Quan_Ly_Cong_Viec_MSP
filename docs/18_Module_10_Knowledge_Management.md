# Module 10 — Knowledge Management

## Purpose

Provide a controlled MSP knowledge base for reusable troubleshooting procedures, SOPs, FAQs and resolution knowledge derived from Tickets, Problems and Changes.

## Lifecycle

`DRAFT -> IN_REVIEW -> PUBLISHED -> ARCHIVED`

An article cannot bypass review: `DRAFT -> PUBLISHED` is rejected.

Published content can return to review when a material update requires re-validation.

## Article model

- Article number and unique slug
- Title, summary and body
- Category
- Visibility: `INTERNAL`, `CUSTOMER`, `PUBLIC`
- Customer/service scope
- Owner and reviewer
- Version
- Publication and expiry dates
- Immutable event history

## Operational links

Knowledge can be linked to:

- Ticket
- Problem
- Change

This supports the MSP lifecycle:

`Incident/Ticket -> Problem -> Change -> Knowledge`

and reuse of proven resolutions for future tickets.

## Quality controls

- Required title/body/category
- Explicit review gate before publication
- Version increment on content changes
- Status transition validation
- Audit/history for creation, status changes, content changes and links
- MySQL integration and regression PHP lint in CI
