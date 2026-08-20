USE msp_itsm;

ALTER TABLE contract_alerts
  ADD COLUMN attempted_at DATETIME NULL AFTER sent_at,
  ADD COLUMN email_log_id BIGINT UNSIGNED NULL AFTER error_message,
  ADD INDEX idx_contract_alert_due(contract_id,status,scheduled_date),
  ADD INDEX idx_contract_alert_attempted(attempted_at),
  ADD CONSTRAINT fk_contract_alert_email_log FOREIGN KEY(email_log_id) REFERENCES email_logs(id);
