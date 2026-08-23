USE msp_itsm;

ALTER TABLE users
  ADD COLUMN portal_type ENUM('SERVICE','CUSTOMER') NOT NULL DEFAULT 'SERVICE' AFTER is_active,
  ADD COLUMN scope_type ENUM('GLOBAL','CUSTOMER','SERVICE','ASSIGNED') NOT NULL DEFAULT 'GLOBAL' AFTER portal_type,
  ADD COLUMN customer_id INT UNSIGNED NULL AFTER scope_type,
  ADD COLUMN service_id INT UNSIGNED NULL AFTER customer_id,
  ADD INDEX idx_users_customer_scope(customer_id,scope_type),
  ADD INDEX idx_users_service_scope(service_id,scope_type),
  ADD CONSTRAINT fk_users_customer_scope FOREIGN KEY(customer_id) REFERENCES customers(id),
  ADD CONSTRAINT fk_users_service_scope FOREIGN KEY(service_id) REFERENCES services(id);

UPDATE users u
JOIN roles r ON r.id = u.role_id
SET u.portal_type = CASE WHEN r.code = 'CUSTOMER' THEN 'CUSTOMER' ELSE 'SERVICE' END,
    u.scope_type = CASE WHEN r.code IN ('ADMIN','SERVICE_DESK_MANAGER','ENGINEER') THEN 'GLOBAL' ELSE 'ASSIGNED' END;

UPDATE users u
JOIN roles r ON r.id = u.role_id
SET u.scope_type = 'CUSTOMER'
WHERE r.code = 'CUSTOMER';
