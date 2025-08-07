-- Fix contacts table column order
-- This script will recreate the contacts table with the correct column order
-- where 'id' is the first column, preserving all existing data

-- Step 1: Create a temporary table with the correct column order
CREATE TABLE contacts_temp (
    id CHAR(36) PRIMARY KEY,
    type ENUM('Client', 'Provider', 'Branch', 'Patient') NOT NULL,
    client_id CHAR(36) NULL,
    provider_id CHAR(36) NULL,
    branch_id CHAR(36) NULL,
    patient_id CHAR(36) NULL,
    name VARCHAR(255) NOT NULL,
    title VARCHAR(255) NULL,
    email VARCHAR(255) UNIQUE NULL,
    second_email VARCHAR(255) UNIQUE NULL,
    phone_number VARCHAR(255) NULL,
    second_phone VARCHAR(255) NULL,
    country_id BIGINT UNSIGNED NULL,
    city_id BIGINT UNSIGNED NULL,
    address VARCHAR(255) NULL,
    preferred_contact ENUM('Phone', 'Second Phone', 'Email', 'Second Email', 'first_whatsapp', 'second_whatsapp') NULL,
    status ENUM('Active', 'Inactive') NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX contacts_client_id_foreign (client_id),
    INDEX contacts_provider_id_foreign (provider_id),
    INDEX contacts_branch_id_foreign (branch_id),
    INDEX contacts_patient_id_foreign (patient_id),
    INDEX contacts_country_id_foreign (country_id),
    INDEX contacts_city_id_foreign (city_id)
);

-- Step 2: Copy all data from the old table to the new table
INSERT INTO contacts_temp 
SELECT id, type, client_id, provider_id, branch_id, patient_id, name, title, email, second_email, phone_number, second_phone, country_id, city_id, address, preferred_contact, status, created_at, updated_at 
FROM contacts;

-- Step 3: Drop the old table
DROP TABLE contacts;

-- Step 4: Rename the temporary table to the original name
RENAME TABLE contacts_temp TO contacts;

-- Step 5: Add foreign key constraints back
ALTER TABLE contacts 
ADD CONSTRAINT contacts_client_id_foreign FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
ADD CONSTRAINT contacts_provider_id_foreign FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
ADD CONSTRAINT contacts_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES provider_branches(id) ON DELETE CASCADE,
ADD CONSTRAINT contacts_patient_id_foreign FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
ADD CONSTRAINT contacts_country_id_foreign FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE SET NULL,
ADD CONSTRAINT contacts_city_id_foreign FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL; 