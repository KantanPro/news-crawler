<?php
/**
 * WordPress connection test
 * 
 * This script tests if WordPress can be loaded and the license manager works
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== WordPress Connection Test ===\n";

// Test database connection first
$db_host = 'localhost';
$db_name = 'wordpress';
$db_user = 'root';
$db_pass = '';

echo "Testing database connection...\n";
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    echo "✗ Database connection failed: " . $mysqli->connect_error . "\n";
    echo "Please make sure MySQL is running and the database 'wordpress' exists.\n";
    exit(1);
} else {
    echo "✓ Database connection successful\n";
    $mysqli->close();
}

// Test WordPress loading
echo "Testing WordPress loading...\n";
try {
    // Load WordPress
    require_once('../../../wp-load.php');
    echo "✓ WordPress loaded successfully\n";
    
    // Check if the license manager class exists
    if (class_exists('NewsCrawler_License_Manager')) {
        echo "✓ NewsCrawler_License_Manager class found\n";
        
        // Get license manager instance
        $license_manager = NewsCrawler_License_Manager::get_instance();
        echo "✓ License manager instance created\n";
        
        // Test API connection
        echo "Testing API connection...\n";
        $get_test = $license_manager->test_api_endpoint_get();
        
        if ($get_test['success']) {
            echo "✓ API connection test successful\n";
            echo "Response: " . $get_test['message'] . "\n";
        } else {
            echo "✗ API connection test failed\n";
            echo "Error: " . $get_test['message'] . "\n";
        }
        
    } else {
        echo "✗ NewsCrawler_License_Manager class not found\n";
    }
    
} catch (Exception $e) {
    echo "✗ Exception occurred: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "✗ Fatal error occurred: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?>
