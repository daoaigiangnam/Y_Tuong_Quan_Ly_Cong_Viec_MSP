USE msp_itsm;
INSERT INTO roles(code,name) VALUES ('ADMIN','Administrator'),('CUSTOMER','Customer'),('IT_OWNER','IT Owner'),('IT_SUPPORT','IT Support'),('IT_LEAD','IT Lead'),('SALES','Sales') ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO services(code,name,description) VALUES ('IT_SUPPORT','IT Support','General IT support'),('SERVER','Server','Server operation and maintenance'),('NETWORK','Network','Network operation and maintenance'),('APPLICATION','Application','Application support'),('CLOUD','Cloud','Cloud services') ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO customers(code,name,email,phone,status,created_at) VALUES ('DEMO','Demo Customer','customer@example.com','0900000000','ACTIVE',NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name);
