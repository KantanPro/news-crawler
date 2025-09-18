<?php
/**
 * Test script for News Crawler License API connection
 * 
 * This script tests the connection to the KantanPro License Manager API
 * to diagnose license verification issues.
 */

// Load WordPress
require_once( '../../../wp-load.php' );

// Check if user has admin privileges
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'このスクリプトを実行する権限がありません。' );
}

echo "<h1>News Crawler License API Connection Test</h1>\n";
echo "<p>実行時刻: " . current_time( 'Y-m-d H:i:s' ) . "</p>\n";

// Get license manager instance
$license_manager = NewsCrawler_License_Manager::get_instance();

echo "<h2>1. API Endpoint Test (GET)</h2>\n";
$get_test = $license_manager->test_api_endpoint_get();
echo "<p><strong>結果:</strong> " . ( $get_test['success'] ? '成功' : '失敗' ) . "</p>\n";
echo "<p><strong>メッセージ:</strong> " . esc_html( $get_test['message'] ) . "</p>\n";
echo "<p><strong>レスポンスコード:</strong> " . esc_html( $get_test['response_code'] ) . "</p>\n";
echo "<p><strong>Content-Type:</strong> " . esc_html( $get_test['content_type'] ) . "</p>\n";
echo "<p><strong>レスポンスボディ:</strong></p>\n";
echo "<pre>" . esc_html( $get_test['response_body'] ) . "</pre>\n";

echo "<h2>2. API Endpoint Test (POST)</h2>\n";
$post_test = $license_manager->test_klm_api_connection();
echo "<p><strong>結果:</strong> " . ( $post_test['success'] ? '成功' : '失敗' ) . "</p>\n";
echo "<p><strong>メッセージ:</strong> " . esc_html( $post_test['message'] ) . "</p>\n";
echo "<p><strong>レスポンスコード:</strong> " . esc_html( $post_test['response_code'] ) . "</p>\n";
echo "<p><strong>レスポンスボディ:</strong></p>\n";
echo "<pre>" . esc_html( $post_test['response_body'] ) . "</pre>\n";

echo "<h2>3. Site URL Test</h2>\n";
$site_url = home_url();
echo "<p><strong>サイトURL:</strong> " . esc_html( $site_url ) . "</p>\n";

// Test with a real license key if available
$license_key = get_option( 'news_crawler_license_key' );
if ( ! empty( $license_key ) ) {
    echo "<h2>4. Real License Key Test</h2>\n";
    echo "<p><strong>ライセンスキー:</strong> " . esc_html( substr( $license_key, 0, 8 ) . '...' ) . "</p>\n";
    
    $real_test = $license_manager->verify_license( $license_key );
    echo "<p><strong>結果:</strong> " . ( $real_test['success'] ? '成功' : '失敗' ) . "</p>\n";
    echo "<p><strong>メッセージ:</strong> " . esc_html( $real_test['message'] ) . "</p>\n";
    
    if ( isset( $real_test['debug_info'] ) ) {
        echo "<p><strong>デバッグ情報:</strong></p>\n";
        echo "<pre>" . esc_html( json_encode( $real_test['debug_info'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . "</pre>\n";
    }
} else {
    echo "<h2>4. Real License Key Test</h2>\n";
    echo "<p>ライセンスキーが設定されていません。</p>\n";
}

echo "<h2>5. WordPress Environment</h2>\n";
echo "<p><strong>WordPress Version:</strong> " . get_bloginfo( 'version' ) . "</p>\n";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>\n";
echo "<p><strong>Server Software:</strong> " . ( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ) . "</p>\n";
echo "<p><strong>WP_DEBUG:</strong> " . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'true' : 'false' ) . "</p>\n";

echo "<h2>6. Recent Error Logs</h2>\n";
$error_log = ini_get( 'error_log' );
if ( $error_log && file_exists( $error_log ) ) {
    $recent_logs = tail( $error_log, 20 );
    echo "<pre>" . esc_html( $recent_logs ) . "</pre>\n";
} else {
    echo "<p>エラーログファイルが見つかりません。</p>\n";
}

/**
 * Get last N lines of a file
 */
function tail( $file, $lines = 10 ) {
    $handle = fopen( $file, 'r' );
    if ( ! $handle ) {
        return false;
    }
    
    $linecounter = $lines;
    $pos = -2;
    $beginning = false;
    $text = array();
    
    while ( $linecounter > 0 ) {
        $t = " ";
        while ( $t != "\n" ) {
            if ( fseek( $handle, $pos, SEEK_END ) == -1 ) {
                $beginning = true;
                break;
            }
            $t = fgetc( $handle );
            $pos--;
        }
        $linecounter--;
        if ( $beginning ) {
            rewind( $handle );
        }
        $text[ $lines - $linecounter - 1 ] = fgets( $handle );
        if ( $beginning ) {
            break;
        }
    }
    fclose( $handle );
    return implode( "", array_reverse( $text ) );
}
?>
