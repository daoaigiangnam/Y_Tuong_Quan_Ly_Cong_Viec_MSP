# 02 — Omnichannel & Communication Hub

## Mục tiêu

Communication Hub không phải là một Ticket Generator. Nó là lớp trung tâm để lưu **Communication Event**, Conversation, Attachment và Raw Evidence; đồng thời hỗ trợ Identity Resolution và Case Correlation.

## Canonical Communication Event

```json
{
  "event_id": "EVT-000001",
  "channel": "ZALO",
  "direction": "INBOUND",
  "sender_type": "CUSTOMER",
  "customer_identity_id": "IID-00025",
  "conversation_id": "CON-000125",
  "external_message_id": "MSG-ABC",
  "content_type": "TEXT",
  "content": "Website bên anh bị chậm",
  "event_time": "2026-08-21T10:15:20+07:00",
  "raw_payload_ref": "RAW-000001"
}
```

## Channel Adapter

Mỗi kênh có Adapter/Connector riêng:

```text
Zalo API/Webhook   ─┐
Viber API/Webhook   ├─> Canonical Event -> Communication Hub
Meta API/Webhook    │
Chatbot API         │
Email API           │
PBX/Recording       ┘
```

## Zalo / Viber / Social

Không scrape hoặc copy/paste từ tài khoản cá nhân. Dùng API/Webhook chính thức của nền tảng tương ứng khi được hỗ trợ.

## Chatbot

Chatbot của Công ty phải phát event vào Hub ngay khi có inbound/outbound message. Chatbot được phép dùng AI để thu thập thông tin nhưng không tự phê duyệt Scope.

## Email

Giữ đầy đủ:

- Message-ID
- Thread-ID / Conversation ID nếu có
- In-Reply-To / References
- From/To/CC
- Subject
- Body
- Attachment
- Timestamp

Mục tiêu là có thể nối `Email → Conversation → Case`.

## Call

Call là kênh đặc biệt:

```text
PBX
├── CDR / API / AMI
│     └── Call metadata
└── Call Recording
      └── Audio
             ↓
         STT / Diarization
             ↓
         Transcript
             ↓
         Communication Event
```

Nếu có thể, ưu tiên **dual-channel recording**:

- Channel 1 = Customer
- Channel 2 = Company/Agent

Điều này giúp tránh nhầm speaker và làm nền cho Call QA.

## Raw Evidence

Raw payload, Email gốc, Audio, Attachment không bị overwrite.

Nên lưu metadata trong DB và file lớn trong Object Storage:

```text
Database
  └── event_id / metadata / checksum / reference

Object Storage
  ├── audio/
  ├── attachments/
  └── raw-payload/
```

## Conversation

Một Case có thể có nhiều Conversation:

```text
CASE-00125
├── CON-001 (Portal)
├── CON-002 (Zalo)
├── CON-003 (Call)
└── CON-004 (Email)
```

Mỗi Conversation gồm nhiều Communication Event.
