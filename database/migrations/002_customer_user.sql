USE msp_itsm;
ALTER TABLE users ADD COLUMN customer_id INT UNSIGNED NULL AFTER role_id;
ALTER TABLE users ADD INDEX idx_users_customer(customer_id);
ALTER TABLE users ADD CONSTRAINT fk_users_customer FOREIGN KEY(customer_id) REFERENCES customers(id);
