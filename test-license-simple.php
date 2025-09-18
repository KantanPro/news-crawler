<?php
/**
 * Simple license test script
 * 
 * This script tests the license verification without WordPress database connection
 */

// Test the API endpoint directly
$api_url = 'https://www.kantanpro.com/wp-json/ktp-license/v1/verify';
$site_url = 'https://example.com'; // Test site URL
$license_key = 'TEST-KEY-123456-TEST1234-ABCDEF'; // Test license key

echo "=== News Crawler License API Test ===\n";
echo "API URL: " . $api_url . "\n";
echo "Site URL: " . $site_url . "\n";
echo "License Key: " . $license_key . "\n\n";

// Prepare the request data
$data = array(
    'license_key' => $license_key,
    'site_url' => $site_url,
    'plugin_version' => '2.1.5'
);

// Encode the data
$body = http_build_query($data, '', '&', PHP_QUERY_RFC3986);

echo "Request Body: " . $body . "\n\n";

// Make the request
$context = stream_context_create(array(
    'http' => array(
        'method' => 'POST',
        'header' => array(
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'User-Agent: NewsCrawler/2.1.5'
        ),
        'content' => $body,
        'timeout' => 30
    )
));

echo "Making request...\n";
$response = file_get_contents($api_url, false, $context);

if ($response === false) {
    echo "ERROR: Failed to make request\n";
    $error = error_get_last();
    if ($error) {
        echo "Error details: " . $error['message'] . "\n";
    }
} else {
    echo "Response received:\n";
    echo "Length: " . strlen($response) . " bytes\n";
    echo "Content:\n";
    echo $response . "\n";
    
    // Try to decode as JSON
    $json_data = json_decode($response, true);
    if ($json_data) {
        echo "\nParsed JSON:\n";
        print_r($json_data);
    } else {
        echo "\nJSON decode failed. Raw response:\n";
        echo $response . "\n";
    }
}

echo "\n=== Test Complete ===\n";
?>
