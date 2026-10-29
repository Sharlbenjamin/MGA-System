<?php

// Simple script to fix provider_branches table on live server
// Upload this file to your live server and run: php fix_live_server.php

// Database configuration - update these values for your live server
$host = 'localhost';
$dbname = 'fdcpgwbqxd';
$username = 'fdcpgwbqxd';
$password = 'k2t25mHVn2';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n";
    
    // Step 1: Check current structure
    echo "\n=== Step 1: Checking current table structure ===\n";
    $stmt = $pdo->query("DESCRIBE provider_branches");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        if (in_array($column['Field'], ['gop_contact_id', 'operation_contact_id', 'financial_contact_id'])) {
            echo "Column: {$column['Field']}, Type: {$column['Type']}, Null: {$column['Null']}\n";
        }
    }
    
    // Step 2: Check foreign key constraints
    echo "\n=== Step 2: Checking foreign key constraints ===\n";
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = '$dbname' 
        AND TABLE_NAME = 'provider_branches' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($constraints as $constraint) {
        echo "Constraint: {$constraint['CONSTRAINT_NAME']}, Column: {$constraint['COLUMN_NAME']}, Referenced: {$constraint['REFERENCED_TABLE_NAME']}\n";
    }
    
    // Step 3: Drop foreign key constraints for contact columns
    echo "\n=== Step 3: Dropping foreign key constraints ===\n";
    foreach ($constraints as $constraint) {
        if (in_array($constraint['COLUMN_NAME'], ['gop_contact_id', 'operation_contact_id', 'financial_contact_id'])) {
            try {
                $pdo->exec("ALTER TABLE provider_branches DROP FOREIGN KEY {$constraint['CONSTRAINT_NAME']}");
                echo "Dropped constraint: {$constraint['CONSTRAINT_NAME']}\n";
            } catch (Exception $e) {
                echo "Could not drop constraint {$constraint['CONSTRAINT_NAME']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Step 4: Drop contact columns
    echo "\n=== Step 4: Dropping contact columns ===\n";
    $columnsToDrop = ['gop_contact_id', 'operation_contact_id', 'financial_contact_id'];
    foreach ($columnsToDrop as $column) {
        try {
            $pdo->exec("ALTER TABLE provider_branches DROP COLUMN IF EXISTS $column");
            echo "Dropped column: $column\n";
        } catch (Exception $e) {
            echo "Could not drop column $column: " . $e->getMessage() . "\n";
        }
    }
    
    // Step 5: Add contact columns with correct UUID type
    echo "\n=== Step 5: Adding contact columns with correct UUID type ===\n";
    $pdo->exec("ALTER TABLE provider_branches ADD COLUMN gop_contact_id CHAR(36) NULL");
    echo "Added gop_contact_id\n";
    
    $pdo->exec("ALTER TABLE provider_branches ADD COLUMN operation_contact_id CHAR(36) NULL");
    echo "Added operation_contact_id\n";
    
    $pdo->exec("ALTER TABLE provider_branches ADD COLUMN financial_contact_id CHAR(36) NULL");
    echo "Added financial_contact_id\n";
    
    // Step 6: Add foreign key constraints
    echo "\n=== Step 6: Adding foreign key constraints ===\n";
    $pdo->exec("
        ALTER TABLE provider_branches ADD CONSTRAINT provider_branches_gop_contact_id_foreign 
        FOREIGN KEY (gop_contact_id) REFERENCES contacts(id) ON DELETE SET NULL
    ");
    echo "Added gop_contact_id foreign key\n";
    
    $pdo->exec("
        ALTER TABLE provider_branches ADD CONSTRAINT provider_branches_operation_contact_id_foreign 
        FOREIGN KEY (operation_contact_id) REFERENCES contacts(id) ON DELETE SET NULL
    ");
    echo "Added operation_contact_id foreign key\n";
    
    $pdo->exec("
        ALTER TABLE provider_branches ADD CONSTRAINT provider_branches_financial_contact_id_foreign 
        FOREIGN KEY (financial_contact_id) REFERENCES contacts(id) ON DELETE SET NULL
    ");
    echo "Added financial_contact_id foreign key\n";
    
    // Step 7: Verify changes
    echo "\n=== Step 7: Verifying changes ===\n";
    $stmt = $pdo->query("DESCRIBE provider_branches");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        if (in_array($column['Field'], ['gop_contact_id', 'operation_contact_id', 'financial_contact_id'])) {
            echo "Column: {$column['Field']}, Type: {$column['Type']}, Null: {$column['Null']}\n";
        }
    }
    
    echo "\n=== SUCCESS! Provider branches table has been fixed. ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 