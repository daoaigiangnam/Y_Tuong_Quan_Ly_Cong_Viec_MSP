CREATE TABLE IF NOT EXISTS cmdb_ci_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cmdb_cis (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NULL,
    ci_type VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(100) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
    environment VARCHAR(30) NULL,
    hostname VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    fqdn VARCHAR(255) NULL,
    manufacturer VARCHAR(120) NULL,
    model VARCHAR(120) NULL,
    serial_number VARCHAR(120) NULL,
    owner_user_id BIGINT UNSIGNED NULL,
    description TEXT NULL,
    criticality VARCHAR(20) NOT NULL DEFAULT 'MEDIUM',
    customer_visible TINYINT(1) NOT NULL DEFAULT 0,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cmdb_customer (customer_id),
    INDEX idx_cmdb_service (service_id),
    INDEX idx_cmdb_type_status (ci_type, status),
    INDEX idx_cmdb_owner (owner_user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cmdb_ci_relationships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_ci_id BIGINT UNSIGNED NOT NULL,
    target_ci_id BIGINT UNSIGNED NOT NULL,
    relationship_type VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    retired_at DATETIME NULL,
    UNIQUE KEY uq_cmdb_relationship (source_ci_id, target_ci_id, relationship_type, status),
    INDEX idx_cmdb_rel_source (source_ci_id),
    INDEX idx_cmdb_rel_target (target_ci_id),
    CONSTRAINT fk_cmdb_rel_source FOREIGN KEY (source_ci_id) REFERENCES cmdb_cis(id) ON DELETE CASCADE,
    CONSTRAINT fk_cmdb_rel_target FOREIGN KEY (target_ci_id) REFERENCES cmdb_cis(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cmdb_ci_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ci_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    old_data JSON NULL,
    new_data JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cmdb_audit_ci (ci_id, created_at),
    CONSTRAINT fk_cmdb_audit_ci FOREIGN KEY (ci_id) REFERENCES cmdb_cis(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO cmdb_ci_types (code, name) VALUES
('SERVER','Server'),
('NETWORK','Network Device'),
('FIREWALL','Firewall'),
('DATABASE','Database'),
('APPLICATION','Application'),
('CLOUD_RESOURCE','Cloud Resource'),
('ENDPOINT','Endpoint'),
('OTHER','Other');
