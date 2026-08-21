# 06 — Logical Data Model

## 1. Party & Identity

```text
organizations
contacts
interaction_identities
customer_relationships
contracts
service_entitlements
```

### interaction_identities

```text
id
contact_id nullable
organization_id nullable
channel
external_id
verified
verification_method
confidence
first_seen_at
last_seen_at
status
```

## 2. Communication

```text
channels
conversations
communication_events
attachments
raw_events
call_recordings
transcripts
transcript_segments
```

### communication_events

```text
id
event_id
conversation_id
customer_identity_id
channel
direction
sender_type
sender_id
external_message_id
content_type
content
external_timestamp
received_at
raw_event_id
created_at
```

## 3. Case / Requirement

```text
cases
requirements
requirement_versions
requirement_fields
evidence_links
customer_confirmations
approved_scopes
```

### evidence_links

Maps a requirement field/value to evidence:

```text
id
requirement_id
field_name
communication_event_id
attachment_id
recording_id
transcript_segment_id
source_type
created_at
```

## 4. ITSM / MSP

```text
incidents
service_requests
problems
changes
work_orders
work_order_tasks
sla_records
```

## 5. Governance

```text
audit_logs
correlation_records
validation_records
ai_analysis_records
manual_overrides
```

## 6. Core relationships

```text
Organization
  └── Contact
       └── Interaction Identity
            └── Conversation
                 └── Communication Event
                      └── Case
                           └── Requirement
                                ├── Requirement Version
                                ├── Evidence Links
                                ├── Customer Confirmation
                                └── Approved Scope
                                      └── Work Order
```

## 7. Separation of concerns

- Communication tables preserve evidence and conversation.
- Requirement tables represent business intent.
- ITSM tables represent operational execution.
- Governance tables preserve provenance, decisions and auditability.
