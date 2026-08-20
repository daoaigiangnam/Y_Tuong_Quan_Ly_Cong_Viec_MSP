CREATE TABLE IF NOT EXISTS changes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    change_no VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    change_type VARCHAR(20) NOT NULL,
    priority VARCHAR(10) NOT NULL DEFAULT 'P3',
    risk VARCHAR(20) NOT NULL DEFAULT 'MEDIUM',
    impact VARCHAR(20) NOT NULL DEFAULT 'MEDIUM',
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    customer_id BIGINT UNSIGNED NULL,
    service_id BIGINT UNSIGNED NULL,
    requester_user_id BIGINT UNSIGNED NULL,
    owner_user_id BIGINT UNSIGNED NULL,
    approver_user_id BIGINT UNSIGNED NULL,
    implementation_plan TEXT NOT NULL,
    rollback_plan TEXT NOT NULL,
    test_plan TEXT NULL,
    success_criteria TEXT NOT NULL,
    reason TEXT NULL,
    planned_start_at DATETIME NULL,
    planned_end_at DATETIME NULL,
    actual_start_at DATETIME NULL,
    actual_end_at DATETIME NULL,
    approved_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_changes_change_no (change_no),
    KEY idx_changes_status (status),
    KEY idx_changes_customer (customer_id),
    KEY idx_changes_service (service_id),
    KEY idx_changes_owner (owner_user_id),
    KEY idx_changes_planned_start (planned_start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS change_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    change_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    event VARCHAR(50) NOT NULL,
    value VARCHAR(255) NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_change_history_change (change_id),
    KEY idx_change_history_created (created_at),
    CONSTRAINT fk_change_history_change FOREIGN KEY (change_id) REFERENCES changes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS change_tickets (
    change_id BIGINT UNSIGNED NOT NULL,
    ticket_id BIGINT UNSIGNED NOT NULL,
    linked_by_user_id BIGINT UNSIGNED NOT NULL,
    linked_at DATETIME NOT NULL,
    PRIMARY KEY (change_id, ticket_id),
    KEY idx_change_tickets_ticket (ticket_id),
    CONSTRAINT fk_change_tickets_change FOREIGN KEY (change_id) REFERENCES changes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS change_problems (
    change_id BIGINT UNSIGNED NOT NULL,
    problem_id BIGINT UNSIGNED NOT NULL,
    linked_by_user_id BIGINT UNSIGNED NOT NULL,
    linked_at DATETIME NOT NULL,
    PRIMARY KEY (change_id, problem_id),
    KEY idx_change_problems_problem (problem_id),
    CONSTRAINT fk_change_problems_change FOREIGN KEY (change_id) REFERENCES changes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
