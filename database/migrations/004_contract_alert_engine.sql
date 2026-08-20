USE msp_itsm;

-- Contract alert engine migration.
-- This migration is intentionally idempotent because database/schema.sql may
-- already contain the current contract_alerts shape on fresh installations.

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'contract_alerts'
        AND COLUMN_NAME = 'attempted_at'
    ),
    'SELECT 1',
    'ALTER TABLE contract_alerts ADD COLUMN attempted_at DATETIME NULL AFTER sent_at'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'contract_alerts'
        AND COLUMN_NAME = 'email_log_id'
    ),
    'SELECT 1',
    'ALTER TABLE contract_alerts ADD COLUMN email_log_id BIGINT UNSIGNED NULL AFTER error_message'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'contract_alerts'
        AND INDEX_NAME = 'idx_contract_alert_due'
    ),
    'SELECT 1',
    'ALTER TABLE contract_alerts ADD INDEX idx_contract_alert_due(contract_id,status,scheduled_date)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'contract_alerts'
        AND INDEX_NAME = 'idx_contract_alert_attempted'
    ),
    'SELECT 1',
    'ALTER TABLE contract_alerts ADD INDEX idx_contract_alert_attempted(attempted_at)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'contract_alerts'
        AND COLUMN_NAME = 'email_log_id'
        AND REFERENCED_TABLE_NAME = 'email_logs'
        AND REFERENCED_COLUMN_NAME = 'id'
    ),
    'SELECT 1',
    'ALTER TABLE contract_alerts ADD CONSTRAINT fk_contract_alert_email_log FOREIGN KEY(email_log_id) REFERENCES email_logs(id)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
