-- Comprehensive fix for provider_branches table contact ID columns
-- Run this script directly on your live server

-- Step 1: Check current table structure
SELECT 'Current table structure:' as info;
DESCRIBE provider_branches;

-- Step 2: Check current foreign key constraints
SELECT 'Current foreign key constraints:' as info;
SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME 
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'fdcpgwbqxd' 
AND TABLE_NAME = 'provider_branches' 
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Step 3: Check current indexes on contact columns
SELECT 'Current indexes on contact columns:' as info;
SHOW INDEX FROM provider_branches WHERE Column_name IN ('gop_contact_id', 'operation_contact_id', 'financial_contact_id');

-- Step 4: Drop ALL foreign key constraints for provider_branches
SELECT 'Dropping foreign key constraints...' as info;
SELECT CONCAT('ALTER TABLE provider_branches DROP FOREIGN KEY ', CONSTRAINT_NAME, ';') as drop_command
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'fdcpgwbqxd' 
AND TABLE_NAME = 'provider_branches' 
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Step 5: Drop any indexes on contact columns
SELECT 'Dropping indexes on contact columns...' as info;
SHOW INDEX FROM provider_branches WHERE Column_name IN ('gop_contact_id', 'operation_contact_id', 'financial_contact_id');

-- Step 6: Check if contact columns exist and their current type
SELECT 'Checking contact columns...' as info;
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'fdcpgwbqxd' 
AND TABLE_NAME = 'provider_branches' 
AND COLUMN_NAME IN ('gop_contact_id', 'operation_contact_id', 'financial_contact_id');

-- Step 7: Backup any existing data in contact columns
SELECT 'Backing up contact data...' as info;
SELECT id, gop_contact_id, operation_contact_id, financial_contact_id 
FROM provider_branches 
WHERE gop_contact_id IS NOT NULL OR operation_contact_id IS NOT NULL OR financial_contact_id IS NOT NULL;

-- Step 8: Drop contact columns if they exist
SELECT 'Dropping contact columns...' as info;
ALTER TABLE provider_branches DROP COLUMN IF EXISTS gop_contact_id;
ALTER TABLE provider_branches DROP COLUMN IF EXISTS operation_contact_id;
ALTER TABLE provider_branches DROP COLUMN IF EXISTS financial_contact_id;

-- Step 9: Add contact columns with correct UUID type
SELECT 'Adding contact columns with correct type...' as info;
ALTER TABLE provider_branches ADD COLUMN gop_contact_id CHAR(36) NULL;
ALTER TABLE provider_branches ADD COLUMN operation_contact_id CHAR(36) NULL;
ALTER TABLE provider_branches ADD COLUMN financial_contact_id CHAR(36) NULL;

-- Step 10: Add foreign key constraints
SELECT 'Adding foreign key constraints...' as info;
ALTER TABLE provider_branches ADD CONSTRAINT provider_branches_gop_contact_id_foreign 
    FOREIGN KEY (gop_contact_id) REFERENCES contacts(id) ON DELETE SET NULL;

ALTER TABLE provider_branches ADD CONSTRAINT provider_branches_operation_contact_id_foreign 
    FOREIGN KEY (operation_contact_id) REFERENCES contacts(id) ON DELETE SET NULL;

ALTER TABLE provider_branches ADD CONSTRAINT provider_branches_financial_contact_id_foreign 
    FOREIGN KEY (financial_contact_id) REFERENCES contacts(id) ON DELETE SET NULL;

-- Step 11: Verify the changes
SELECT 'Verifying changes...' as info;
DESCRIBE provider_branches;

SELECT 'Final foreign key constraints:' as info;
SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME 
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'fdcpgwbqxd' 
AND TABLE_NAME = 'provider_branches' 
AND REFERENCED_TABLE_NAME IS NOT NULL; 