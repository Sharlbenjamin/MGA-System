-- Fix provider_branches table contact ID columns
-- Run this script directly on your live server

-- First, let's check the current structure
DESCRIBE provider_branches;

-- Check for any foreign key constraints
SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME 
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'fdcpgwbqxd' 
AND TABLE_NAME = 'provider_branches' 
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Drop any existing foreign key constraints for contact columns
-- (This will fail if constraints don't exist, which is fine)
ALTER TABLE provider_branches DROP FOREIGN KEY IF EXISTS provider_branches_gop_contact_id_foreign;
ALTER TABLE provider_branches DROP FOREIGN KEY IF EXISTS provider_branches_operation_contact_id_foreign;
ALTER TABLE provider_branches DROP FOREIGN KEY IF EXISTS provider_branches_financial_contact_id_foreign;

-- Try to modify the columns to CHAR(36)
-- If columns don't exist, this will fail, but that's okay
ALTER TABLE provider_branches MODIFY COLUMN gop_contact_id CHAR(36) NULL;
ALTER TABLE provider_branches MODIFY COLUMN operation_contact_id CHAR(36) NULL;
ALTER TABLE provider_branches MODIFY COLUMN financial_contact_id CHAR(36) NULL;

-- Add the columns if they don't exist
-- (This will fail if columns already exist, which is fine)
ALTER TABLE provider_branches ADD COLUMN IF NOT EXISTS gop_contact_id CHAR(36) NULL;
ALTER TABLE provider_branches ADD COLUMN IF NOT EXISTS operation_contact_id CHAR(36) NULL;
ALTER TABLE provider_branches ADD COLUMN IF NOT EXISTS financial_contact_id CHAR(36) NULL;

-- Add foreign key constraints
ALTER TABLE provider_branches ADD CONSTRAINT provider_branches_gop_contact_id_foreign 
    FOREIGN KEY (gop_contact_id) REFERENCES contacts(id) ON DELETE SET NULL;

ALTER TABLE provider_branches ADD CONSTRAINT provider_branches_operation_contact_id_foreign 
    FOREIGN KEY (operation_contact_id) REFERENCES contacts(id) ON DELETE SET NULL;

ALTER TABLE provider_branches ADD CONSTRAINT provider_branches_financial_contact_id_foreign 
    FOREIGN KEY (financial_contact_id) REFERENCES contacts(id) ON DELETE SET NULL;

-- Verify the changes
DESCRIBE provider_branches; 