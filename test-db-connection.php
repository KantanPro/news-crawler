<?php
/**
 * Database connection test
 * 
 * This script tests the database connection from within the WordPress container
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Database Connection Test ===\n";

// Test database connection
$db_host = 'db:3306';
$db_name = 'wordpress';
$db_user = 'wordpress';
$db_pass = 'wordpress';

echo "Testing database connection...\n";
echo "Host: " . $db_host . "\n";
echo "Database: " . $db_name . "\n";
echo "User: " . $db_user . "\n";

try {
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($mysqli->connect_error) {
        echo "✗ Database connection failed: " . $mysqli->connect_error . "\n";
        exit(1);
    } else {
        echo "✓ Database connection successful\n";
        
        // Test query
        $result = $mysqli->query("SHOW TABLES");
        if ($result) {
            echo "✓ Database query successful\n";
            echo "Tables found: " . $result->num_rows . "\n";
        } else {
            echo "✗ Database query failed: " . $mysqli->error . "\n";
        }
        
        $mysqli->close();
    }
    
} catch (Exception $e) {
    echo "✗ Exception occurred: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?>
