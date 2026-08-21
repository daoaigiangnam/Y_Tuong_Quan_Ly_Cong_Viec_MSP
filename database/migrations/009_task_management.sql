USE msp_itsm;

CREATE TABLE IF NOT EXISTS task_policies (
    id TINYINT UNSIGNED PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    trigger_event VARCHAR(60) NOT NULL DEFAULT 'TICKET_ASSIGNMENT',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

INSERT INTO task_policies(id,code,enabled,trigger_event,created_at,updated_at)
VALUES(1,'CREATE_TASK_ON_SUPPORT_ASSIGNMENT',1,'TICKET_ASSIGNMENT',NOW(),NOW())
ON DUPLICATE KEY UPDATE code=VALUES(code), trigger_event=VALUES(trigger_event), updated_at=NOW();

CREATE TABLE IF NOT EXISTS tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_no VARCHAR(80) NOT NULL UNIQUE,
    ticket_id INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    priority ENUM('P1','P2','P3','P4') NOT NULL DEFAULT 'P3',
    status ENUM('NEW','ASSIGNED','IN_PROGRESS','BLOCKED','DONE','CANCELLED') NOT NULL DEFAULT 'NEW',
    assignee_user_id INT UNSIGNED NULL,
    created_by_user_id INT UNSIGNED NOT NULL,
    due_at DATETIME NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    FOREIGN KEY(assignee_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY(created_by_user_id) REFERENCES users(id),
    INDEX idx_task_ticket(ticket_id),
    INDEX idx_task_assignee(assignee_user_id),
    INDEX idx_task_status(status),
    INDEX idx_task_due(status,due_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS task_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    event VARCHAR(60) NOT NULL,
    value VARCHAR(255) NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY(user_id) REFERENCES users(id),
    INDEX idx_task_history(task_id,created_at)
) ENGINE=InnoDB;
