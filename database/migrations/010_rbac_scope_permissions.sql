USE msp_itsm;

/*
 * RBAC + portal/scope hardening.
 * Safe to run against an existing database: every additive object is guarded.
 */

DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_rbac_scope_permissions $$
CREATE PROCEDURE migrate_rbac_scope_permissions()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='portal_type'
    ) THEN
        ALTER TABLE users ADD COLUMN portal_type ENUM('SERVICE','CUSTOMER') NOT NULL DEFAULT 'SERVICE' AFTER role_id;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='scope_type'
    ) THEN
        ALTER TABLE users ADD COLUMN scope_type ENUM('GLOBAL','CUSTOMER','SERVICE','ASSIGNED') NOT NULL DEFAULT 'GLOBAL' AFTER portal_type;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='customer_id'
    ) THEN
        ALTER TABLE users ADD COLUMN customer_id INT UNSIGNED NULL AFTER scope_type;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='service_id'
    ) THEN
        ALTER TABLE users ADD COLUMN service_id INT UNSIGNED NULL AFTER customer_id;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='idx_users_customer'
    ) THEN
        ALTER TABLE users ADD INDEX idx_users_customer(customer_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='idx_users_service'
    ) THEN
        ALTER TABLE users ADD INDEX idx_users_service(service_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='permissions'
    ) THEN
        CREATE TABLE permissions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(100) NOT NULL UNIQUE,
            name VARCHAR(150) NOT NULL,
            module VARCHAR(60) NOT NULL,
            action VARCHAR(40) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_permissions_module(module)
        ) ENGINE=InnoDB;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions'
    ) THEN
        CREATE TABLE role_permissions (
            role_id INT UNSIGNED NOT NULL,
            permission_id INT UNSIGNED NOT NULL,
            PRIMARY KEY(role_id,permission_id),
            FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY(permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='users'
          AND CONSTRAINT_NAME='fk_users_customer_scope'
    ) THEN
        ALTER TABLE users ADD CONSTRAINT fk_users_customer_scope
            FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='users'
          AND CONSTRAINT_NAME='fk_users_service_scope'
    ) THEN
        ALTER TABLE users ADD CONSTRAINT fk_users_service_scope
            FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE SET NULL;
    END IF;
END $$

DELIMITER ;
CALL migrate_rbac_scope_permissions();
DROP PROCEDURE IF EXISTS migrate_rbac_scope_permissions;

INSERT INTO permissions(code,name,module,action) VALUES
('dashboard.view','View dashboard','dashboard','view'),
('customer.view','View customers','customer','view'),
('customer.manage','Manage customers','customer','manage'),
('contact.view','View customer contacts','contact','view'),
('contact.manage','Manage customer contacts','contact','manage'),
('service.view','View services','service','view'),
('service.manage','Manage services','service','manage'),
('contract.view','View contracts','contract','view'),
('contract.manage','Manage contracts','contract','manage'),
('ticket.view','View tickets','ticket','view'),
('ticket.create','Create tickets','ticket','create'),
('ticket.update','Update tickets','ticket','update'),
('ticket.assign','Assign tickets','ticket','assign'),
('problem.view','View problems','problem','view'),
('problem.manage','Manage problems','problem','manage'),
('change.view','View changes','change','view'),
('change.manage','Manage changes','change','manage'),
('knowledge.view','View knowledge','knowledge','view'),
('knowledge.manage','Manage knowledge','knowledge','manage'),
('cmdb.view','View CMDB','cmdb','view'),
('cmdb.manage','Manage CMDB','cmdb','manage'),
('task.view','View tasks','task','view'),
('task.manage','Manage tasks','task','manage'),
('report.view','View reports','report','view')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),action=VALUES(action);

/* Administrative roles receive the complete permission set. */
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='ADMIN';

/* Internal operational roles. */
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE r.code='IT_OWNER' AND p.code IN (
'dashboard.view','customer.view','contact.view','service.view','contract.view','contract.manage',
'ticket.view','ticket.create','ticket.update','ticket.assign','problem.view','problem.manage',
'change.view','change.manage','knowledge.view','knowledge.manage','cmdb.view','cmdb.manage',
'task.view','task.manage','report.view');

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE r.code='IT_LEAD' AND p.code IN (
'dashboard.view','customer.view','contact.view','service.view','contract.view','ticket.view',
'ticket.create','ticket.update','ticket.assign','problem.view','problem.manage','change.view',
'change.manage','knowledge.view','knowledge.manage','cmdb.view','cmdb.manage','task.view','task.manage','report.view');

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE r.code='IT_SUPPORT' AND p.code IN (
'dashboard.view','customer.view','contact.view','service.view','contract.view',
'ticket.view','ticket.create','ticket.update','ticket.assign','problem.view','problem.manage',
'knowledge.view','knowledge.manage','cmdb.view','task.view','task.manage');

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE r.code='SALES' AND p.code IN (
'dashboard.view','customer.view','contact.view','contact.manage','service.view','contract.view','contract.manage');

/* Customer portal is deliberately restricted to customer-facing operations. */
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE r.code='CUSTOMER' AND p.code IN ('ticket.view','ticket.create','ticket.update','knowledge.view');

UPDATE users u JOIN roles r ON r.id=u.role_id SET u.portal_type='CUSTOMER',u.scope_type='CUSTOMER'
WHERE r.code='CUSTOMER';

UPDATE users u JOIN roles r ON r.id=u.role_id SET u.portal_type='SERVICE'
WHERE r.code<>'CUSTOMER';
