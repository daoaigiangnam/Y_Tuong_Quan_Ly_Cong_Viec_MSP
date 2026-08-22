# 05 — Data Governance, Requirement & Scope Control

## 1. Data classification

```text
RAW EVIDENCE
   ↓
EXTRACTED
   ↓
VALIDATED
   ↓
CUSTOMER CONFIRMED
   ↓
APPROVED
```

### RAW

Original Zalo message, Email, Chat, Audio, Attachment, Portal submission.

### EXTRACTED

AI- or rule-derived values such as intent, category, time, quantity, service, impact.

### VALIDATED

Reviewed by authorized Service Desk / Sales / Service Manager / Technical Owner according to field.

### CUSTOMER CONFIRMED

Customer explicitly confirms the requirement.

### APPROVED

Value is accepted as the official requirement/scope source for execution.

## 2. Provenance

Every important field should carry:

```text
value
source
source_event_id
extracted_by
confidence
verification_status
version
created_at
```

Example:

```json
{
  "field": "requested_time",
  "value": "09:00",
  "source": "CALL-00551",
  "original_text": "Mai khoảng 8 hoặc 9 giờ",
  "confidence": 0.71,
  "verification_status": "UNCONFIRMED"
}
```

## 3. Requirement versioning

Never overwrite requirement history.

```text
REQ V1 → V2 → V3 → Current
```

Each change records:

- Who changed.
- When.
- Why.
- Source evidence.
- Previous version.

## 4. Customer confirmation

Important business fields should require explicit confirmation where appropriate:

- Scope
- Quantity
- Deadline
- Cost-impacting work
- Production change
- Access/security permissions
- Service commitment

## 5. Scope Lock

After customer confirmation:

```text
APPROVED SCOPE
     ↓
SCOPE LOCKED
     ↓
WORK ORDER
```

IT cannot silently expand Scope.

If technical discovery finds additional work:

```text
Technical Recommendation
     ↓
Additional Work / Change Request
     ↓
Customer / Internal Approval
     ↓
New Approved Scope
```

## 6. No Approved Scope → No Execution

Exception: Major Incident / Emergency Change with explicit emergency governance.

## 7. Data lineage

Every Work Order must be able to navigate back:

`Work Order → Approved Scope → Requirement Version → Conversation → Communication Event → Original Evidence`

## 8. Audit

Track at minimum:

- create/update/delete attempts on critical records
- state transitions
- approval/rejection
- scope lock/unlock
- correlation decision
- AI extraction version/model
- manual override
- customer confirmation
