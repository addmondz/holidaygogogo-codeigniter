ALTER TABLE costing_packages ADD COLUMN customer_name VARCHAR(255) NULL DEFAULT NULL AFTER name;
ALTER TABLE costing_packages ADD COLUMN customer_contact VARCHAR(50) NULL DEFAULT NULL AFTER customer_name;
ALTER TABLE costing_packages ADD COLUMN customer_email VARCHAR(255) NULL DEFAULT NULL AFTER customer_contact;
