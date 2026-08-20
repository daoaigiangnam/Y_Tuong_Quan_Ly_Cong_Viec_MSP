USE msp_itsm;

CREATE TABLE IF NOT EXISTS problems (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    problem_no VARCHAR(80) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    problem_type ENUM('REACTIVE','PROACTIVE') NOT NULL,
    priority ENUM('P1','P2','P3','P4') NOT NULL DEFAULT 'P3',
    status ENUM('NEW','ASSESSING','INVESTIGATING','ROOT_CAUSE_IDENTIFIED','KNOWN_ERROR','FIX_PLANNED','FIX_IMPLEMENTED','VALIDATING','RESOLVED','CLOSED','REJECTED','CANCELLED') NOT NULL DEFAULT 'NEW',
    customer_id INT UNSIGNED NULL,
    service_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    lead_user_id INT UNSIGNED NULL,
    impact_summary TEXT NULL,
    root_cause TEXT NULL,
    workaround TEXT NULL,
    permanent_fix TEXT NULL,
    change_reference VARCHAR(80) NULL,
    discovered_at DATETIME NULL,
    root_cause_identified_at DATETIME NULL,
    resolved_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_by_user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY(customer_id) REFERENCES customers(id),
    FOREIGN KEY(service_id) REFERENCES services(id),
    FOREIGN KEY(owner_user_id) REFERENCES users(id),
    FOREIGN KEY(lead_user_id) REFERENCES users(id),
    FOREIGN KEY(created_by_user_id) REFERENCES users(id),
    INDEX idx_problem_status(status),
    INDEX idx_problem_priority(priority),
    INDEX idx_problem_customer(customer_id),
    INDEX idx_problem_service(service_id),
    INDEX idx_problem_owner(owner_user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS problem_tickets (
    problem_id INT UNSIGNED NOT NULL,
    ticket_id INT UNSIGNED NOT NULL,
    linked_by_user_id INT UNSIGNED NOT NULL,
    linked_at DATETIME NOT NULL,
    PRIMARY KEY(problem_id, ticket_id),
    FOREIGN KEY(problem_id) REFERENCES problems(id) ON DELETE CASCADE,
    FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY(linked_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS problem_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    problem_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    event VARCHAR(80) NOT NULL,
    value VARCHAR(255) NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY(problem_id) REFERENCES problems(id) ON DELETE CASCADE,
    FOREIGN KEY(user_id) REFERENCES users(id),
    INDEX idx_problem_history(problem_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS problem_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    problem_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    storage_key VARCHAR(500) NOT NULL,
    mime_type VARCHAR(150) NULL,
    file_size BIGINT UNSIGNED NULL,
    category VARCHAR(80) NULL,
    visibility ENUM('INTERNAL_ONLY','CUSTOMER_VISIBLE') NOT NULL DEFAULT 'INTERNAL_ONLY',
    uploaded_by_user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY(problem_id) REFERENCES problems(id) ON DELETE CASCADE,
    FOREIGN KEY(uploaded_by_user_id) REFERENCES users(id),
    INDEX idx_problem_documents(problem_id),
    INDEX idx_problem_document_visibility(problem_id,visibility)
) ENGINE=InnoDB;
