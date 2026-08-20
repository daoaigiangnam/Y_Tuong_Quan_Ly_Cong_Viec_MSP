USE msp_itsm;

ALTER TABLE tickets
  MODIFY status ENUM('NEW','TRIAGED','ASSIGNED','IN_PROGRESS','PENDING_CUSTOMER','PENDING_VENDOR','PENDING_INTERNAL','RESOLVED','REOPENED','CLOSED') NOT NULL DEFAULT 'NEW',
  ADD COLUMN request_type VARCHAR(50) NULL AFTER service_id,
  ADD COLUMN requester_user_id INT UNSIGNED NULL AFTER created_by_user_id,
  ADD COLUMN first_response_at DATETIME NULL AFTER due_at,
  ADD COLUMN sla_policy_snapshot JSON NULL AFTER first_response_at,
  ADD COLUMN sla_target_response_at DATETIME NULL AFTER sla_policy_snapshot,
  ADD COLUMN sla_target_resolve_at DATETIME NULL AFTER sla_target_response_at,
  ADD INDEX idx_ticket_requester(requester_user_id),
  ADD INDEX idx_ticket_sla_resolve(sla_target_resolve_at),
  ADD CONSTRAINT fk_ticket_requester FOREIGN KEY(requester_user_id) REFERENCES users(id);

ALTER TABLE ticket_comments
  ADD COLUMN visibility ENUM('CUSTOMER_VISIBLE','INTERNAL_ONLY') NOT NULL DEFAULT 'CUSTOMER_VISIBLE' AFTER body,
  ADD INDEX idx_ticket_comments_visibility(ticket_id,visibility,created_at);

ALTER TABLE ticket_history
  ADD INDEX idx_history_event(ticket_id,event,created_at);
