<?php
/**
 * License verification debug script
 * 
 * This script tests the license verification and logs detailed information
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load WordPress
require_once('../../../wp-load.php');

echo "=== News Crawler License Debug Test ===\n";
echo "WordPress loaded successfully\n";

// Check if the license manager class exists
if (class_exists('NewsCrawler_License_Manager')) {
    echo "✓ NewsCrawler_License_Manager class found\n";
    
    try {
        // Get license manager instance
        $license_manager = NewsCrawler_License_Manager::get_instance();
        echo "✓ License manager instance created\n";
        
        // Test with a dummy license key
        $test_license_key = 'TEST-KEY-123456-TEST1234-ABCDEF';
        echo "Testing with license key: " . $test_license_key . "\n";
        
        // Call verify_license method
        $result = $license_manager->verify_license($test_license_key);
        
        echo "Verification result:\n";
        echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
        echo "Message: " . $result['message'] . "\n";
        
        if (isset($result['debug_info'])) {
            echo "Debug info:\n";
            print_r($result['debug_info']);
        }
        
    } catch (Exception $e) {
        echo "✗ Exception occurred: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    } catch (Error $e) {
        echo "✗ Fatal error occurred: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    
} else {
    echo "✗ NewsCrawler_License_Manager class not found\n";
    
    // Check if the file exists
    $class_file = NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-license-manager.php';
    if (file_exists($class_file)) {
        echo "✓ Class file exists: " . $class_file . "\n";
        
        // Check if the class is defined in the file
        $file_content = file_get_contents($class_file);
        if (strpos($file_content, 'class NewsCrawler_License_Manager') !== false) {
            echo "✓ Class definition found in file\n";
        } else {
            echo "✗ Class definition not found in file\n";
        }
    } else {
        echo "✗ Class file not found: " . $class_file . "\n";
    }
}

echo "\n=== Test Complete ===\n";
?>
