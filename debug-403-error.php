<?php
/**
 * HTTP 403エラーのデバッグ用スクリプト
 * 本番環境で実行してHTTP 403エラーの原因を特定
 */

// WordPressの読み込み
require_once('../../../wp-config.php');

// デバッグ情報の出力
echo "=== HTTP 403エラーデバッグ情報 ===\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Site URL: " . home_url() . "\n";
echo "Admin URL: " . admin_url() . "\n";
echo "REST API URL: " . rest_url('ktp-license/v1/verify') . "\n";

// アクティブなプラグインの確認
echo "\n=== アクティブなプラグイン ===\n";
$active_plugins = get_option('active_plugins');
foreach ($active_plugins as $plugin) {
    echo "- " . $plugin . "\n";
}

// セキュリティプラグインの確認
$security_plugins = array(
    'wordfence/wordfence.php',
    'sucuri-scanner/sucuri.php',
    'ithemes-security/ithemes-security.php',
    'all-in-one-wp-security-and-firewall/wp-security.php',
    'better-wp-security/better-wp-security.php'
);

echo "\n=== セキュリティプラグインの確認 ===\n";
foreach ($security_plugins as $plugin) {
    if (in_array($plugin, $active_plugins)) {
        echo "⚠️  検出: " . $plugin . "\n";
    }
}

// REST APIのテスト
echo "\n=== REST APIテスト ===\n";

// 1. 基本的なREST APIテスト
$rest_url = rest_url('wp/v2/posts');
$response = wp_remote_get($rest_url);
if (is_wp_error($response)) {
    echo "❌ 基本的なREST APIエラー: " . $response->get_error_message() . "\n";
} else {
    $code = wp_remote_retrieve_response_code($response);
    echo "✅ 基本的なREST API: HTTP " . $code . "\n";
}

// 2. KantanPro License Manager APIテスト
$klm_url = rest_url('ktp-license/v1/info');
$response = wp_remote_get($klm_url . '?license_key=test');
if (is_wp_error($response)) {
    echo "❌ KLM APIエラー: " . $response->get_error_message() . "\n";
} else {
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    echo "✅ KLM API: HTTP " . $code . "\n";
    echo "レスポンス: " . substr($body, 0, 200) . "...\n";
}

// 3. 詳細なHTTP 403テスト
echo "\n=== HTTP 403詳細テスト ===\n";

$test_data = array(
    'license_key' => 'NCRL-145561-JUMG8|GG-366B',
    'site_url' => home_url(),
    'plugin_version' => '2.4.8'
);

$args = array(
    'method' => 'POST',
    'headers' => array(
        'Content-Type' => 'application/x-www-form-urlencoded',
        'User-Agent' => 'NewsCrawler/2.4.8 (WordPress/' . get_bloginfo('version') . '; PHP/' . PHP_VERSION . ')',
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest'
    ),
    'body' => http_build_query($test_data),
    'timeout' => 30,
    'sslverify' => true
);

$response = wp_remote_request(rest_url('ktp-license/v1/verify'), $args);

if (is_wp_error($response)) {
    echo "❌ リクエストエラー: " . $response->get_error_message() . "\n";
    echo "エラーコード: " . $response->get_error_code() . "\n";
} else {
    $code = wp_remote_retrieve_response_code($response);
    $headers = wp_remote_retrieve_headers($response);
    $body = wp_remote_retrieve_body($response);
    
    echo "レスポンスコード: " . $code . "\n";
    echo "レスポンスヘッダー:\n";
    foreach ($headers->getAll() as $key => $value) {
        echo "  " . $key . ": " . (is_array($value) ? implode(', ', $value) : $value) . "\n";
    }
    echo "レスポンスボディ: " . $body . "\n";
}

// 4. サーバー情報
echo "\n=== サーバー情報 ===\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "\n";

// 5. .htaccessの確認
echo "\n=== .htaccess確認 ===\n";
$htaccess_files = array(
    ABSPATH . '.htaccess',
    ABSPATH . 'wp-content/.htaccess',
    ABSPATH . 'wp-content/plugins/.htaccess'
);

foreach ($htaccess_files as $file) {
    if (file_exists($file)) {
        echo "✅ 存在: " . $file . "\n";
        $content = file_get_contents($file);
        if (strpos($content, '403') !== false || strpos($content, 'Forbidden') !== false) {
            echo "⚠️  403/Forbidden関連の設定が含まれています\n";
        }
    } else {
        echo "❌ 存在しない: " . $file . "\n";
    }
}

echo "\n=== デバッグ完了 ===\n";
?>
