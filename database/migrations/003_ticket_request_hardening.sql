USE msp_itsm;

DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_ticket_request_hardening $$

CREATE PROCEDURE migrate_ticket_request_hardening()
BEGIN

    /* =========================================================
       1. Normalize ticket status
       ========================================================= */

    ALTER TABLE tickets
      MODIFY status ENUM(
        'NEW',
        'TRIAGED',
        'ASSIGNED',
        'IN_PROGRESS',
        'PENDING_CUSTOMER',
        'PENDING_VENDOR',
        'PENDING_INTERNAL',
        'RESOLVED',
        'REOPENED',
        'CLOSED'
      ) NOT NULL DEFAULT 'NEW';


    /* =========================================================
       2. tickets.request_type
       ========================================================= */

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tickets'
          AND COLUMN_NAME = 'request_type'
    ) THEN
        ALTER TABLE tickets
          ADD COLUMN request_type VARCHAR(50) NULL AFTER service_id;
    END IF;


    /* =========================================================
       3. tickets.requester_user_id
       ========================================================= */

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tickets'
          AND COLUMN_NAME = 'requester_user_id'
    ) THEN
        ALTER TABLE tickets
          ADD COLUMN requester_user_id INT UNSIGNED NULL
          AFTER created_by_user_id;
    END IF;


    /* =========================================================
       4. tickets.first_response_at
       ========================================================= */

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tickets'
          AND COLUMN_NAME = 'first_response_at'
    ) THEN
        ALTER TABLE tickets
          ADD COLUMN first_response_at DATETIME NULL
          AFTER due_at;
    END IF;


    /* =========================================================
       5. tickets.sla_policy_snapshot
       ========================================================= */

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tickets'
          AND COLUMN_NAME = 'sla_policy_snapshot'
    ) THEN
        ALTER TABLE tickets
          ADD COLUMN sla_policy_snapshot JSON NULL
          AFTER first_response_at;
    END IF;


    /* =========================================================
       6. tickets.sla_target_response_at
       ========================================================= */

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tickets'
          AND COLUMN_NAME = 'sla_target_response_at'
    ) THEN
        ALTER TABLE tickets
          ADD COLUMN sla_target_response_at DATETIME NULL
          AFTER sla_policy_snapshot;
    END IF;


    /* =========================================================
       7. tickets.sla_target_resolve_at
       ========================================================= */

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tickets'
          AND COLUMN_NAME = 'sla_target_resolve_at'
    ) THEN
        ALTER TABLE tickets
          ADD COLUMN sla_target_resolve_at DATETIME NULL
          AFTER sla_target_response_at;
    END IF;


    /* =========================================================
       8. tickets.idx_ticket_requester
       ========================================================= */

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tickets'
          AND INDEX_NAME = 'idx_ticket_requester'
    ) THEN
        ALTER TABLE tickets
          ADD INDEX idx_ticket_requester(requester_user_id);
    END IF;


    /* =========================================================
       9. tickets.idx_ticket_sla_resolve
       ========================================================= */

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tickets'
          AND INDEX_NAME = 'idx_ticket_sla_resolve'
    ) THEN
        ALTER TABLE tickets
          ADD INDEX idx_ticket_sla_resolve(sla_target_resolve_at);
    END IF;


    /* =========================================================
       10. tickets.fk_ticket_requester
       ========================================================= */

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tickets'
          AND CONSTRAINT_NAME = 'fk_ticket_requester'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        ALTER TABLE tickets
          ADD CONSTRAINT fk_ticket_requester
          FOREIGN KEY(requester_user_id)
          REFERENCES users(id);
    END IF;


    /* =========================================================
       11. ticket_comments.visibility
       ========================================================= */

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'ticket_comments'
          AND COLUMN_NAME = 'visibility'
    ) THEN
        ALTER TABLE ticket_comments
          ADD COLUMN visibility ENUM(
            'CUSTOMER_VISIBLE',
            'INTERNAL_ONLY'
          ) NOT NULL DEFAULT 'CUSTOMER_VISIBLE'
          AFTER body;
    END IF;


    /* =========================================================
       12. ticket_comments visibility index
       ========================================================= */

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'ticket_comments'
          AND INDEX_NAME = 'idx_ticket_comments_visibility'
    ) THEN
        ALTER TABLE ticket_comments
          ADD INDEX idx_ticket_comments_visibility(
            ticket_id,
            visibility,
            created_at
          );
    END IF;


    /* =========================================================
       13. ticket_history event index
       ========================================================= */

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'ticket_history'
          AND INDEX_NAME = 'idx_history_event'
    ) THEN
        ALTER TABLE ticket_history
          ADD INDEX idx_history_event(
            ticket_id,
            event,
            created_at
          );
    END IF;

END $$

DELIMITER ;

CALL migrate_ticket_request_hardening();

DROP PROCEDURE IF EXISTS migrate_ticket_request_hardening;
